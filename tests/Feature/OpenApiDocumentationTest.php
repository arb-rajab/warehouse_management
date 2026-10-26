<?php

use App\Models\User;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

test('the generated OpenAPI document types the mobile dashboard stats field as an object, not a string', function () {
    $generator = app(Generator::class);
    $document = $generator(Scramble::getGeneratorConfig('default'));

    $schema = $document['paths']['/api/v1/dashboard']['get']['responses']['200']['content']['application/json']['schema'];
    $statsSchema = $schema['properties']['stats'];

    expect($statsSchema['type'])->toBe('object');
    expect($statsSchema['properties'])->toHaveKeys(['occupancy', 'expiring', 'activity_today', 'activity_week']);
});

test('the API reference covers implemented routes with their actual authentication and resource envelopes', function () {
    // Scramble caches reflection across application instances; export in a fresh
    // process, as the CLI does, so another test's inferred types cannot leak in.
    $exportPath = tempnam(sys_get_temp_dir(), 'openapi-');
    try {
        $result = Process::path(base_path())->run([
            PHP_BINARY, 'artisan', 'scramble:export', '--path='.$exportPath,
        ]);
        expect($result->successful())->toBeTrue($result->errorOutput());
        $document = json_decode(file_get_contents($exportPath), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        unlink($exportPath);
    }
    $normalizePath = fn (string $path): string => preg_replace('/\{[^}]+\}/', '{}', $path);
    $operations = [];

    foreach ($document['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            $operations[$method.' '.$normalizePath($path)] = $operation;
        }
    }

    $missingMethods = [];
    foreach (Route::getRoutes() as $route) {
        if ((! str_starts_with($route->uri(), 'temp/api/') && ! str_starts_with($route->uri(), 'api/')) || $route->isFallback) {
            continue;
        }

        $path = substr($route->uri(), strlen('temp/api'));
        if (! method_exists($route->getControllerClass(), $route->getActionMethod())) {
            $missingMethods[] = $path;

            continue;
        }

        $key = strtolower($route->methods()[0]).' '.$normalizePath('/'.$route->uri());
        expect($operations)->toHaveKey($key);
        $security = $operations[$key]['security'] ?? $document['security'] ?? [];
        $middleware = $route->gatherMiddleware();

        expect($security)->toBe(match (true) {
            in_array('wms.auth', $middleware, true) => [['privateToken' => []]],
            in_array('auth:store_api', $middleware, true), in_array('auth:sanctum', $middleware, true) => [['http' => []]],
            default => [],
        });
        unset($operations[$key]);
    }

    expect($operations)->toBeEmpty();
    expect($missingMethods)->toEqualCanonicalizing([
        '/v2/products/featured-from-seller/{id}',
        '/v2/products/inhouse',
        '/v2/products/sub-category/{id}',
        '/v2/products/sub-sub-category/{id}',
        '/v2/seller/dashboard/dashboard-counters',
        '/v2/seller/products/search',
        '/v2/trips/notifications',
        '/v2/wallet/offline-recharge',
    ]);
    expect(parse_url($document['servers'][0]['url'], PHP_URL_PATH) ?: '/')->toBe('/');
    expect($document['paths'])->toHaveKey('/api/v1/dashboard')->not->toHaveKey('/temp/api/{fallbackPlaceholder}');
    expect($document['components']['securitySchemes']['privateToken'])->toBe([
        'type' => 'apiKey', 'in' => 'header', 'name' => 'private-token',
    ]);

    $login = $document['paths']['/temp/api/v2/auth/login']['post']['requestBody']['content']['application/json']['schema'];
    expect($login['properties']['email']['type'])->toBe('string');
    expect($login['properties']['email'])->not->toHaveKey('format');
    expect($login['required'])->toEqualCanonicalizing(['email', 'password', 'login_by']);

    $resolveSchema = function (array $schema) use (&$resolveSchema, $document): array {
        if (isset($schema['$ref'])) {
            return $resolveSchema($document['components']['schemas'][basename($schema['$ref'])]);
        }

        foreach ($schema['allOf'] ?? [] as $part) {
            $schema = array_replace_recursive($schema, $resolveSchema($part));
        }

        return $schema;
    };
    $products = $resolveSchema($document['paths']['/temp/api/v2/products']['get']['responses']['200']['content']['application/json']['schema']);
    expect($products['properties'])->toHaveKeys(['data', 'links', 'meta', 'success', 'status']);
    expect($products['properties']['data']['type'])->toBe('array');
    expect(JsonResource::$wrap)->toBeNull();

    $warehouseUser = $resolveSchema($document['paths']['/api/v1/user']['get']['responses']['200']['content']['application/json']['schema']);
    expect($warehouseUser['properties'])->toHaveKey('id')->not->toHaveKey('data');
});

test('the unified API documentation is restricted and has no separate store reference', function () {
    foreach (['/docs/api', '/docs/api.json'] as $path) {
        $this->get($path)->assertForbidden();
    }

    Gate::define('viewApiDocs', fn (?User $user): bool => true);

    $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'API');
    $this->get('/docs/api')->assertOk()->assertSee('API');
    $this->get('/docs/store-api')->assertNotFound();
    $this->get('/docs/store-api.json')->assertNotFound();
});

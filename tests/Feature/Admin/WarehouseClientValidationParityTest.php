<?php

use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rules\Unique;

/*
 * The warehouse form validates in the browser first, from a copy of its
 * FormRequests' rules: resources/js/lib/warehouseRules.json, with the messages
 * in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree. The `unique` rules
 * on the name and code read the warehouses table, so only the server runs them.
 */

/**
 * The request's rules as the form's submit builds them.
 *
 * @param  class-string  $class
 * @return array<string, array<mixed>>
 */
function warehouseRequestRules(string $class, ?Warehouse $warehouse = null): array
{
    $request = $class::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    if ($warehouse !== null) {
        $route = (new Route('PUT', 'warehouses/{warehouse}', fn () => null))->bind($request);
        $route->setParameter('warehouse', $warehouse);
        $request->setRouteResolver(fn () => $route);
    }

    return app()->call([$request, 'rules']);
}

/** @return array<string, list<string>> */
function warehouseClientRules(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/warehouseRules.json')), true, flags: JSON_THROW_ON_ERROR)['rules'];
}

test('the warehouse form\'s client rules mirror the server rules', function (string $class) {
    $server = warehouseRequestRules($class, $class === UpdateWarehouseRequest::class ? (new Warehouse)->forceFill(['id' => 1]) : null);
    $client = warehouseClientRules();

    expect(array_keys($client))->toEqualCanonicalizing(array_keys($server));

    foreach ($server as $field => $rules) {
        // Everything but `unique` is checked in the browser, in the server's order.
        $copied = array_values(array_filter($rules, fn (mixed $rule): bool => is_string($rule)));
        $serverOnly = array_values(array_filter($rules, fn (mixed $rule): bool => ! is_string($rule)));

        expect($client[$field])->toBe($copied, "{$field}: warehouseRules.json differs from the server rules");

        foreach ($serverOnly as $rule) {
            expect($rule)->toBeInstanceOf(Unique::class, "{$field}: a server rule neither copied nor listed as server-only");
        }
    }
})->with([
    // Wrapped, not a bare [class, class] pair: Pest runs a two-element array as a callable when the
    // class answers static calls, and a FormRequest does (Macroable::__callStatic).
    'store request' => [StoreWarehouseRequest::class],
    'update request' => [UpdateWarehouseRequest::class],
]);

test('only the name and the code are unique on the server, which the browser leaves to it', function () {
    $server = warehouseRequestRules(StoreWarehouseRequest::class);
    $unique = array_keys(array_filter($server, fn (array $rules): bool => Arr::first($rules, fn (mixed $rule): bool => $rule instanceof Unique) !== null));

    expect($unique)->toBe(['name', 'code']);
});

test('the browser messages repeat the server wording for the warehouse fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['required', 'string', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(warehouseClientRules()) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})");
    }
})->with(['en', 'ar']);

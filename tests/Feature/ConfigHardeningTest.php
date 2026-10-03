<?php

use App\Http\Middleware\HttpsProtocol;
use App\Http\Middleware\RestrictToAllowedIps;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Laravel\Pulse\Http\Middleware\Authorize;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;

/**
 * Evaluates a config file in a clean child process whose environment holds
 * only `$env` (the variables this file's tests care about start out unset),
 * so a default can be tested even though the test run's own environment, .env
 * file included, sets some of them.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function loadConfigWithEnv(string $file, array $env): array
{
    $code = <<<'PHP'
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) { return $default; }
    return match (strtolower($value)) { 'true', '(true)' => true, 'false', '(false)' => false, 'null', '(null)' => null, 'empty', '(empty)' => '', default => $value };
}
function base_path($path = '') { return '/base/'.$path; }
function storage_path($path = '') { return '/storage/'.$path; }
require getenv('TEST_AUTOLOAD');
echo json_encode(require getenv('TEST_CONFIG_FILE'), JSON_PARTIAL_OUTPUT_ON_ERROR);
PHP;

    $unset = array_fill_keys(['APP_ENV', 'TELESCOPE_ENABLED', 'CSP_REPORT_ONLY', 'BACKUP_OFFSITE_DISK', 'FORCE_HTTPS'], false);

    $process = new Process([PHP_BINARY, '-d', 'display_errors=0', '-r', $code], base_path(), [
        ...$unset,
        ...$env,
        'TEST_AUTOLOAD' => base_path('vendor/autoload.php'),
        'TEST_CONFIG_FILE' => config_path($file),
    ]);
    $process->mustRun();

    return json_decode($process->getOutput(), true);
}

test('telescope defaults to disabled outside local when TELESCOPE_ENABLED is unset', function () {
    expect(loadConfigWithEnv('telescope.php', ['APP_ENV' => 'production'])['enabled'])->toBeFalse()
        ->and(loadConfigWithEnv('telescope.php', ['APP_ENV' => 'local'])['enabled'])->toBeTrue();
});

test('an explicit TELESCOPE_ENABLED still overrides the default', function () {
    expect(loadConfigWithEnv('telescope.php', ['APP_ENV' => 'production', 'TELESCOPE_ENABLED' => 'true'])['enabled'])->toBeTrue();
});

test('the health json endpoint answers 503 when a check fails', function () {
    expect(config('health.json_results_failure_status'))->toBe(503);
});

test('the WAF only skips the _boost routes locally', function () {
    expect(loadConfigWithEnv('waf.php', ['APP_ENV' => 'local'])['exclude_paths'])->toContain('_boost', '_boost/*')
        ->and(loadConfigWithEnv('waf.php', ['APP_ENV' => 'production'])['exclude_paths'])->not->toContain('_boost')->not->toContain('_boost/*')
        ->and(config('waf.exclude_paths'))->not->toContain('_boost')
        ->and(config('waf.exclude_paths'))->toContain('telescope', 'up');
});

test('the CSP is enforced in production unless CSP_REPORT_ONLY says otherwise', function () {
    $reportOnly = fn (array $env) => loadConfigWithEnv('secure-headers.php', $env)['csp']['report-only'];

    expect($reportOnly(['APP_ENV' => 'production']))->toBeFalse()
        ->and($reportOnly(['APP_ENV' => 'local']))->toBeTrue()
        ->and($reportOnly(['APP_ENV' => 'production', 'CSP_REPORT_ONLY' => 'true']))->toBeTrue();
});

test('no permissions-policy feature is open to every origin', function () {
    $features = config('secure-headers.permissions-policy');

    foreach ($features as $name => $policy) {
        if (is_array($policy) && array_key_exists('*', $policy)) {
            expect($policy['*'])->toBeFalse("permissions-policy {$name} allows every origin");
        }
    }

    foreach (['attribution-reporting', 'browsing-topics', 'document-domain', 'picture-in-picture', 'storage-access'] as $feature) {
        expect($features[$feature]['none'])->toBeTrue("permissions-policy {$feature} should be switched off");
    }

    $this->get('/admin/login')->assertHeader('Permissions-Policy');
});

test('every queue connection dispatches jobs only after the surrounding transaction commits', function () {
    foreach (config('queue.connections') as $name => $connection) {
        if (array_key_exists('after_commit', $connection)) {
            expect($connection['after_commit'])->toBeTrue("queue connection {$name}");
        }
    }
});

test('the backup goes to an off-site disk when one is configured, and the local one otherwise', function () {
    $without = loadConfigWithEnv('backup.php', ['BACKUP_OFFSITE_DISK' => '']);
    $with = loadConfigWithEnv('backup.php', ['BACKUP_OFFSITE_DISK' => 's3']);

    expect($without['backup']['destination']['disks'])->toBe(['local'])
        ->and($with['backup']['destination']['disks'])->toBe(['local', 's3'])
        ->and($with['monitor_backups'][0]['disks'])->toBe(['local', 's3']);
});

function dispatchCommandStarting(string $command): void
{
    event(new CommandStarting($command, new ArrayInput([]), new NullOutput));
}

test('backup:run refuses to run in production without an archive password', function () {
    config(['backup.backup.password' => null]);
    app()->instance('env', 'production');

    expect(fn () => dispatchCommandStarting('backup:run'))->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD');
});

test('backup:run runs in production once a password is set, and other commands never need one', function () {
    app()->instance('env', 'production');

    config(['backup.backup.password' => 'a-long-random-secret']);
    dispatchCommandStarting('backup:run');

    config(['backup.backup.password' => null]);
    dispatchCommandStarting('products:sync');

    expect(true)->toBeTrue();
});

test('backup:run is not blocked outside production without a password', function () {
    config(['backup.backup.password' => null]);

    dispatchCommandStarting('backup:run');

    expect(true)->toBeTrue();
});

test('the local disk is not exposed through the storage route without a signature', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/licenses/license.pdf', 'secret');

    expect($this->get('/storage/uploads/licenses/license.pdf')->getStatusCode())->toBeIn([403, 404]);
});

test('HttpsProtocol redirects insecure requests only when FORCE_HTTPS is on', function () {
    $request = Request::create('http://warehouse.test/shop', 'GET');
    $middleware = new HttpsProtocol;

    config(['app.force_https' => false]);
    expect($middleware->handle($request, fn () => new Response('ok'))->getContent())->toBe('ok');

    config(['app.force_https' => true]);
    $redirect = $middleware->handle($request, fn () => new Response('ok'));

    expect($redirect->getStatusCode())->toBe(302)
        ->and($redirect->headers->get('Location'))->toStartWith('https://')->toEndWith('/shop');

    $secure = Request::create('https://warehouse.test/shop', 'GET');
    expect($middleware->handle($secure, fn () => new Response('ok'))->getContent())->toBe('ok');
});

test('FORCE_HTTPS reaches the middleware through config', function () {
    expect(loadConfigWithEnv('app.php', ['FORCE_HTTPS' => 'On'])['force_https'])->toBeTrue()
        ->and(loadConfigWithEnv('app.php', ['FORCE_HTTPS' => 'Off'])['force_https'])->toBeFalse();
});

test('passwords are never flashed back to the session', function () {
    $handler = app(ExceptionHandler::class);
    $dontFlash = (new ReflectionProperty($handler, 'dontFlash'))->getValue($handler);

    expect($dontFlash)->toContain('current_password', 'password', 'password_confirmation');
});

test('pulse checks the IP allowlist before the authorization gate', function () {
    $middleware = config('pulse.middleware');

    expect(array_search(RestrictToAllowedIps::class.':pulse.allowed_ips', $middleware))
        ->toBeLessThan(array_search(Authorize::class, $middleware));
});

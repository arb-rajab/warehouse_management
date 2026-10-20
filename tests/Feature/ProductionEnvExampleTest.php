<?php

use Dotenv\Dotenv;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The keys the template sets, active or commented out as `# KEY=value`, with
 * the commented ones recognised only when the whole line is a bare
 * assignment (not a sentence that happens to mention one).
 *
 * @return array{active: array<string, string|null>, commented: list<string>}
 */
function productionEnvExampleKeys(): array
{
    $contents = file_get_contents(base_path('.env.production.example'));
    preg_match_all('/^# ([A-Z][A-Z0-9_]*)=[^\s,]*$/m', $contents, $commented);

    return ['active' => Dotenv::parse($contents), 'commented' => $commented[1]];
}

/**
 * Every environment variable the application reads: env() and Env::get() in
 * the PHP the framework loads configuration and bootstrapping from, and VITE_ variables
 * in the frontend.
 *
 * @return list<string>
 */
function environmentVariablesTheApplicationReads(): array
{
    $keys = [];

    foreach ((new Finder)->files()->in([base_path('config'), base_path('app'), base_path('bootstrap'), base_path('routes'), base_path('database')])->name('*.php') as $file) {
        preg_match_all('/\b(?:env|Env::get)\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', $file->getContents(), $matches);
        array_push($keys, ...$matches[1]);
    }

    foreach ((new Finder)->files()->in(base_path('resources/js'))->name(['*.ts', '*.vue']) as $file) {
        preg_match_all('/import\.meta\.env\.(VITE_[A-Z0-9_]+)/', $file->getContents(), $matches);
        array_push($keys, ...$matches[1]);
    }

    return array_values(array_unique($keys));
}

test('the production env template exists and parses into a value for every active line, without duplicates', function () {
    $contents = file_get_contents(base_path('.env.production.example'));
    preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $contents, $assignments);

    expect($assignments[1])->not->toBeEmpty()
        ->and($assignments[1])->toBe(array_values(array_unique($assignments[1])))
        ->and(array_keys(productionEnvExampleKeys()['active']))->toBe($assignments[1]);
});

test('the production env template sets the safe values the go-live checklist asks for', function () {
    $env = productionEnvExampleKeys()['active'];

    expect($env)->toMatchArray([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'SESSION_SECURE_COOKIE' => 'true',
        'CSP_ENABLED' => 'true',
        'CSP_REPORT_ONLY' => 'false',
        'HSTS_ENABLED' => 'true',
        'WAF_ENABLED' => 'true',
        'HONEYPOT_ENABLED' => 'true',
        'TELESCOPE_ENABLED' => 'false',
        'DB_CONNECTION' => 'mysql',
        'MAIL_MAILER' => 'smtp',
        'SESSION_DRIVER' => 'database',
        'CACHE_STORE' => 'database',
        'QUEUE_CONNECTION' => 'database',
        'LOG_STACK' => 'daily',
        'LOG_LEVEL' => 'warning',
        'APP_KEY' => '',
    ])
        ->and($env['APP_URL'])->toStartWith('https://')
        ->and($env['APP_URL'])->not->toContain('localhost')
        ->and($env['BACKUP_OFFSITE_DISK'])->toBe('s3')
        ->and($env)->toHaveKey('BACKUP_ARCHIVE_PASSWORD')
        ->and($env['BACKUP_ARCHIVE_PASSWORD'])->toBe('')
        ->and($env)->not->toHaveKeys(['FORCE_HTTPS', 'DEMO_MODE', 'STORE_PAYMENT_GATEWAYS', 'TRUSTED_PROXIES']);
});

test('the production env template carries no real-looking secret', function () {
    $env = productionEnvExampleKeys()['active'];
    $secretKey = '/(_KEY|_SECRET|_PASSWORD|_TOKEN|_URL_KEY)$|^(STORE_PRODUCTS_SYNC_URL|MAIL_USERNAME|DB_USERNAME)$/';

    foreach ($env as $key => $value) {
        if (preg_match($secretKey, $key)) {
            expect($value === '' || str_contains((string) $value, 'CHANGE_ME'))->toBeTrue("{$key} must be empty or a CHANGE_ME placeholder, got a value");
        }

        expect((string) $value)
            ->not->toMatch('/^base64:/', "{$key} looks like an application key")
            ->not->toMatch('/AKIA[0-9A-Z]{12,}/', "{$key} looks like an AWS access key id")
            ->not->toMatch('/^[A-Za-z0-9+\/=_-]{32,}$/', "{$key} looks like a token")
            ->not->toMatch('/:\/\/[^\/\s:@]+:[^\/\s@]+@/', "{$key} embeds credentials in a URL")
            ->not->toContain('-----BEGIN');
    }
});

test('every key the production env template sets, active or commented out, is one the application reads', function () {
    $keys = productionEnvExampleKeys();
    $read = environmentVariablesTheApplicationReads();

    $unknown = array_values(array_diff([...array_keys($keys['active']), ...$keys['commented']], $read));

    expect($unknown)->toBe([]);
});

test('the production env template reaches the configuration it is meant to change', function () {
    $env = productionEnvExampleKeys()['active'];
    $script = <<<'PHP'
require getenv('TEST_BASE').'/vendor/autoload.php';
$app = require getenv('TEST_BASE').'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'environment' => $app->environment(),
    'debug' => config('app.debug'),
    'url' => config('app.url'),
    'session_secure' => config('session.secure'),
    'csp_report_only' => config('secure-headers.csp.report-only'),
    'hsts' => config('secure-headers.hsts.enable'),
    'telescope' => config('telescope.enabled'),
    'mailer' => config('mail.default'),
    'database' => config('database.default'),
    'cache' => config('cache.default'),
    'queue' => config('queue.default'),
    'log_level' => config('logging.channels.daily.level'),
    'offsite' => config('backup.backup.destination.disks'),
]);
PHP;

    $process = new Process([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], base_path(), [
        ...array_map(fn ($value) => (string) $value, array_filter($env, fn ($value, $key) => $key !== 'APP_KEY' && ! str_contains((string) $value, '${'), ARRAY_FILTER_USE_BOTH)),
        'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'LOG_STACK' => 'daily',
        'TEST_BASE' => base_path(),
    ]);
    $process->mustRun();
    $config = json_decode($process->getOutput(), true);

    expect($config)->toMatchArray([
        'environment' => 'production',
        'debug' => false,
        'session_secure' => true,
        'csp_report_only' => false,
        'telescope' => false,
        'mailer' => 'smtp',
        'database' => 'mysql',
        'cache' => 'database',
        'queue' => 'database',
        'log_level' => 'warning',
    ])
        ->and($config['url'])->toStartWith('https://')
        ->and($config['offsite'])->toBe(['local', 's3']);
});

test('the production env template documents that there is no CSP report endpoint, and the README and checklist point to it', function () {
    $template = file_get_contents(base_path('.env.production.example'));

    expect($template)->toContain('no report endpoint')
        ->and($template)->toContain('browser console')
        ->and(file_get_contents(base_path('README.md')))->toContain('.env.production.example')
        ->and(file_get_contents(base_path('.ai/go-live-checklist-20261021.md')))->toContain('.env.production.example');
});

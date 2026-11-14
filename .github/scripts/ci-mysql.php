<?php

/*
 * Gives one CI job its own database on a MySQL server that several jobs share.
 *
 * The self-hosted runners on the team VPS (docs/self-hosted-runner.md) share
 * one native MySQL 8.4 service, and several runner instances can run the
 * `Pest (MySQL migrations)` job at the same time. Each job therefore creates,
 * migrates and drops a database of its own, named after the runner, the run
 * and the attempt, so concurrent jobs and re-runs never touch each other's
 * schema.
 *
 * The server and account come from the runner's environment, never from the
 * repository:
 *
 *   CI_MYSQL_HOST      host, default 127.0.0.1
 *   CI_MYSQL_PORT      port (required)
 *   CI_MYSQL_USER      account allowed to create and drop `whci\_%` databases (required)
 *   CI_MYSQL_PASSWORD  its password (required)
 *   CI_MYSQL_WAIT_SECONDS  how long `check` waits for the server, default 10
 *
 * Usage:
 *
 *   php .github/scripts/ci-mysql.php check   # reachable, MySQL 8.4, else fail naming the setting
 *   php .github/scripts/ci-mysql.php create  # create this job's database, export DB_* to the job
 *   php .github/scripts/ci-mysql.php drop    # drop it again; never fails the job
 *   php .github/scripts/ci-mysql.php name    # print the database name
 *
 * A runner runs one job at a time, so `create` first drops any database this
 * runner left behind (a job that was killed before its drop step ran).
 */

const REQUIRED_VERSION = '8.4';
const PREFIX = 'whci_';
const SETTINGS_HINT = 'Set CI_MYSQL_HOST, CI_MYSQL_PORT, CI_MYSQL_USER and CI_MYSQL_PASSWORD in the runner\'s environment (docs/self-hosted-runner.md).';

function setting(string $name, string $default = ''): string
{
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

function fail(string $message): never
{
    echo "::error::{$message}\n";
    exit(1);
}

/** The settings `check` and `create` need, or a failure naming each missing one. */
function settings(): array
{
    $missing = array_values(array_filter(
        ['CI_MYSQL_PORT', 'CI_MYSQL_USER', 'CI_MYSQL_PASSWORD'],
        fn (string $name): bool => setting($name) === '',
    ));

    if ($missing !== []) {
        fail(implode(', ', $missing).' '.(count($missing) === 1 ? 'is' : 'are').' not set on this runner, so the MySQL migration tests have no server. '.SETTINGS_HINT);
    }

    $port = setting('CI_MYSQL_PORT');

    if (! ctype_digit($port)) {
        fail("CI_MYSQL_PORT is '{$port}', not a port number. ".SETTINGS_HINT);
    }

    return [
        'host' => setting('CI_MYSQL_HOST', '127.0.0.1'),
        'port' => $port,
        'user' => setting('CI_MYSQL_USER'),
        'password' => setting('CI_MYSQL_PASSWORD'),
    ];
}

function connect(array $settings, int $waitSeconds): PDO
{
    $dsn = "mysql:host={$settings['host']};port={$settings['port']};charset=utf8mb4";
    $deadline = time() + $waitSeconds;

    while (true) {
        try {
            return new PDO($dsn, $settings['user'], $settings['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $exception) {
            if (time() >= $deadline) {
                fail("MySQL is unreachable at {$settings['host']}:{$settings['port']} as {$settings['user']} ({$exception->getMessage()}). Start the runner's MySQL service or correct CI_MYSQL_HOST, CI_MYSQL_PORT, CI_MYSQL_USER or CI_MYSQL_PASSWORD (docs/self-hosted-runner.md).");
            }

            sleep(2);
        }
    }
}

function check(): PDO
{
    $settings = settings();
    $pdo = connect($settings, (int) setting('CI_MYSQL_WAIT_SECONDS', '10'));
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

    if (! str_starts_with($version, REQUIRED_VERSION.'.')) {
        fail("MySQL {$version} answers at {$settings['host']}:{$settings['port']}; the migration tests run against MySQL ".REQUIRED_VERSION.' (docs/self-hosted-runner.md).');
    }

    echo "MySQL {$version} at {$settings['host']}:{$settings['port']} as {$settings['user']}.\n";

    return $pdo;
}

/** This runner's prefix: the runner name, readable but cut short, plus its checksum so two names never share one. */
function runnerPrefix(): string
{
    $runner = setting('RUNNER_NAME', 'local');
    $slug = substr(trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($runner)), '_'), 0, 20);

    return PREFIX.($slug === '' ? '' : $slug.'_').hash('crc32b', $runner).'_';
}

function databaseName(): string
{
    $run = setting('GITHUB_RUN_ID', (string) getmypid());
    $attempt = setting('GITHUB_RUN_ATTEMPT', '1');

    if (! ctype_digit($run) || ! ctype_digit($attempt)) {
        fail("GITHUB_RUN_ID '{$run}' and GITHUB_RUN_ATTEMPT '{$attempt}' must be numbers.");
    }

    $name = runnerPrefix().$run.'_'.$attempt;

    // MySQL's limit for a database name.
    if (strlen($name) > 64) {
        fail("The database name {$name} is longer than MySQL's 64 characters.");
    }

    return $name;
}

/** Appends KEY=value lines to a GitHub Actions command file, refusing values that would break its format. */
function export(string $variable, array $values): void
{
    $file = getenv($variable);

    if ($file === false || $file === '') {
        return;
    }

    $lines = '';

    foreach ($values as $key => $value) {
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            fail("{$key} contains a line break.");
        }

        $lines .= "{$key}={$value}\n";
    }

    file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);
}

function create(): void
{
    $pdo = check();
    $settings = settings();
    $name = databaseName();
    $pattern = str_replace('_', '\\_', runnerPrefix()).'%';

    foreach ($pdo->query('SHOW DATABASES LIKE '.$pdo->quote($pattern))->fetchAll(PDO::FETCH_COLUMN) as $stale) {
        if (preg_match('/^whci_[a-z0-9_]+$/', $stale) === 1) {
            echo "Dropping {$stale}, left behind by an earlier job on this runner.\n";
            $pdo->exec("DROP DATABASE IF EXISTS `{$stale}`");
        }
    }

    // Exported before the database exists, so the always-run drop step finds
    // the name even when the create below fails half way.
    echo "::add-mask::{$settings['password']}\n";
    export('GITHUB_ENV', ['CI_MYSQL_DATABASE' => $name]);

    $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    export('GITHUB_ENV', [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $settings['host'],
        'DB_PORT' => $settings['port'],
        'DB_DATABASE' => $name,
        'DB_USERNAME' => $settings['user'],
        'DB_PASSWORD' => $settings['password'],
    ]);
    export('GITHUB_OUTPUT', ['database' => $name]);

    echo "Created {$name}; later steps use it as DB_DATABASE.\n";
}

function drop(): void
{
    $name = setting('CI_MYSQL_DATABASE');

    if ($name === '') {
        echo "No database was created for this job.\n";

        return;
    }

    if (preg_match('/^whci_[a-z0-9_]+$/', $name) !== 1) {
        echo "::warning::Not dropping '{$name}': it is not a CI database name.\n";

        return;
    }

    // A failed drop must not fail a job whose tests passed: the next job on
    // this runner drops what is left (see create()).
    if (setting('CI_MYSQL_PORT') === '' || setting('CI_MYSQL_USER') === '' || setting('CI_MYSQL_PASSWORD') === '') {
        echo "::warning::Not dropping {$name}: the CI_MYSQL_* settings are gone. The next job on this runner drops it.\n";

        return;
    }

    try {
        $settings = settings();
        $pdo = new PDO("mysql:host={$settings['host']};port={$settings['port']};charset=utf8mb4", $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        echo "Dropped {$name}.\n";
    } catch (PDOException $exception) {
        echo "::warning::Could not drop {$name}: {$exception->getMessage()} The next job on this runner drops it.\n";
    }
}

match ($argv[1] ?? '') {
    'check' => check(),
    'create' => create(),
    'drop' => drop(),
    'name' => print(databaseName()."\n"),
    default => fail('usage: php .github/scripts/ci-mysql.php check|create|drop|name'),
};

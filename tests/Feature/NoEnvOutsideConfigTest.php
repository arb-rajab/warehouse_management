<?php

/**
 * Every deploy runs `config:cache`, after which env() outside config/ returns
 * null. The legacy store read its settings through env() at hundreds of sites,
 * so on a deployed host emails had no app name or sender, the S3 branches never
 * ran and role creation failed. These keys have a config equivalent now; a
 * read of one through env() is a bug that no test on an uncached config sees.
 *
 * Other keys the store still reads through env() (payment gateways, analytics
 * and captcha credentials, DEMO_MODE, FORCE_HTTPS, ...) are not listed yet.
 *
 * @var array<string, string> key => the config() call that replaces it
 */
const ENV_KEYS_WITH_A_CONFIG_EQUIVALENT = [
    'APP_NAME' => "config('app.name')",
    'APP_URL' => "config('app.url')",
    'MAIL_FROM_ADDRESS' => "config('mail.from.address')",
    'MAIL_FROM_NAME' => "config('mail.from.name')",
    'FILESYSTEM_DRIVER' => "config('filesystems.default')",
    'SESSION_DRIVER' => "config('session.driver')",
    'CACHE_DRIVER' => "config('cache.default')",
    'MAIL_DRIVER' => "config('mail.default')",
    'DEFAULT_LANGUAGE' => "config('app.default_language')",
];

test('nothing outside config reads a key that has a config equivalent through env()', function () {
    $offenders = [];
    $pattern = '/\benv\(\s*[\'"]('.implode('|', array_keys(ENV_KEYS_WITH_A_CONFIG_EQUIVALENT)).')[\'"]/';

    foreach (sourceFilesUnder(['app', 'bootstrap', 'routes', 'resources/views', 'database/migrations']) as $path => $source) {
        if (preg_match_all($pattern, $source, $matches)) {
            foreach (array_unique($matches[1]) as $key) {
                $offenders[] = "$path: env('$key') -> ".ENV_KEYS_WITH_A_CONFIG_EQUIVALENT[$key];
            }
        }
    }

    expect($offenders)->toBe([]);
});

<?php

/**
 * The legacy store keys its default-language rows (translations, the language
 * switcher, the translate() fallback) on one language code. It was read with
 * env('DEFAULT_LANGUAGE') at 66 sites, which is null once `config:cache` has
 * run, as it does on every deploy. See config/app.php's `default_language`.
 */
test('the default content language is en unless DEFAULT_LANGUAGE says otherwise', function () {
    expect(config('app.default_language'))->toBe('en')
        ->and(default_language())->toBe('en');
});

test('nothing outside config reads DEFAULT_LANGUAGE through env()', function () {
    $offenders = [];

    foreach (['app', 'routes', 'resources/views', 'database'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && preg_match('/env\(\s*[\'"]DEFAULT_LANGUAGE[\'"]/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});

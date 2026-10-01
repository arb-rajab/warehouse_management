<?php

/**
 * The legacy store keys its default-language rows (translations, the language
 * switcher, the translate() fallback) on one language code. It was read with
 * env('DEFAULT_LANGUAGE') at 66 sites, which is null once `config:cache` has
 * run, as it does on every deploy. See config/app.php's `default_language`.
 * NoEnvOutsideConfigTest keeps those reads out.
 */
test('the default content language is en unless DEFAULT_LANGUAGE says otherwise', function () {
    expect(config('app.default_language'))->toBe('en')
        ->and(default_language())->toBe('en');
});

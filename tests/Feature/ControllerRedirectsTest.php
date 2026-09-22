<?php

use Symfony\Component\Finder\Finder;

/**
 * Regression guard for the admin-table-redirect bug: `redirect()->back()`
 * depends on session/Referer state that Inertia + `config/secure-headers.php`
 * make unreliable (see `StoreInertiaPreviousUrl`'s docblock), so every
 * mutating controller action must redirect via an explicit route instead —
 * `Controller::redirectPreservingQuery()` for the common case, or an explicit
 * `return_to`-based branch like `RedirectsAfterCellAction`. See
 * .ai/rules/controllers.md.
 */
test('no controller redirects with back(), other than the intentional LocaleController exception', function () {
    $offenders = [];

    $finder = (new Finder())
        ->files()
        ->in(app_path('Http/Controllers'))
        ->name('*.php')
        // LocaleController's back() is intentional: switching locale is fired
        // from every page in the app, not from one fixed index route, so
        // there's no explicit route to redirect to instead.
        ->notName('LocaleController.php');

    foreach ($finder as $file) {
        if (preg_match('/->\s*back\s*\(/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

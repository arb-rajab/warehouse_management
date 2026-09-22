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
    // Whether the source's actual PHP tokens contain a `->back(` method
    // call — a plain text/regex scan would also match the literal string
    // inside a docblock explaining why `back()` must not be used, like the
    // one above.
    $callsBack = function (string $source): bool {
        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            $afterNext = $tokens[$index + 2] ?? null;

            if (is_array($next) && $next[0] === T_STRING && $next[1] === 'back' && $afterNext === '(') {
                return true;
            }
        }

        return false;
    };

    $finder = new Finder()
        ->files()
        ->in(app_path('Http/Controllers'))
        ->name('*.php')
        // LocaleController's back() is intentional: switching locale is fired
        // from every page in the app, not from one fixed index route, so
        // there's no explicit route to redirect to instead.
        ->notName('LocaleController.php');

    $offenders = [];

    foreach ($finder as $file) {
        if ($callsBack($file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

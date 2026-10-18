<?php

use App\Http\Controllers\Store\Admin\VisitController;
use Symfony\Component\Finder\Finder;

/**
 * Regression guard for the admin-table-redirect bug: `redirect()->back()`
 * depends on session/Referer state that Inertia + `config/secure-headers.php`
 * make unreliable (see `StoreInertiaPreviousUrl`'s docblock), so every
 * mutating controller action must redirect via an explicit route instead —
 * `Controller::redirectPreservingQuery()` for the common case, or an explicit
 * route like `RedirectsAfterCellAction`. See
 * .ai/rules/controllers.md.
 */
test('no controller redirects with back(), other than locale switching and the representative visit note writer', function () {
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
        ->notName('LocaleController.php')
        // The legacy store's root-namespace controllers (see
        // .ai/rules/legacy-albaraka-holland.md) render plain Blade views,
        // not Inertia pages, so this test's whole premise — Referer being
        // stripped and Inertia treating every visit as XHR — doesn't apply
        // to them. notPath() (not notName()) because several of these
        // basenames collide with an unrelated, in-scope Inertia controller
        // of the same name under Api/V2/ or Seller/ (e.g. CartController,
        // CustomerController) — excluding by name alone would hide a real
        // violation in the wrong one.
        ->notPath([
            '#^CartController\.php$#',
            '#^CartOfferedController\.php$#',
            '#^CustomerController\.php$#',
            '#^ProductQueryController\.php$#',
            '#^RepresentativePackageController\.php$#',
            '#^RepresentativePaymentController\.php$#',
        ]);

    $offenders = [];

    foreach ($finder as $file) {
        $source = $file->getContents();
        if ($file->getRelativePathname() === 'Store/Admin/VisitController.php') {
            // The note writer serves the retained storefront and the visit
            // detail; step 31 explicitly preserves a redirect back for both.
            $method = new ReflectionMethod(VisitController::class, 'update_note');
            $lines = explode("\n", $source);
            array_splice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
            $source = implode("\n", $lines);
        }
        if ($callsBack($source)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

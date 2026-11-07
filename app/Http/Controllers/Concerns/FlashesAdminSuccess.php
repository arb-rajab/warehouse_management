<?php

namespace App\Http\Controllers\Concerns;

use Inertia\Inertia;

/**
 * The success message every WMS admin action that changes data and redirects
 * sends (.ai/rules/success-messages.md): the layout's SuccessBanner shows the
 * `success` flash on the page the redirect lands on. The WMS twin of the store
 * back office's FlashesSuccess, reading `lang/{en,ar}/admin.php`.
 */
trait FlashesAdminSuccess
{
    /**
     * Flash `admin.<key>` as the success message. Call it only once the work
     * has succeeded, just before the redirect, never on a failure path.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function flashAdminSuccess(string $key, array $replace = []): void
    {
        Inertia::flash('success', __('admin.'.$key, $replace));
    }
}

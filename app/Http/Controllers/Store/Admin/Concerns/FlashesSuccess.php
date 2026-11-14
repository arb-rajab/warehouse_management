<?php

namespace App\Http\Controllers\Store\Admin\Concerns;

use Inertia\Inertia;

/**
 * The success message every store back-office action that changes data and
 * redirects sends (.ai/rules/success-messages.md): the layout's SuccessBanner
 * shows the `success` flash on the page the redirect lands on.
 */
trait FlashesSuccess
{
    /**
     * Flash `store_admin.<key>` as the success message. Call it only once the
     * work has succeeded, just before the redirect, never on a failure path.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function flashSuccess(string $key, array $replace = []): void
    {
        Inertia::flash('success', __('store_admin.'.$key, $replace));
    }
}

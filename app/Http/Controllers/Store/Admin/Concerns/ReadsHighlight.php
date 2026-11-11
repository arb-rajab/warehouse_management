<?php

namespace App\Http\Controllers\Store\Admin\Concerns;

use Illuminate\Http\Request;

/**
 * The `?highlight=<key>` a deep link opens a settings page with, so the page
 * marks and scrolls to that option (.ai/rules/deep-link-highlight.md). Only a
 * key the page itself holds is passed on; anything else, an array included,
 * becomes null. The raw query value is never echoed.
 */
trait ReadsHighlight
{
    /** @param  list<string>  $keys  the keys this page can mark */
    protected function highlight(Request $request, array $keys): ?string
    {
        $highlight = $request->query('highlight');

        return is_string($highlight) && in_array($highlight, $keys, true) ? $highlight : null;
    }
}

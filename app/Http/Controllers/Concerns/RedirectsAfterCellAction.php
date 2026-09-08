<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared redirect target for the admin actions that operate on one cell from
 * two different pages (the cell map and a single row's page) — pallet actions
 * and toggle-active.
 *
 * The origin can't be inferred server-side: the `Referer` header is stripped
 * app-wide by `config/secure-headers.php`'s `Referrer-Policy: no-referrer`,
 * and the session-tracked previous URL `back()` relies on doesn't help either,
 * since Inertia's client marks every visit (including full page-to-page
 * navigation) as an XHR request, which stops Laravel's session middleware from
 * ever updating it during normal SPA use — it stays frozen at whatever the last
 * true full browser page load was. So the frontend sends an explicit
 * `return_to` field instead, validated against a fixed `in:cells,row` list by
 * `ValidatesReturnTo`. It only ever selects between two known, hardcoded
 * destinations, and the row redirected to is derived from the actual cell the
 * action operated on, never from request input, so this can't be used to
 * redirect somewhere unrelated.
 */
trait RedirectsAfterCellAction
{
    protected function redirectAfterCellAction(Request $request, Cell $cell): RedirectResponse
    {
        if ($request->input('return_to') === 'row') {
            return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
        }

        return redirect()->route('admin.cells.index', $request->query());
    }
}

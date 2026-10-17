<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use Illuminate\Http\RedirectResponse;

/**
 * Shared redirect target for the admin actions that operate on one cell — the
 * pallet actions and toggle-active, all triggered from that cell's row page.
 * The row redirected to is derived from the cell the action operated on, never
 * from request input.
 */
trait RedirectsAfterCellAction
{
    protected function redirectAfterCellAction(Cell $cell): RedirectResponse
    {
        return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
    }
}

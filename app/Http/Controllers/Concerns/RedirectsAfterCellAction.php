<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cell;
use App\Models\Row;
use Illuminate\Http\RedirectResponse;

/**
 * Shared redirect target for the admin actions that operate on one cell — the
 * pallet actions and toggle-active, all triggered from that cell's row page.
 * The row redirected to is derived from the cell the action operated on, never
 * from request input. The row is read afresh (not through a possibly
 * narrower eager load) for its warehouse, which {@see Row::routeParameters()}
 * adds when another warehouse uses the same letter.
 */
trait RedirectsAfterCellAction
{
    protected function redirectAfterCellAction(Cell $cell): RedirectResponse
    {
        $row = Row::query()->select(['id', 'warehouse_id', 'letter'])->findOrFail($cell->row_id);

        return redirect()->route('admin.rows.show', $row->routeParameters());
    }
}

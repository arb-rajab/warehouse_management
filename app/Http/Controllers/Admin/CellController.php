<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellLogAction;
use App\Http\Controllers\Concerns\BuildsCellQrLabels;
use App\Http\Controllers\Concerns\RedirectsAfterCellAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ToggleCellActiveRequest;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;

class CellController extends Controller
{
    use BuildsCellQrLabels, RedirectsAfterCellAction;

    public function exportQrCode(Cell $cell): HttpResponse
    {
        $cell->loadMissing('row:id,letter,flats_count');
        $setting = Setting::current();

        return response($this->cellQrLabelImage($cell->row->letter, $cell, $setting->qr_code_width, $setting->qr_code_height, $cell->row->flats_count))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"cell-{$cell->row->letter}{$cell->cell_number}-{$cell->flat_number}-qr.svg\"");
    }

    /**
     * Toggle a cell's active status — deactivating flags it as corrupted/out of
     * service (regardless of its current occupancy), reactivating restores it.
     * Occupancy (`state`) is never touched by this action.
     *
     * Redirects back to the cell's row page — see `RedirectsAfterCellAction`,
     * shared with the pallet actions.
     */
    public function toggleActive(ToggleCellActiveRequest $request, Cell $cell): RedirectResponse
    {
        return DB::transaction(function () use ($request, $cell) {
            /** @var Cell $lockedCell */
            $lockedCell = Cell::query()->with('pallet:id,cell_id,product_id')->lockForUpdate()->findOrFail($cell->id);

            $activating = ! $lockedCell->is_active;
            $lockedCell->update(['is_active' => $activating]);

            CellStatusLog::create([
                'cell_id' => $lockedCell->id,
                'related_cell_id' => null,
                'action' => $activating ? CellLogAction::Reactivated : CellLogAction::Deactivated,
                'from_state' => $lockedCell->state,
                'to_state' => $lockedCell->state,
                'product_id' => $lockedCell->pallet?->product_id,
                'pallet_id' => $lockedCell->pallet?->id,
                'boxes_count' => null,
                'user_id' => $request->user()->id,
                'note' => $request->input('note'),
            ]);

            return $this->redirectAfterCellAction($lockedCell);
        });
    }
}

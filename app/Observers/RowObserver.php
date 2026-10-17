<?php

namespace App\Observers;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Row;
use App\Services\DashboardStatsCache;
use Illuminate\Support\Facades\DB;

class RowObserver
{
    /**
     * Handle the Row "created" event by generating all of its empty cells.
     */
    public function created(Row $row): void
    {
        $this->generateCells($row);

        DashboardStatsCache::flush();
    }

    /**
     * Handle the Row "updated" event by adding or removing cells so the grid
     * matches the new dimensions. Cells inside both the old and new bounds are
     * kept (with their pallets and history).
     *
     * Callers must guarantee the cells being removed hold no pallets and no
     * history (see Row::hasPalletsBeyond()/hasHistoryBeyond()) — deleting a
     * cell with status logs or verification reports against it fails the
     * `restrictOnDelete` constraint on those tables.
     *
     * The delete and the insert run in one transaction (a savepoint when,
     * as from RowController, the caller already holds one).
     */
    public function updated(Row $row): void
    {
        if (! $row->wasChanged(['cells_count', 'flats_count'])) {
            return;
        }

        $oldCells = (int) ($row->getPrevious()['cells_count'] ?? $row->cells_count);
        $oldFlats = (int) ($row->getPrevious()['flats_count'] ?? $row->flats_count);

        DB::transaction(function () use ($row, $oldCells, $oldFlats) {
            $row->cells()
                ->where(fn ($query) => $query
                    ->where('cell_number', '>', $row->cells_count)
                    ->orWhere('flat_number', '>', $row->flats_count))
                ->delete();

            $this->generateCells($row, $oldCells, $oldFlats);
        });

        DashboardStatsCache::flush();
    }

    /**
     * Handle the Row "deleted" event — its cells (all Empty and history-free;
     * deletion is blocked while any hold a pallet or have status log/
     * verification history, see RowController::destroy) go with it, which
     * changes the dashboard's occupancy.empty count.
     */
    public function deleted(Row $row): void
    {
        DashboardStatsCache::flush();
    }

    /**
     * Generate the row's empty cells for its current dimensions that lie
     * outside the previous `$existingCells` x `$existingFlats` grid (all of
     * them for a new row).
     */
    private function generateCells(Row $row, int $existingCells = 0, int $existingFlats = 0): void
    {
        $now = now();
        $cells = [];

        for ($cellNumber = 1; $cellNumber <= $row->cells_count; $cellNumber++) {
            for ($flatNumber = 1; $flatNumber <= $row->flats_count; $flatNumber++) {
                if ($cellNumber <= $existingCells && $flatNumber <= $existingFlats) {
                    continue;
                }

                $cells[] = [
                    'row_id' => $row->id,
                    'cell_number' => $cellNumber,
                    'flat_number' => $flatNumber,
                    'state' => CellState::Empty->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($cells !== []) {
            Cell::insert($cells);
        }
    }
}

<?php

namespace App\Observers;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Row;
use App\Services\DashboardStatsCache;

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
     * Handle the Row "updated" event by regenerating its cells when the layout changed.
     *
     * Callers must guarantee the row has no pallets and no history (see
     * Row::hasPallets()/hasHistory()) before changing its dimensions, since
     * regenerating cells discards the previous ones — deleting a cell with
     * status logs or verification reports against it fails the
     * `restrictOnDelete` constraint on those tables.
     */
    public function updated(Row $row): void
    {
        if (! $row->wasChanged(['cells_count', 'flats_count'])) {
            return;
        }

        $row->cells()->delete();

        $this->generateCells($row);

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
     * Generate all of a row's empty cells for its current dimensions.
     */
    private function generateCells(Row $row): void
    {
        $now = now();
        $cells = [];

        for ($cellNumber = 1; $cellNumber <= $row->cells_count; $cellNumber++) {
            for ($flatNumber = 1; $flatNumber <= $row->flats_count; $flatNumber++) {
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

        Cell::insert($cells);
    }
}

<?php

namespace App\Observers;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Row;

class RowObserver
{
    /**
     * Handle the Row "created" event by generating all of its empty cells.
     */
    public function created(Row $row): void
    {
        $this->generateCells($row);
    }

    /**
     * Handle the Row "updated" event by regenerating its cells when the layout changed.
     *
     * Callers must guarantee the row has no pallets before changing its dimensions,
     * since regenerating cells discards the previous ones.
     */
    public function updated(Row $row): void
    {
        if (! $row->wasChanged(['cells_count', 'flats_count'])) {
            return;
        }

        $row->cells()->delete();

        $this->generateCells($row);
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

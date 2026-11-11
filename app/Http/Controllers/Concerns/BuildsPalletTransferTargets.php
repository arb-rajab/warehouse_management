<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

/**
 * The destinations an admin pallet transfer can pick from: every warehouse and
 * every row of every warehouse, so a pallet can move to a cell in another
 * warehouse. The transfer dialog lists the rows of the chosen warehouse and
 * posts that warehouse's id with the row letter, since a letter names one row
 * per warehouse only. Shared by the row page and the warehouse map.
 */
trait BuildsPalletTransferTargets
{
    /**
     * @return array{warehouses: Collection<int, Warehouse>, rows: Collection<int, Row>}
     */
    protected function palletTransferTargets(): array
    {
        return [
            'warehouses' => Warehouse::query()->select(['id', 'name'])->orderBy('name')->get(),
            'rows' => Row::query()->select(Row::SELECT_COLUMNS)->orderBy('letter')->orderBy('warehouse_id')->get(),
        ];
    }
}

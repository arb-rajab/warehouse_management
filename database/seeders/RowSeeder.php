<?php

namespace Database\Seeders;

use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class RowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creating each row fires RowObserver::created(), which generates its
     * cells. Every row goes in the "Default" warehouse.
     */
    public function run(): void
    {
        $warehouseId = Warehouse::firstOrCreateDefault()->id;

        Row::firstOrCreate(['warehouse_id' => $warehouseId, 'letter' => 'A'], ['cells_count' => 20, 'flats_count' => 4]);
        Row::firstOrCreate(['warehouse_id' => $warehouseId, 'letter' => 'B'], ['cells_count' => 15, 'flats_count' => 3]);
        Row::firstOrCreate(['warehouse_id' => $warehouseId, 'letter' => 'C'], ['cells_count' => 10, 'flats_count' => 5]);
        Row::firstOrCreate(['warehouse_id' => $warehouseId, 'letter' => 'AA'], ['cells_count' => 8, 'flats_count' => 2]);
    }
}

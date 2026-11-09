<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The "Default" warehouse (which RowSeeder fills) and an empty second
     * one, so the warehouse filters have more than one option locally.
     */
    public function run(): void
    {
        Warehouse::firstOrCreateDefault();
        Warehouse::query()->firstOrCreate(['name' => 'Annex'], ['code' => 'ANX', 'address' => 'Industrieweg 12, Rotterdam']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Row;
use Illuminate\Database\Seeder;

class RowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creating each row fires RowObserver::created(), which generates its cells.
     */
    public function run(): void
    {
        Row::create(['letter' => 'A', 'cells_count' => 20, 'flats_count' => 4]);
        Row::create(['letter' => 'B', 'cells_count' => 15, 'flats_count' => 3]);
        Row::create(['letter' => 'C', 'cells_count' => 10, 'flats_count' => 5]);
        Row::create(['letter' => 'AA', 'cells_count' => 8, 'flats_count' => 2]);
    }
}

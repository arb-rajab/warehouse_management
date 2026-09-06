<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            ProductSeeder::class,
            RowSeeder::class,
            PalletSeeder::class,
            CellStatusLogSeeder::class,
            CellVerificationRoundSeeder::class,
            CellVerificationReportSeeder::class,
        ]);
    }
}

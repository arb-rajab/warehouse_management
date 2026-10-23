<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\RefusesProduction;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use RefusesProduction;

    /**
     * Seed the application's database.
     *
     * Refuses to run in production: this chain creates known-password admin
     * accounts (test@example.com / password for the warehouse panel,
     * store-admin@example.com / password for the store back office) and demo
     * pallet/warehouse data, none of which belongs in live data.
     */
    public function run(): void
    {
        $this->refuseInProduction();

        User::factory()->create([
            'name' => 'Test User',
            'email' => User::query()->where('email', 'test@example.com')->exists()
                ? fake()->unique()->safeEmail()
                : 'test@example.com',
        ]);

        $this->call([
            ProductSeeder::class,
            RowSeeder::class,
            PalletSeeder::class,
            CellStatusLogSeeder::class,
            CellVerificationRoundSeeder::class,
            CellVerificationReportSeeder::class,
            PageSeeder::class,
            StoreRoleSeeder::class,
            StoreUserSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Refuses to run in production: this chain creates a known-password admin
     * account (test@example.com / password) and demo pallet/warehouse data,
     * neither of which belongs in live warehouse data.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Refusing to run database seeders in production.');
        }

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
        ]);
    }
}

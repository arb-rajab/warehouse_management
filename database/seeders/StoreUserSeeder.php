<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StoreUser;
use Illuminate\Database\Seeder;

class StoreUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * A store admin (store-admin@example.com / password) holding Super Admin,
     * to sign in to the store back office under /temp/admin, the way
     * test@example.com signs in to the warehouse panel. Needs StoreRoleSeeder.
     */
    public function run(): void
    {
        $superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->first();

        if ($superAdmin === null) {
            return;
        }

        $admin = StoreUser::query()->firstOrCreate(
            ['email' => 'store-admin@example.com'],
            ['name' => 'Store Admin', 'password' => 'password'],
        );
        $admin->forceFill(['user_type' => 'admin', 'email_verified_at' => now()])->save();
        $admin->assignRole($superAdmin);
    }
}

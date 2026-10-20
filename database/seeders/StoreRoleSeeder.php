<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\StorePermissions;
use Illuminate\Database\Seeder;

class StoreRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The store back office's Super Admin role, which Gate::before() grants
     * every ability, and one ordinary staff role to give a staff member.
     * Also re-runs the store permission sync (no grants), so a database
     * migrated before the list last grew still gets every row.
     *
     * Clerk is a demo role for local and test databases only; production
     * gets Super Admin alone and builds its staff roles in the app.
     */
    public function run(): void
    {
        StorePermissions::sync();

        Role::findOrCreate(Role::SUPER_ADMIN, 'store');

        if (app()->environment('local', 'testing')) {
            Role::findOrCreate('Clerk', 'store');
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class StoreRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The store back office's Super Admin role, which Gate::before() grants
     * every ability, and one ordinary staff role to give a staff member.
     */
    public function run(): void
    {
        Role::findOrCreate(Role::SUPER_ADMIN, 'store');
        Role::findOrCreate('Clerk', 'store');
    }
}

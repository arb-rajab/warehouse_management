<?php

namespace Database\Seeders;

use App\Models\StoreUser;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class StoreAccountFilterSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        StoreFilterSeedData::guard();
        $data = app(StoreFilterSeedData::class);
        $admin = $data->user('admin');
        foreach (['customer', 'representative', 'seller', 'driver', 'staff'] as $key) {
            $data->user($key);
        }
        $data->user('customer', ['company_name' => 'Filter coverage company', 'AccSysID' => 987654321, 'rep_id' => $data->user('representative')]);
        $data->user('seller-unverified', ['user_type' => 'seller']);
        foreach ([false, true] as $representative) {
            $prefix = $representative ? 'rep' : 'customer';
            foreach ([0, 1] as $flag) {
                foreach ([0, 1] as $archived) {
                    foreach (['new_user', 'has_been_contacted'] as $activity) {
                        $data->user("{$prefix}-{$flag}-{$archived}-{$activity}", [
                            'is_rep' => (int) $representative,
                            'is_important' => 1,
                            'admin_verified' => $flag,
                            'from_api' => $flag,
                            'registration_completed' => $flag,
                            'is_delete' => $archived ? 1 : null,
                            'activity_status' => $activity,
                            'rep_id' => $representative ? null : $data->user('representative'),
                        ]);
                    }
                }
            }
        }
        $data->user('customer-unimportant', ['is_important' => 0]);
        $data->user('representative-unimportant', ['is_rep' => 1, 'is_important' => 0]);
        $role = $data->row('wms_roles', 'fixture-admin-role', ['name' => 'Store Filter Fixture Administrator', 'guard_name' => 'store']);
        $permissions = DB::table('wms_permissions')->where('guard_name', 'store')->pluck('id');
        foreach ($permissions as $permission) {
            DB::table('wms_role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        DB::table('wms_model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => StoreUser::class, 'model_id' => $admin]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

<?php

namespace Database\Seeders;

use App\Models\StoreUser;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Database\Seeders\Support\StoreSeedExamples;
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
        $data->batch(function () use ($data): void {
            $admin = $data->user('admin');
            foreach (['customer', 'representative', 'seller', 'driver', 'staff'] as $key) {
                $data->user($key);
            }
            $data->user('customer', ['company_name' => StoreSeedExamples::company('customer'), 'AccSysID' => 987654321, 'rep_id' => $data->user('representative')]);
            foreach ([1 => 'seller', 0 => 'seller-unverified'] as $verified => $prefix) {
                for ($index = 0; $index < StoreSeedExamples::ADMIN_ROWS; $index++) {
                    $key = $index === 0 ? $prefix : $prefix.'-'.$index;
                    $data->user($key, ['user_type' => 'seller', 'company_name' => StoreSeedExamples::company($key)]);
                }
            }
            foreach ([false, true] as $representative) {
                $prefix = $representative ? 'rep' : 'customer';
                foreach ([0, 1] as $flag) {
                    foreach ([0, 1] as $archived) {
                        foreach (['new_user', 'has_been_contacted'] as $activity) {
                            for ($index = 0; $index < 11; $index++) {
                                $key = "{$prefix}-{$flag}-{$archived}-{$activity}".($index === 0 ? '' : '-'.$index);
                                $data->user($key, [
                                    'company_name' => StoreSeedExamples::company($key),
                                    'is_rep' => (int) $representative, 'is_important' => 1,
                                    'admin_verified' => $flag, 'from_api' => $flag,
                                    'registration_completed' => $flag, 'is_delete' => $archived ? 1 : null,
                                    'activity_status' => $activity,
                                    'rep_id' => $representative ? null : $data->user('representative'),
                                ]);
                            }
                        }
                    }
                }
                for ($index = 0; $index < StoreSeedExamples::ADMIN_ROWS; $index++) {
                    $key = ($representative ? 'representative' : 'customer').'-unimportant'.($index === 0 ? '' : '-'.$index);
                    $data->user($key, ['is_rep' => (int) $representative, 'is_important' => 0]);
                }
            }
            $role = $data->row('wms_roles', 'fixture-admin-role', ['name' => 'Demonstration Store Administrator', 'guard_name' => 'store']);
            $permissions = DB::table('wms_permissions')->where('guard_name', 'store')->pluck('id');
            foreach ($permissions as $permission) {
                DB::table('wms_role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
            }
            DB::table('wms_model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => StoreUser::class, 'model_id' => $admin]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}

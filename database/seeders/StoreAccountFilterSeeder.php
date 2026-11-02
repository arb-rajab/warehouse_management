<?php

namespace Database\Seeders;

use App\Models\StoreUser;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Database\Seeders\Support\StoreSeedExamples;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $this->completeCustomerAddresses($data);
        });
    }

    /**
     * Gives every current customer a usable delivery address: a customer with
     * none gets one default address, and an address missing its street,
     * country, phone or postal code has only those blanks filled. A value that
     * is already there is never overwritten, so real addresses stay as they are
     * and a second run changes nothing.
     */
    private function completeCustomerAddresses(StoreFilterSeedData $data): void
    {
        $country = DB::table('countries')->where('code', 'NL')->value('id')
            ?? DB::table('countries')->where('status', 1)->orderBy('id')->value('id')
            ?? $data->row('countries', 'account-country', ['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
        $state = Schema::hasTable('states') ? DB::table('states')->where('country_id', $country)->orderBy('id')->value('id') : null;
        $city = $state !== null && Schema::hasTable('cities') ? DB::table('cities')->where('state_id', $state)->orderBy('id')->value('id') : null;
        $streets = ['Damrak 1', 'Westhavenweg 45', 'Prinsengracht 2', 'Keizersgracht 120', 'Kinkerstraat 88', 'Javastraat 14', 'Ceintuurbaan 30', 'Haarlemmerstraat 61'];

        foreach (DB::table('users')->where('user_type', 'customer')->orderBy('id')->pluck('id') as $customer) {
            $number = StoreSeedExamples::number("address-$customer");
            $blanks = [
                'address' => $streets[$number % count($streets)],
                'country_id' => $country,
                'postal_code' => sprintf('10%02d%s', $number % 90 + 10, ['AB', 'CD', 'EF', 'GH'][$number % 4]),
                'phone' => '+3120555'.sprintf('%04d', $number % 10000),
            ];
            $addresses = DB::table('addresses')->where('user_id', $customer)->orderBy('id')->get();

            if ($addresses->isEmpty()) {
                $data->row('addresses', "customer-address-$customer", [
                    'user_id' => $customer, ...$blanks, 'state_id' => $state ?? 0, 'city_id' => $city, 'set_default' => 1,
                ]);

                continue;
            }

            foreach ($addresses as $address) {
                $fill = [];
                foreach ($blanks as $column => $value) {
                    if ($address->{$column} === null || trim((string) $address->{$column}) === '' || $address->{$column} === 0) {
                        $fill[$column] = $value;
                    }
                }
                if ($fill !== []) {
                    DB::table('addresses')->where('id', $address->id)->update($fill);
                }
            }
            if (! $addresses->contains('set_default', 1)) {
                DB::table('addresses')->where('id', $addresses->first()->id)->update(['set_default' => 1]);
            }
        }
    }
}

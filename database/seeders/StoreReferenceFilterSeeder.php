<?php

namespace Database\Seeders;

use App\Models\StoreUser;
use App\Notifications\UnorderedCartNotification;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/** Reference-list options and searchable baselines, without changing existing setup. */
class StoreReferenceFilterSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        StoreFilterSeedData::guard();
        $data = app(StoreFilterSeedData::class);
        DB::transaction(function () use ($data): void {
            $this->geography($data);
            $this->baselines($data);
            $this->reviews($data);
            $this->staffAndUploads($data);
        });
    }

    private function geography(StoreFilterSeedData $data): void
    {
        $zone = DB::table('zones')->orderBy('id')->value('id') ?? $data->row('zones', 'reference-zone', ['name' => 'Filter coverage zone', 'status' => 1]);
        if (! DB::table('countries')->where('status', 1)->exists()) {
            $data->row('countries', 'reference-country', ['name' => 'Filter coverage country', 'code' => 'ZZ', 'zone_id' => $zone, 'status' => 1]);
        }
        foreach (DB::table('countries')->where('status', 1)->orderBy('id')->get(['id']) as $country) {
            if (! DB::table('states')->where('country_id', $country->id)->exists()) {
                $data->row('states', 'reference-state-'.$country->id, ['name' => 'Filter coverage state '.$country->id, 'country_id' => $country->id, 'status' => 1]);
            }
        }
        foreach (DB::table('states')->where('status', 1)->orderBy('id')->get(['id']) as $state) {
            if (! DB::table('cities')->where('state_id', $state->id)->exists()) {
                $data->row('cities', 'reference-city-'.$state->id, ['name' => 'Filter coverage city '.$state->id, 'state_id' => $state->id, 'status' => 1, 'cost' => 2.5]);
            }
        }
    }

    private function baselines(StoreFilterSeedData $data): void
    {
        foreach ([
            'currencies' => ['name' => 'Filter coverage currency', 'symbol' => '¤', 'code' => 'XXX', 'exchange_rate' => 1, 'status' => 1],
            'taxes' => ['name' => 'Filter coverage tax', 'tax_status' => 1],
            'carriers' => ['name' => 'Filter coverage carrier', 'transit_time' => '2–3 days', 'free_shipping' => 0, 'status' => 1],
            'customer_packages' => ['name' => 'Filter coverage package', 'amount' => 10, 'product_upload' => 5],
            'pages' => ['type' => 'custom_page', 'title' => 'Filter coverage page', 'slug' => 'store-filter-coverage-page', 'content' => '<p>Development and staging example.</p>'],
            'brands' => ['name' => 'Filter coverage brand', 'slug' => 'store-filter-coverage-brand'],
            'languages' => ['name' => 'English', 'code' => 'en', 'app_lang_code' => 'en', 'rtl' => 0, 'status' => 1],
        ] as $table => $values) {
            if (! DB::table($table)->exists()) {
                $data->row($table, 'reference-baseline', $values);
            }
        }
        foreach (['translations', 'app_translations'] as $table) {
            if (! DB::table($table)->where('lang', 'en')->exists()) {
                $data->row($table, 'reference-source', ['lang' => 'en', 'lang_key' => 'store_filter_coverage_example', 'lang_value' => 'Filter coverage example']);
            }
        }
    }

    private function reviews(StoreFilterSeedData $data): void
    {
        if ($data->find('products', 'reference-reviewed-product') === null && DB::table('reviews')->join('products', 'products.id', '=', 'reviews.product_id')->join('users', 'users.id', '=', 'reviews.user_id')->exists()) {
            return;
        }
        $product = $data->row('products', 'reference-reviewed-product', [
            'name' => 'Filter coverage reviewed product', 'ar_name' => '', 'slug' => 'store-filter-coverage-reviewed-product',
            'added_by' => 'admin', 'user_id' => $data->user('admin'), 'published' => 1, 'approved' => 1,
            'category_id' => DB::table('categories')->orderBy('id')->value('id'),
            'unit_price' => 50, 'rating' => 3,
        ]);
        foreach ([1, 5] as $rating) {
            $data->row('reviews', 'reference-review-'.$rating, [
                'product_id' => $product, 'user_id' => $data->user('customer'), 'rating' => $rating,
                'comment' => 'Filter coverage rating '.$rating, 'status' => 1, 'viewed' => 0,
            ]);
        }
    }

    private function staffAndUploads(StoreFilterSeedData $data): void
    {
        $admin = $data->user('admin');
        $data->row('notifications', 'reference-admin-notification', [
            'id' => 'da73dd02-5858-4ee9-a88b-2388fdc5464f',
            'type' => UnorderedCartNotification::class,
            'notifiable_type' => StoreUser::class, 'notifiable_id' => $admin,
            'data' => json_encode(['user_id' => $data->user('customer'), 'user_name' => 'Filter fixture customer', 'message' => 'Filter coverage notification; no message sent.'], JSON_THROW_ON_ERROR),
        ]);
        $staff = $data->user('staff');
        $role = $data->row('wms_roles', 'reference-staff-role', ['name' => 'Store Filter Fixture Staff', 'guard_name' => 'store']);
        $data->row('staff', 'reference-staff', ['user_id' => $staff, 'role_id' => $role]);
        foreach (DB::table('wms_permissions')->where('guard_name', 'store')->where('name', 'view_uploads')->pluck('id') as $permission) {
            DB::table('wms_role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        DB::table('wms_model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => StoreUser::class, 'model_id' => $staff]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['image' => 'png', 'video' => 'mp4', 'audio' => 'wav', 'archive' => 'zip', 'document' => 'pdf'] as $type => $extension) {
            foreach ([$data->user('admin'), $staff] as $owner) {
                // Metadata fixtures intentionally have no file URL or downloadable binary.
                $data->row('uploads', 'reference-upload-'.$type.'-'.$owner, [
                    'user_id' => $owner, 'file_original_name' => 'Filter coverage '.$type,
                    'type' => $type, 'extension' => $extension, 'file_size' => strlen($type) * 100,
                    'file_name' => null, 'external_link' => null,
                ]);
            }
        }
    }
}

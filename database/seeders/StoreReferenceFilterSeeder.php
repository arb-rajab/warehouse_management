<?php

namespace Database\Seeders;

use App\Models\StoreUser;
use App\Notifications\UnorderedCartNotification;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Database\Seeders\Support\StoreSeedExamples;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $data->batch(function () use ($data): void {
            DB::transaction(function () use ($data): void {
                $this->geography($data);
                $this->baselines($data);
                $this->reviews($data);
                $this->staffAndUploads($data);
            });
        });
    }

    private function geography(StoreFilterSeedData $data): void
    {
        $zone = $data->find('zones', 'reference-zone') !== null ? $data->row('zones', 'reference-zone', ['name' => 'Western Europe', 'status' => 1]) : DB::table('zones')->orderBy('id')->value('id') ?? $data->row('zones', 'reference-zone', ['name' => 'Western Europe', 'status' => 1]);
        $ownedCountry = $data->find('countries', 'reference-country');
        if (! DB::table('countries')->where('status', 1)->exists() || $ownedCountry !== null) {
            $code = 'NL';
            $data->row('countries', 'reference-country', ['name' => 'Netherlands', 'code' => $code, 'zone_id' => $zone, 'status' => 1]);
        }
        $places = [
            'NL' => ['Noord-Holland' => 'Amsterdam', 'North Holland' => 'Amsterdam', 'Zuid-Holland' => 'Rotterdam', 'South Holland' => 'Rotterdam', 'Utrecht' => 'Utrecht', 'Gelderland' => 'Arnhem', 'Noord-Brabant' => 'Eindhoven', 'North Brabant' => 'Eindhoven', 'Limburg' => 'Maastricht', 'Zeeland' => 'Middelburg', 'Flevoland' => 'Almere', 'Overijssel' => 'Zwolle', 'Drenthe' => 'Assen', 'Groningen' => 'Groningen', 'Friesland' => 'Leeuwarden'],
            'BE' => ['Antwerpen' => 'Antwerp', 'Antwerp' => 'Antwerp', 'Brussels' => 'Brussels', 'East Flanders' => 'Ghent', 'West Flanders' => 'Bruges', 'Flemish Brabant' => 'Leuven', 'Limburg' => 'Hasselt'],
            'DE' => ['Berlin' => 'Berlin', 'Bavaria' => 'Munich', 'Bayern' => 'Munich', 'Hamburg' => 'Hamburg', 'Hessen' => 'Frankfurt', 'North Rhine-Westphalia' => 'Cologne'],
            'FR' => ['Île-de-France' => 'Paris'], 'GB' => ['Greater London' => 'London'],
            'US' => ['California' => 'Los Angeles'], 'SY' => ['Damascus' => 'Damascus'],
            'TR' => ['Istanbul' => 'Istanbul'], 'SA' => ['Riyadh' => 'Riyadh'], 'AE' => ['Dubai' => 'Dubai'],
        ];
        $countries = DB::table('countries')->where('status', 1)->orderBy('id')->get(['id', 'code']);
        foreach ($countries as $country) {
            $region = array_key_first($places[$country->code] ?? []);
            if ($region !== null && (! DB::table('states')->where('country_id', $country->id)->exists() || $data->find('states', 'reference-state-'.$country->id) !== null)) {
                $data->row('states', 'reference-state-'.$country->id, ['name' => $region, 'country_id' => $country->id, 'status' => 1]);
            }
            foreach (DB::table('states')->where('country_id', $country->id)->where('status', 1)->get(['id', 'name']) as $state) {
                $city = $places[$country->code][$state->name] ?? null;
                if ($city !== null && (! DB::table('cities')->where('state_id', $state->id)->exists() || $data->find('cities', 'reference-city-'.$state->id) !== null)) {
                    $data->row('cities', 'reference-city-'.$state->id, ['name' => $city, 'state_id' => $state->id, 'status' => 1, 'cost' => 2.5]);
                }
            }
        }
    }

    private function baselines(StoreFilterSeedData $data): void
    {
        foreach ([
            'currencies' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR', 'exchange_rate' => 1, 'status' => 1],
            'taxes' => ['name' => 'Reduced VAT', 'tax_status' => 1],
            'carriers' => ['name' => 'PostNL', 'transit_time' => '2–3 days', 'free_shipping' => 0, 'status' => 1],
            'customer_packages' => ['name' => 'Wholesale Starter', 'amount' => 10, 'product_upload' => 5],
            'pages' => ['type' => 'custom_page', 'title' => 'Delivery and Collection', 'slug' => 'delivery-and-collection', 'content' => '<p>Collect your wholesale order in Amsterdam or arrange delivery with our warehouse team.</p>'],
            'brands' => ['name' => 'Cedar Pantry', 'slug' => 'cedar-pantry'],
            'languages' => ['name' => 'English', 'code' => 'en', 'app_lang_code' => 'en', 'rtl' => 0, 'status' => 1],
        ] as $table => $values) {
            if (! DB::table($table)->exists() || $data->find($table, 'reference-baseline') !== null) {
                $data->row($table, 'reference-baseline', $values);
            }
        }
        $phrases = [
            'delivery_estimate' => 'Estimated delivery', 'order_received' => 'Order received',
            'payment_received' => 'Payment received', 'collection_ready' => 'Ready for collection',
            'wholesale_prices' => 'Wholesale prices', 'carton_quantity' => 'Carton quantity',
            'stock_available' => 'Available from stock', 'order_reference' => 'Order reference',
            'invoice_reference' => 'Invoice reference', 'delivery_address' => 'Delivery address',
            'billing_address' => 'Billing address', 'customer_account' => 'Customer account',
            'business_details' => 'Business details', 'contact_representative' => 'Contact your representative',
            'request_quote' => 'Request a quotation', 'payment_terms' => 'Payment terms',
            'product_origin' => 'Country of origin', 'storage_advice' => 'Storage advice',
            'allergen_information' => 'Allergen information', 'best_before_date' => 'Best before date',
            'return_request' => 'Request a return',
        ];
        foreach (['translations', 'app_translations'] as $table) {
            if (DB::table($table)->where('lang', 'en')->count() < StoreSeedExamples::ADMIN_ROWS || $data->find($table, 'reference-source') !== null) {
                foreach (array_keys($phrases) as $index => $key) {
                    $seedKey = $index === 0 ? 'reference-source' : 'reference-source-'.$index;
                    if ($data->find($table, $seedKey) !== null || ! DB::table($table)->where('lang', 'en')->where('lang_key', $key)->exists()) {
                        $data->row($table, $seedKey, ['lang' => 'en', 'lang_key' => $key, 'lang_value' => $phrases[$key]]);
                    }
                }
            }
        }
    }

    private function reviews(StoreFilterSeedData $data): void
    {
        $product = $data->find('products', 'reference-reviewed-product');
        if ($product !== null || ! DB::table('products')->exists()) {
            $product = $data->row('products', 'reference-reviewed-product', [
                'name' => 'Extra Virgin Olive Oil 1 L', 'ar_name' => '', 'slug' => 'cedar-extra-virgin-olive-oil',
                'added_by' => 'admin', 'user_id' => $data->user('admin'), 'published' => 1, 'approved' => 1,
                'category_id' => DB::table('categories')->orderBy('id')->value('id'),
                'unit_price' => 25, 'rating' => 3,
            ]);
        } else {
            $product = DB::table('products')->orderBy('id')->value('id');
        }
        foreach ([1, 5] as $rating) {
            for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
                $data->row('reviews', 'reference-review-'.$rating.($copy === 0 ? '' : '-'.$copy), [
                    'product_id' => $product, 'user_id' => $data->user('customer'), 'rating' => $rating,
                    'comment' => $rating === 5 ? 'Fresh ingredients, carefully packed and delivered on time.' : 'The outer carton arrived damaged; please improve the protective packaging.',
                    'status' => 1, 'viewed' => 0,
                ]);
            }
        }
    }

    private function staffAndUploads(StoreFilterSeedData $data): void
    {
        $admin = $data->user('admin');
        for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
            $data->row('notifications', 'reference-admin-notification'.($copy === 0 ? '' : '-'.$copy), [
                'id' => $copy === 0 ? 'da73dd02-5858-4ee9-a88b-2388fdc5464f' : (string) Str::uuid(),
                'type' => UnorderedCartNotification::class,
                'notifiable_type' => StoreUser::class, 'notifiable_id' => $admin,
                'data' => json_encode(['user_id' => $data->user('customer'), 'user_name' => StoreSeedExamples::person('customer'), 'message' => 'A wholesale customer left an unfinished basket.'], JSON_THROW_ON_ERROR),
            ]);
        }
        $staff = $data->user('staff');
        $role = $data->row('wms_roles', 'reference-staff-role', ['name' => 'Demonstration Store Staff', 'guard_name' => 'store']);
        for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
            $staffMember = $copy === 0 ? $staff : $data->user('staff-'.$copy, ['user_type' => 'staff']);
            $data->row('staff', 'reference-staff'.($copy === 0 ? '' : '-'.$copy), ['user_id' => $staffMember, 'role_id' => $role]);
            foreach (DB::table('wms_permissions')->where('guard_name', 'store')->where('name', 'view_uploads')->pluck('id') as $permission) {
                DB::table('wms_role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
            }
            DB::table('wms_model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => StoreUser::class, 'model_id' => $staffMember]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['image' => 'png', 'video' => 'mp4', 'audio' => 'wav', 'archive' => 'zip', 'document' => 'pdf'] as $type => $extension) {
            foreach ([$data->user('admin'), $staff] as $owner) {
                for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
                    // Metadata fixtures intentionally have no file URL or downloadable binary.
                    $data->row('uploads', 'reference-upload-'.$type.'-'.$owner.($copy === 0 ? '' : '-'.$copy), [
                        'user_id' => $owner, 'file_original_name' => 'Pantry catalogue '.$type.' '.($copy + 1),
                        'type' => $type, 'extension' => $extension, 'file_size' => strlen($type) * 100,
                        'file_name' => null, 'external_link' => null,
                    ]);
                }
            }
        }
    }
}

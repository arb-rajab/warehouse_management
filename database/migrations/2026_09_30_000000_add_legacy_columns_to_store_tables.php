<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's tables here reproduce `shop.sql`, a 2023-05 dump taken at
     * the vendor's version 6.5.0. The legacy store then altered them in
     * migrations of its own (2023-08 to 2025-03, `database/migrations` of
     * arb-rajab/albaraka-holland) and in the vendor's 7.0.0 upgrade script
     * (`sqlupdates/v700.sql`: `reviews.photos`, `categories.cover_image` and
     * the `shops` banners), none of which were carried over, so every column
     * below was missing while the store's models, registration, cart,
     * checkout and admin UI all use them. Each takes the type, default and
     * nullability of its last legacy migration (`double(M, D)` as
     * `decimal(M, D)`, as everywhere else here), and the `after()` placement
     * MySQL honours. The legacy migrations that only backfilled rows are not
     * repeated: there are no legacy rows here.
     *
     * Two columns are not a straight copy:
     *
     * - `users.company_name` was added NOT NULL with no default. MySQL fills
     *   existing rows with '' when it does that; sqlite refuses to add a NOT
     *   NULL column without a default at all, so '' is written out here.
     * - `users.raizer_admin` is in no legacy migration, `shop.sql` or
     *   sqlupdates file. It exists only in production, where it marks the
     *   vendor's own super-admin account: the store owner's admin is the row
     *   `where('raizer_admin', null)` finds, and the `RaizerSuperAdmin`
     *   middleware and the admin panel's cache-clear button check
     *   `raizer_admin == true`. A nullable boolean is what those reads imply.
     *
     * `products` is WMS-owned now (see .ai/rules/shared-database.md), but
     * production's table is the store's, with all of its columns, while
     * `create_products_table` builds only the eight WMS reads. The store's
     * home page, product pages, cart and checkout read the rest: the columns
     * `shop.sql` defines plus those its later migrations added. Four of
     * them were NOT NULL with no default there, which neither sqlite (for an
     * added column) nor `products:sync`, which writes only `name`, `ar_name`
     * and `published`, can satisfy: `unit_price` takes 0 here, and `slug`,
     * `serial` and `mat_id` are nullable (MySQL gives a text column no
     * default). `user_id` and `collection_id` match the ids they point at,
     * `users.id` and `product_collections.id`.
     *
     * Each added column is guarded on its own, since the store's production
     * database already has every one of them. The three column changes at
     * the end are the legacy migrations' own `->change()` calls, and leave
     * a column that already matches unchanged.
     */
    public function up(): void
    {
        foreach ($this->addedColumns() as $table => $columns) {
            foreach ($columns as $column => $define) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, $define);
                }
            }
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->string('shipping_type')->default('home_delivery')->change();
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->text('variant')->nullable()->change();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->integer('state_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->integer('state_id')->change();
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->string('variant')->change();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('shipping_type', 30)->default('')->change();
        });

        foreach (array_reverse($this->addedColumns()) as $table => $columns) {
            foreach (array_reverse(array_keys($columns)) as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }
    }

    /**
     * @return array<string, array<string, Closure(Blueprint): mixed>>
     */
    private function addedColumns(): array
    {
        return [
            'users' => [
                'company_name' => fn (Blueprint $table) => $table->string('company_name')->default('')->after('name'),
                'admin_verified' => fn (Blueprint $table) => $table->boolean('admin_verified')->nullable(),
                'admin_verified_at' => fn (Blueprint $table) => $table->date('admin_verified_at')->nullable()->after('admin_verified'),
                'last_order_at' => fn (Blueprint $table) => $table->date('last_order_at')->nullable()->after('admin_verified'),
                'is_important' => fn (Blueprint $table) => $table->boolean('is_important')->default(1)->after('admin_verified'),
                'tax_number' => fn (Blueprint $table) => $table->string('tax_number')->nullable(),
                'company_address' => fn (Blueprint $table) => $table->string('company_address')->nullable(),
                'shipping_address' => fn (Blueprint $table) => $table->string('shipping_address')->nullable(),
                'trade_license' => fn (Blueprint $table) => $table->string('trade_license')->nullable(),
                'bank_account_number' => fn (Blueprint $table) => $table->string('bank_account_number')->nullable(),
                'is_rep' => fn (Blueprint $table) => $table->boolean('is_rep')->default(0),
                'from_api' => fn (Blueprint $table) => $table->boolean('from_api')->nullable(),
                'member_serial' => fn (Blueprint $table) => $table->string('member_serial')->nullable(),
                'synched_with_otajer' => fn (Blueprint $table) => $table->boolean('synched_with_otajer')->nullable()->default(false),
                'AccSysID' => fn (Blueprint $table) => $table->integer('AccSysID')->nullable(),
                'registration_completed' => fn (Blueprint $table) => $table->boolean('registration_completed')->nullable(),
                'rep_serial' => fn (Blueprint $table) => $table->string('rep_serial')->nullable(),
                'rep_id' => fn (Blueprint $table) => $table->string('rep_id')->nullable(),
                'rep_package_id' => fn (Blueprint $table) => $table->integer('rep_package_id')->nullable(),
                'rep_discount_percentage' => fn (Blueprint $table) => $table->double('rep_discount_percentage')->nullable(),
                'admin_notes' => fn (Blueprint $table) => $table->string('admin_notes')->nullable(),
                'activity_status' => fn (Blueprint $table) => $table->enum('activity_status', ['new_user', 'has_been_contacted'])->default('new_user'),
                'profits' => fn (Blueprint $table) => $table->double('profits')->default(0),
                'device_key' => fn (Blueprint $table) => $table->string('device_key')->nullable(),
                'raizer_admin' => fn (Blueprint $table) => $table->boolean('raizer_admin')->nullable(),
            ],
            'categories' => [
                'serial' => fn (Blueprint $table) => $table->string('serial')->nullable(),
                'cover_image' => fn (Blueprint $table) => $table->string('cover_image', 100)->nullable()->after('icon'),
            ],
            'reviews' => [
                'photos' => fn (Blueprint $table) => $table->string('photos', 191)->nullable()->after('comment'),
            ],
            'shops' => [
                'top_banner' => fn (Blueprint $table) => $table->string('top_banner', 191)->nullable()->after('sliders'),
                'banner_full_width_1' => fn (Blueprint $table) => $table->string('banner_full_width_1', 191)->nullable()->after('top_banner'),
                'banners_half_width' => fn (Blueprint $table) => $table->string('banners_half_width', 191)->nullable()->after('banner_full_width_1'),
                'banner_full_width_2' => fn (Blueprint $table) => $table->string('banner_full_width_2', 191)->nullable()->after('banners_half_width'),
            ],
            'countries' => [
                'priority' => fn (Blueprint $table) => $table->boolean('priority')->default(0),
            ],
            'brands' => [
                'serial' => fn (Blueprint $table) => $table->integer('serial')->nullable(),
            ],
            'carts' => [
                'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
                'variation_qty' => fn (Blueprint $table) => $table->json('variation_qty')->nullable()->after('variation'),
                'by_rep' => fn (Blueprint $table) => $table->boolean('by_rep')->default(0),
                'for_customer' => fn (Blueprint $table) => $table->integer('for_customer')->nullable(),
                'item_notes' => fn (Blueprint $table) => $table->string('item_notes')->nullable(),
                'product_serial' => fn (Blueprint $table) => $table->string('product_serial')->nullable()->after('product_id'),
                'rep_price' => fn (Blueprint $table) => $table->decimal('rep_price', 20, 2)->nullable()->default(0.00)->after('price'),
                'is_price_changed' => fn (Blueprint $table) => $table->boolean('is_price_changed')->nullable()->default(false),
                'is_admin_notified' => fn (Blueprint $table) => $table->boolean('is_admin_notified')->default(0),
            ],
            'orders' => [
                'company_address' => fn (Blueprint $table) => $table->string('company_address')->nullable(),
                'company_shipping_address' => fn (Blueprint $table) => $table->string('company_shipping_address')->nullable(),
                'company_tax_number' => fn (Blueprint $table) => $table->string('company_tax_number')->nullable(),
                'order_from' => fn (Blueprint $table) => $table->string('order_from')->nullable(),
                'by_rep' => fn (Blueprint $table) => $table->boolean('by_rep')->default(0),
                'for_customer' => fn (Blueprint $table) => $table->integer('for_customer')->nullable(),
                'manager_notes' => fn (Blueprint $table) => $table->string('manager_notes')->nullable(),
                'member_serial' => fn (Blueprint $table) => $table->string('member_serial')->nullable()->after('user_id'),
                'delivery_date' => fn (Blueprint $table) => $table->string('delivery_date')->nullable(),
                'wms_status' => fn (Blueprint $table) => $table->string('wms_status')->nullable(),
                'otajerOrderID' => fn (Blueprint $table) => $table->string('otajerOrderID')->nullable(),
                'profits' => fn (Blueprint $table) => $table->double('profits')->default(0),
                'discount_percent' => fn (Blueprint $table) => $table->string('discount_percent')->nullable(),
                'indicator' => fn (Blueprint $table) => $table->double('indicator')->nullable(),
                'invoice_number' => fn (Blueprint $table) => $table->string('invoice_number')->nullable()->after('company_tax_number'),
            ],
            'products' => [
                'added_by' => fn (Blueprint $table) => $table->string('added_by', 6)->default('admin')->after('name'),
                'user_id' => fn (Blueprint $table) => $table->unsignedInteger('user_id')->nullable()->after('added_by'),
                'brand_id' => fn (Blueprint $table) => $table->integer('brand_id')->nullable(),
                'photos' => fn (Blueprint $table) => $table->string('photos', 2000)->nullable(),
                'old_photo' => fn (Blueprint $table) => $table->string('old_photo', 2000)->nullable()->after('photos'),
                'video_provider' => fn (Blueprint $table) => $table->string('video_provider', 20)->nullable(),
                'video_link' => fn (Blueprint $table) => $table->string('video_link', 100)->nullable(),
                'tags' => fn (Blueprint $table) => $table->string('tags', 500)->nullable(),
                'description' => fn (Blueprint $table) => $table->longText('description')->nullable(),
                'unit_price' => fn (Blueprint $table) => $table->decimal('unit_price', 20, 2)->default(0),
                'purchase_price' => fn (Blueprint $table) => $table->decimal('purchase_price', 20, 2)->nullable(),
                'variant_product' => fn (Blueprint $table) => $table->integer('variant_product')->default(0),
                'attributes' => fn (Blueprint $table) => $table->string('attributes', 1000)->default('[]'),
                'choice_options' => fn (Blueprint $table) => $table->mediumText('choice_options')->nullable(),
                'colors' => fn (Blueprint $table) => $table->mediumText('colors')->nullable(),
                'variations' => fn (Blueprint $table) => $table->text('variations')->nullable(),
                'todays_deal' => fn (Blueprint $table) => $table->integer('todays_deal')->default(0),
                'approved' => fn (Blueprint $table) => $table->boolean('approved')->default(1),
                'stock_visibility_state' => fn (Blueprint $table) => $table->string('stock_visibility_state', 10)->default('quantity'),
                'cash_on_delivery' => fn (Blueprint $table) => $table->boolean('cash_on_delivery')->default(0),
                'featured' => fn (Blueprint $table) => $table->integer('featured')->default(0),
                'seller_featured' => fn (Blueprint $table) => $table->integer('seller_featured')->default(0),
                'current_stock' => fn (Blueprint $table) => $table->integer('current_stock')->default(0),
                'unit' => fn (Blueprint $table) => $table->string('unit', 20)->nullable(),
                'weight' => fn (Blueprint $table) => $table->decimal('weight', 8, 2)->default(0),
                'min_qty' => fn (Blueprint $table) => $table->integer('min_qty')->default(1),
                'max_qty' => fn (Blueprint $table) => $table->integer('max_qty')->default(1000)->after('min_qty'),
                'low_stock_quantity' => fn (Blueprint $table) => $table->integer('low_stock_quantity')->nullable(),
                'discount' => fn (Blueprint $table) => $table->decimal('discount', 20, 2)->nullable(),
                'discount_type' => fn (Blueprint $table) => $table->string('discount_type', 10)->nullable(),
                'discount_start_date' => fn (Blueprint $table) => $table->integer('discount_start_date')->nullable(),
                'discount_end_date' => fn (Blueprint $table) => $table->integer('discount_end_date')->nullable(),
                'tax' => fn (Blueprint $table) => $table->decimal('tax', 20, 2)->nullable(),
                'tax_type' => fn (Blueprint $table) => $table->string('tax_type', 10)->nullable(),
                'shipping_type' => fn (Blueprint $table) => $table->string('shipping_type', 20)->nullable()->default('flat_rate'),
                'shipping_cost' => fn (Blueprint $table) => $table->decimal('shipping_cost', 20, 2)->default(0),
                'is_quantity_multiplied' => fn (Blueprint $table) => $table->boolean('is_quantity_multiplied')->default(0),
                'est_shipping_days' => fn (Blueprint $table) => $table->integer('est_shipping_days')->nullable(),
                'num_of_sale' => fn (Blueprint $table) => $table->integer('num_of_sale')->default(0),
                'meta_title' => fn (Blueprint $table) => $table->mediumText('meta_title')->nullable(),
                'meta_description' => fn (Blueprint $table) => $table->longText('meta_description')->nullable(),
                'meta_img' => fn (Blueprint $table) => $table->string('meta_img')->nullable(),
                'pdf' => fn (Blueprint $table) => $table->string('pdf')->nullable(),
                'slug' => fn (Blueprint $table) => $table->mediumText('slug')->nullable(),
                'rating' => fn (Blueprint $table) => $table->decimal('rating', 8, 2)->default(0),
                'barcode' => fn (Blueprint $table) => $table->string('barcode')->nullable(),
                'digital' => fn (Blueprint $table) => $table->integer('digital')->default(0),
                'auction_product' => fn (Blueprint $table) => $table->integer('auction_product')->default(0),
                'file_name' => fn (Blueprint $table) => $table->string('file_name')->nullable(),
                'file_path' => fn (Blueprint $table) => $table->string('file_path')->nullable(),
                'external_link' => fn (Blueprint $table) => $table->string('external_link', 500)->nullable(),
                'external_link_btn' => fn (Blueprint $table) => $table->string('external_link_btn')->nullable()->default('Buy Now'),
                'wholesale_product' => fn (Blueprint $table) => $table->integer('wholesale_product')->default(0),
                'serial' => fn (Blueprint $table) => $table->string('serial')->nullable(),
                'from_api' => fn (Blueprint $table) => $table->boolean('from_api')->default(0),
                'mat_id' => fn (Blueprint $table) => $table->string('mat_id')->nullable(),
                'unit_equal' => fn (Blueprint $table) => $table->integer('unit_equal')->nullable(),
                'isNewProduct' => fn (Blueprint $table) => $table->boolean('isNewProduct')->nullable(),
                'isOfferProduct' => fn (Blueprint $table) => $table->boolean('isOfferProduct')->nullable(),
                'for_sale' => fn (Blueprint $table) => $table->boolean('for_sale')->default(1),
                'api_unitId' => fn (Blueprint $table) => $table->string('api_unitId')->nullable(),
                'deleted_from_api' => fn (Blueprint $table) => $table->boolean('deleted_from_api')->default(0),
                'api_unit_name' => fn (Blueprint $table) => $table->string('api_unit_name')->nullable(),
                'collection_id' => fn (Blueprint $table) => $table->unsignedBigInteger('collection_id')->nullable(),
            ],
            'order_details' => [
                'product_serial' => fn (Blueprint $table) => $table->string('product_serial')->nullable()->after('product_id'),
                'variation_qty' => fn (Blueprint $table) => $table->json('variation_qty')->nullable()->after('variation'),
                'item_notes' => fn (Blueprint $table) => $table->string('item_notes')->nullable(),
                'product_matId' => fn (Blueprint $table) => $table->string('product_matId')->nullable(),
                'product_name' => fn (Blueprint $table) => $table->string('product_name')->nullable(),
                'original_price' => fn (Blueprint $table) => $table->decimal('original_price', 20, 2)->nullable()->default(0),
                'is_price_changed' => fn (Blueprint $table) => $table->boolean('is_price_changed')->nullable()->default(0),
                'profits' => fn (Blueprint $table) => $table->double('profits')->default(0),
            ],
        ];
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's tables here reproduce `shop.sql`, a 2023-05 dump. The
     * legacy store then altered them in migrations of its own (2023-08 to
     * 2025-03, `database/migrations` of arb-rajab/albaraka-holland) that were
     * never carried over, so every column below was missing while the
     * store's models, registration, cart, checkout and admin UI all use
     * them. Each takes the type, default and nullability of its last legacy
     * migration (`double(M, D)` as `decimal(M, D)`, as everywhere else here),
     * and the `after()` placement MySQL honours. The legacy migrations that
     * only backfilled rows are not repeated: there are no legacy rows here.
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

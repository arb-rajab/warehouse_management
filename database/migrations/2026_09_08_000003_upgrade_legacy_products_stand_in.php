<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `create_products_table` was rewritten to build the stand-in with the
     * store's own `thumbnail_img` instead of this app's invented `image_url`,
     * but editing a migration does nothing to a database that already ran it.
     * Staging and older local checkouts therefore still carry the pre-alignment
     * shape while the code selects `thumbnail_img` — this brings them across.
     *
     * `image_url` is what identifies such a database: it was only ever this
     * app's own column, and the store's real `products` has no column of that
     * name, so its absence means either the store's table or a stand-in that
     * is already current. Both are left alone.
     *
     * The stand-in's `id` is deliberately not retyped to the store's signed
     * `int(11)` here. That only matters for a foreign key against the real
     * shared table, which no stand-in environment has, and changing a primary
     * key referenced by four foreign keys is not worth the risk for a
     * development copy. See .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'image_url')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('thumbnail_img', 100)->nullable()->after('name');
        });

        if (Schema::hasColumn('products', 'boxes_count')) {
            $this->carryBoxCountsToProductSettings();

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('boxes_count');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: the restored columns come back empty, since `image_url` values
     * were never carried anywhere. Guarded on the environment rather than on
     * `hasColumn()`, which cannot tell the store's table from a stand-in — a
     * rollback must never add columns to the store's `products`.
     */
    public function down(): void
    {
        if (app()->isProduction() || Schema::hasColumn('products', 'image_url')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('name');
            $table->unsignedInteger('boxes_count')->default(1)->after('image_url');
            $table->dropColumn('thumbnail_img');
        });
    }

    /**
     * Moves the box counts off the shared table into this app's own, rather
     * than discarding them with the column. `insertOrIgnore` so a product that
     * somehow already has a settings row keeps the configured value.
     */
    private function carryBoxCountsToProductSettings(): void
    {
        $now = now();

        DB::table('products')
            ->select('id', 'boxes_count')
            ->orderBy('id')
            ->chunk(500, function ($products) use ($now) {
                $rows = $products
                    ->map(fn (object $product): array => [
                        'product_id' => $product->id,
                        'boxes_count' => $product->boxes_count,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('wms_product_settings')->insertOrIgnore($rows);
            });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named explicitly, so down() drops only the index this migration made.
     */
    private const string INDEX_NAME = 'product_barcodes_barcode_index';

    /**
     * Run the migrations.
     *
     * A plain (not unique) index on `product_barcodes.barcode`, which every
     * scan lookup (GET /api/v1/products/{code}), the store app's
     * `barcode_search`, the new-barcode uniqueness check (App\Rules\NewProductBarcode)
     * and the copy from `wms_product_barcodes` query by. Not unique: the
     * inherited table already holds values on several products, and existing
     * data is never changed to make room for a constraint
     * (`products:barcode-conflicts` lists them).
     *
     * Guarded on `hasTable()` (the inherited create migration may have left
     * the table to the store) and on `hasIndex()` for any index on exactly
     * this column, so a table that already has one, under whatever name, is
     * left alone and a re-run is a no-op. No driver guard: MySQL and sqlite
     * both build it, and a `varchar(255)` utf8mb4 column (1020 bytes) is under
     * InnoDB's 3072-byte key limit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_barcodes') || Schema::hasIndex('product_barcodes', ['barcode'])) {
            return;
        }

        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->index('barcode', self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops only an index carrying this migration's name, and never in
     * production, where the index may have been there before this migration
     * and the scan lookups depend on it. Touches no rows.
     */
    public function down(): void
    {
        if (app()->isProduction() || ! Schema::hasTable('product_barcodes') || ! Schema::hasIndex('product_barcodes', self::INDEX_NAME)) {
            return;
        }

        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite refuses to add a NOT NULL column without a default, so add one
        // with default(0) then backfill existing rows from their product's
        // boxes_count (the value the app itself uses when creating a pallet).
        Schema::table('pallets', function (Blueprint $table) {
            $table->unsignedInteger('remaining_boxes')->default(0)->after('expiration_date');
        });

        // `products.boxes_count` has since moved to the WMS-owned
        // wms_product_settings table (see .ai/rules/shared-database.md), and
        // the store-owned `products` never had a column of that name. A
        // database migrated before that move still has it and still needs the
        // backfill; anywhere else there is nothing to read from — and, this
        // being the migration that introduces `remaining_boxes`, no pallet
        // predating it either, so the default stands.
        if (! Schema::hasColumn('products', 'boxes_count')) {
            return;
        }

        DB::statement('
            UPDATE pallets
            SET remaining_boxes = (
                SELECT boxes_count FROM products WHERE products.id = pallets.product_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping remaining_boxes discards any live operational data
     * stored in that column.
     */
    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('remaining_boxes');
        });
    }
};

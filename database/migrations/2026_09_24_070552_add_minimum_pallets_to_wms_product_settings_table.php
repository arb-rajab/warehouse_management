<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `minimum_pallets` is the warehouse's own per-product reorder threshold —
     * WMS-owned config like `boxes_count`, not something the store's feed has
     * any concept of (see .ai/rules/shared-database.md). Nullable and with no
     * default: unlike `boxes_count`, "not configured" is a real, permanent
     * state here rather than a gap to fall back from — a product nobody has
     * set a minimum for must never count as low-stock (see Product::minimumPallets()).
     */
    public function up(): void
    {
        Schema::table('wms_product_settings', function (Blueprint $table) {
            $table->unsignedInteger('minimum_pallets')->nullable()->after('boxes_count');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping the column discards every configured minimum.
     */
    public function down(): void
    {
        Schema::table('wms_product_settings', function (Blueprint $table) {
            $table->dropColumn('minimum_pallets');
        });
    }
};

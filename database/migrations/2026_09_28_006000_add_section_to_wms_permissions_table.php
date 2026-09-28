<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The legacy store's `permissions` table carries a `section` column
     * (`varchar(50)`, e.g. `product`, grouping permissions like
     * `add_new_product`/`show_all_products` for its admin UI) that spatie's
     * own migration never adds. Since both apps share one `wms_permissions`
     * table (see .ai/rules/legacy-albaraka-holland.md §2/§3), that column has
     * to live here rather than on a second permissions table. Nullable: every
     * existing WMS permission has no section today, and nothing here assigns
     * one yet — the legacy data import is a separate, later session.
     */
    public function up(): void
    {
        Schema::table('wms_permissions', function (Blueprint $table) {
            $table->string('section', 50)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wms_permissions', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};

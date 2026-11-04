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
     * `products.mat_id` was the Otajer material id, and `products:sync` maps
     * that feed key (`Mat_ID`) to `products.id`, so the column only ever held a
     * copy of the primary key. Every reader now uses `id`; the API payload keys
     * named `mat_id` stay as aliases of it. Skipped when the column is absent.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'mat_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('mat_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the nullable string column `add_legacy_columns_to_store_tables`
     * defines and refills it from `id`, the only value it ever held.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'mat_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('mat_id')->nullable();
        });

        DB::table('products')->update(['mat_id' => DB::raw('CAST(id AS CHAR)')]);
    }
};

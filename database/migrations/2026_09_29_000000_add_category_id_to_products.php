<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `Product::category()` (merged from the legacy store's own Product
     * model, see .ai/rules/legacy-albaraka-holland.md section 2) belongs-to
     * `categories` via `category_id`. `products` is WMS-owned now (see
     * .ai/rules/shared-database.md's "Migration conventions" — no
     * `hasTable()`/environment guard, an ordinary migration like any other
     * WMS table), so this just adds the column, guarded only on `hasColumn()`
     * for idempotency. `categories.id` is a signed `integer`
     * (`create_categories_table`), so this matches that rather than
     * Laravel's default `bigint unsigned`.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'category_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->integer('category_id')->nullable()->after('thumbnail_img');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('products', 'category_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `products` only carries a primary key and the FULLTEXT index added by
     * `add_fulltext_index_to_products_table` — a FULLTEXT index cannot serve
     * an ORDER BY. The admin listing's default sort (and three other sort
     * sites) order by `name`, which otherwise forces a filesort over every
     * row before the LIMIT is applied.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};

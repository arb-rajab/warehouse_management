<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `follow_sellers` table, created by its own the vendor's
     * `sqlupdates/v700.sql` (the 7.0.0 upgrade, applied after `shop.sql` was
     * dumped at 6.5.0) after the 2023 `shop.sql` dump the store's other tables
     * here reproduce, and never carried over: the sellers a customer follows.
     *
     * It has no key and no timestamps there either: FollowSeller writes it with
     * `insert()`.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('follow_sellers')) {
            return;
        }

        Schema::create('follow_sellers', function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->bigInteger('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('follow_sellers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `dynamic_popup_user` table, created by its own
     * `2024_05_09_110614_create_dynamic_popup_user_table` after the 2023
     * `shop.sql` dump the store's other tables here reproduce, and never
     * carried over: the popups a customer has already been shown.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('dynamic_popup_user')) {
            return;
        }

        Schema::create('dynamic_popup_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dynamic_popup_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
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

        Schema::dropIfExists('dynamic_popup_user');
    }
};

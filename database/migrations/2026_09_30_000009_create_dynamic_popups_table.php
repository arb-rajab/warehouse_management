<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `dynamic_popups` table, created by its own
     * `2024_04_22_160750_create_dynamic_popups_table` after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * home page and search look up the popup to show a signed-in customer.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('dynamic_popups')) {
            return;
        }

        Schema::create('dynamic_popups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('status')->default(0);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('banner')->nullable();
            $table->string('btn_link')->nullable();
            $table->string('btn_text')->nullable();
            $table->string('btn_text_color')->nullable();
            $table->string('btn_background_color')->nullable();
            $table->integer('delay_seconds')->default(10);
            $table->integer('offer_id')->nullable();
            $table->integer('show_page')->default(0)->comment('0 for home page, any number else will represent category id of the desired category');
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

        Schema::dropIfExists('dynamic_popups');
    }
};

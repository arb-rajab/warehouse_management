<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `visits` table, created by its own
     * `2024_05_14_164256_create_visits_table` after the 2023 `shop.sql` dump
     * the store's other tables here reproduce, and never carried over: a sales
     * representative's customer visits, which the storefront layout checks for
     * every representative.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('visits')) {
            return;
        }

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('note')->nullable();
            $table->string('map_lat', 20)->nullable();
            $table->string('map_lng', 20)->nullable();
            $table->timestamp('visit_date')->nullable();
            $table->integer('rank')->default(0)->comment('visit rank in a day of visits, 0 is the first visit in the day');
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

        Schema::dropIfExists('visits');
    }
};

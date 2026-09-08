<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `products` is a *shared* table: in production it belongs to the store
     * app, which owns product CRUD and roughly seventy columns this app never
     * reads. WMS only ever reads from it. The `Schema::hasTable()` guard makes
     * this a no-op wherever the real table is already present — production and
     * any other environment pointed at the shared database — while still
     * building a local stand-in for development and testing.
     *
     * The stand-in deliberately mirrors the store's column types rather than
     * Laravel's defaults: `id` is a signed `int(11)` AUTO_INCREMENT there (the
     * store's own migration ends with an `ALTER TABLE ... MODIFY id int(11)`),
     * not `bigint unsigned`, so every `product_id` FK in this app is a signed
     * `integer` column to match. See .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in: a rollback must drop the
     * development copy but must never drop the store's production data.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `offers_catalogs` is a *shared* table, owned by the store app —
     * `HomeController::index()` (the legacy storefront's home page) reads
     * `OffersCatalog::first()` unconditionally, and `OffersCatalogController`
     * only ever downloads its `file` column, never writes it. Mirrors the
     * store's own `create_offers_catalogs_table` migration.
     *
     * The `hasTable()` guard is what makes this safe on the shared
     * database: in production this table already exists as the store's own,
     * so `up()` is a no-op there -- this app must never alter a table it
     * only reads.
     */
    public function up(): void
    {
        if (Schema::hasTable('offers_catalogs')) {
            return;
        }

        Schema::create('offers_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('file');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
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

        Schema::dropIfExists('offers_catalogs');
    }
};

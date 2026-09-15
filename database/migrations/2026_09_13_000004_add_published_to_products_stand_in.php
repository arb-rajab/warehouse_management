<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `create_products_table` now builds the stand-in with the store's
     * `published` column, but editing an already-run migration does nothing
     * to a database that recorded it: staging and older local checkouts would
     * keep the pre-`published` shape while `Admin\ProductController::index()`
     * filters against that column. This carries them across, the same way
     * `add_ar_name_to_products_stand_in` did for `ar_name`.
     *
     * The `hasColumn()` guard is what makes this safe on the shared database:
     * in production `products` is the store's own table and already carries
     * `published` (`int(11) NOT NULL DEFAULT 1`), so `up()` is a no-op there —
     * this app must never alter a table it only reads. A fresh install is a
     * no-op too, since `create_products_table` has already built the column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'published')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->integer('published')->default(1)->after('thumbnail_img');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasColumn()`, which cannot
     * tell the store's `products` from a local stand-in — a rollback must drop
     * the development copy's column and never a column of the store's own.
     *
     * Note the fresh-install wart this shares with every guarded stand-in
     * migration: there `published` came from `create_products_table`, so a
     * `migrate:rollback --step=1` removes a column this migration never added.
     * A full rollback drops the whole stand-in a step later, so only the
     * single-step case is affected.
     */
    public function down(): void
    {
        if (app()->isProduction() || ! Schema::hasColumn('products', 'published')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('published');
        });
    }
};

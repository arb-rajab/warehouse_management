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
     * `ar_name`, but editing an already-run migration does nothing to a
     * database that recorded it: staging and older local checkouts would keep
     * the pre-`ar_name` shape while `Product::searchByName()` selects against
     * that column. This carries them across, the same way
     * `upgrade_legacy_products_stand_in` did for `thumbnail_img`.
     *
     * The `hasColumn()` guard is what makes this safe on the shared database:
     * in production `products` is the store's own table and already carries
     * `ar_name` (`varchar(191) NOT NULL`), so `up()` is a no-op there — this
     * app must never alter a table it only reads. A fresh install is a no-op
     * too, since `create_products_table` has already built the column.
     *
     * The stand-in's column defaults to the empty string rather than being
     * nullable, mirroring the store's NOT NULL: a product with no Arabic name
     * is representable, existing stand-in rows can take the column without a
     * backfill, and sqlite — which refuses a NOT NULL column added without a
     * default — accepts the ALTER.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'ar_name')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('ar_name', 191)->default('')->after('name');
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
     * migration: there `ar_name` came from `create_products_table`, so a
     * `migrate:rollback --step=1` removes a column this migration never added.
     * A full rollback drops the whole stand-in a step later, so only the
     * single-step case is affected.
     */
    public function down(): void
    {
        if (app()->isProduction() || ! Schema::hasColumn('products', 'ar_name')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ar_name');
        });
    }
};

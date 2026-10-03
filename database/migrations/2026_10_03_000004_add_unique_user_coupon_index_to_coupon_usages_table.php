<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A coupon is single-use per customer, but that was only checked before
     * the order was placed, never when the usage row was written, so two
     * checkouts racing past the check both used it. The index makes the second
     * insert fail. Rows already used twice (the case it exists for) are
     * collapsed to the oldest first, or the index could not be built.
     *
     * The store's own database already has this table and may already carry
     * the index.
     */
    public function up(): void
    {
        if (! Schema::hasTable('coupon_usages') || Schema::hasIndex('coupon_usages', ['user_id', 'coupon_id'], 'unique')) {
            return;
        }

        DB::statement('DELETE FROM coupon_usages WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM coupon_usages GROUP BY user_id, coupon_id) AS oldest_usages)');

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique(['user_id', 'coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coupon_usages') && Schema::hasIndex('coupon_usages', ['user_id', 'coupon_id'], 'unique')) {
            Schema::table('coupon_usages', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'coupon_id']);
            });
        }
    }
};

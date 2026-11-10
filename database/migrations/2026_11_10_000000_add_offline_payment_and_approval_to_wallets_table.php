<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The wallet page and the wallet API resource read `offline_payment` and
     * `approval`, which the store's own wallets table has but the local
     * stand-in created by create_wallets_table did not. Each column is only
     * added when it is missing, so the store's database is left untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('wallets')) {
            return;
        }

        foreach (['offline_payment', 'approval'] as $column) {
            if (! Schema::hasColumn('wallets', $column)) {
                Schema::table('wallets', function (Blueprint $table) use ($column) {
                    $table->integer($column)->default(0);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment, like create_wallets_table: in production
     * the columns belong to the store's table.
     */
    public function down(): void
    {
        if (app()->isProduction() || ! Schema::hasTable('wallets')) {
            return;
        }

        foreach (['offline_payment', 'approval'] as $column) {
            if (Schema::hasColumn('wallets', $column)) {
                Schema::table('wallets', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The store tables' lookup columns, none of which the legacy dump indexes.
     *
     * @var array<string, list<list<string>>>
     */
    private const INDEXES = [
        'orders' => [['user_id'], ['seller_id'], ['combined_order_id'], ['delivery_status'], ['payment_status']],
        'carts' => [['user_id'], ['product_id'], ['temp_user_id']],
        'product_stocks' => [['product_id']],
        'order_details' => [['order_id'], ['seller_id'], ['product_id']],
        'trips' => [['driver_id'], ['truck_id']],
        'visits' => [['rep_id'], ['customer_id'], ['order_id']],
        'checkpoints' => [['trip_id'], ['relationable_type', 'relationable_id']],
    ];

    /**
     * Run the migrations.
     *
     * Guarded on `hasIndex()` by column list rather than by name, so the
     * store's own database, which may already index some of these under its
     * own names, never gets a duplicate. A missing table or column is skipped.
     * `cell_status_logs (user_id, created_at)` is already covered by
     * `add_user_id_created_at_index_to_cell_status_logs_table`.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $columns) {
                if (! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                    $blueprint->index($columns);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasIndex()`, which cannot
     * tell this migration's index from one the store already had.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                    $blueprint->dropIndex($columns);
                });
            }
        }
    }
};

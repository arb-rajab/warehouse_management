<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('refund_requests has the expected columns and defaults', function () {
    expect(Schema::hasTable('refund_requests'))->toBeTrue();
    expect(Schema::hasColumns('refund_requests', ['id', 'user_id', 'order_id', 'order_detail_id', 'seller_id', 'seller_approval', 'reason', 'admin_approval', 'admin_seen', 'refund_amount', 'refund_status', 'reject_reason', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('refund_requests')->insert(['user_id' => 1, 'order_id' => 2, 'order_detail_id' => 3]);

    $this->assertDatabaseHas('refund_requests', [...['user_id' => 1, 'order_id' => 2, 'order_detail_id' => 3], ...['seller_id' => null, 'seller_approval' => 0, 'admin_approval' => 0, 'admin_seen' => 0, 'refund_amount' => 0.00, 'refund_status' => 0, 'reason' => null, 'reject_reason' => null]]);
});

test('the refund_requests migration leaves an existing table and its rows alone', function () {
    DB::table('refund_requests')->insert(['user_id' => 1, 'order_id' => 2, 'order_detail_id' => 3]);

    $migration = require database_path('migrations/2026_10_31_100000_create_refund_requests_table.php');
    $migration->up();

    expect(Schema::hasTable('refund_requests'))->toBeTrue();
    expect(DB::table('refund_requests')->count())->toBe(1);
});

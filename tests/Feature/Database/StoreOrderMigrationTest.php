<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('order submissions enforce unique keys without deleting replay tombstones alongside orders', function () {
    expect(Schema::hasColumns('wms_store_order_submissions', ['submission_key', 'actor_id', 'order_id', 'payload_hash', 'created_at']))->toBeTrue()
        ->and(Schema::hasColumn('order_details', 'entry_unit'))->toBeTrue();
    $row = ['submission_key' => (string) Str::uuid(), 'actor_id' => 1, 'order_id' => 999999, 'payload_hash' => str_repeat('a', 64), 'created_at' => now()];
    DB::table('wms_store_order_submissions')->insert($row);
    expect(fn () => DB::table('wms_store_order_submissions')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

test('new order permissions are store-only idempotent and grant no roles automatically', function () {
    $migration = require database_path('migrations/2026_10_10_035933_add_order_entry_permissions.php');
    $migration->up();
    $migration->up();
    foreach (['add_order', 'edit_order', 'update_order_tracking', 'update_order_notes', 'edit_order_price'] as $name) {
        expect(DB::table(config('permission.table_names.permissions'))->where('name', $name)->where('guard_name', 'store')->count())->toBe(1)
            ->and(DB::table(config('permission.table_names.permissions'))->where('name', $name)->where('guard_name', 'web')->count())->toBe(0);
    }
    expect(DB::table(config('permission.table_names.role_has_permissions'))->count())->toBe(0);
});

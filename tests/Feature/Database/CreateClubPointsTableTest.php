<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('club_points has the expected columns and defaults', function () {
    expect(Schema::hasTable('club_points'))->toBeTrue();
    expect(Schema::hasColumns('club_points', ['id', 'user_id', 'order_id', 'points', 'convert_status', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('club_points')->insert(['user_id' => 1, 'order_id' => 2]);

    $this->assertDatabaseHas('club_points', [...['user_id' => 1, 'order_id' => 2], ...['points' => 0.00, 'convert_status' => 0]]);
});

test('the club_points migration leaves an existing table and its rows alone', function () {
    DB::table('club_points')->insert(['user_id' => 1, 'order_id' => 2]);

    $migration = require database_path('migrations/2026_10_31_100100_create_club_points_table.php');
    $migration->up();

    expect(Schema::hasTable('club_points'))->toBeTrue();
    expect(DB::table('club_points')->count())->toBe(1);
});

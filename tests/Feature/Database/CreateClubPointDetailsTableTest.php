<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('club_point_details has the expected columns and defaults', function () {
    expect(Schema::hasTable('club_point_details'))->toBeTrue();
    expect(Schema::hasColumns('club_point_details', ['id', 'club_point_id', 'product_id', 'product_qty', 'point', 'converted_amount', 'refunded', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('club_point_details')->insert(['club_point_id' => 1]);

    $this->assertDatabaseHas('club_point_details', [...['club_point_id' => 1], ...['product_id' => null, 'product_qty' => null, 'point' => 0.00, 'converted_amount' => 0.00, 'refunded' => 0]]);
});

test('the club_point_details migration leaves an existing table and its rows alone', function () {
    DB::table('club_point_details')->insert(['club_point_id' => 1]);

    $migration = require database_path('migrations/2026_10_31_100200_create_club_point_details_table.php');
    $migration->up();

    expect(Schema::hasTable('club_point_details'))->toBeTrue();
    expect(DB::table('club_point_details')->count())->toBe(1);
});

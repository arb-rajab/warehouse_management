<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the tickets table has the expected columns', function () {
    expect(Schema::hasTable('tickets'))->toBeTrue();
    expect(Schema::hasColumns('tickets', [
        'id', 'code', 'user_id', 'subject', 'details', 'files', 'status',
        'viewed', 'client_viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('tickets')->insert([
        'code' => 12345,
        'user_id' => 1,
        'subject' => 'Order issue',
    ]);

    $this->assertDatabaseHas('tickets', [
        'code' => 12345,
        'user_id' => 1,
        'subject' => 'Order issue',
        'status' => 'pending',
        'viewed' => 0,
        'client_viewed' => 0,
    ]);
});

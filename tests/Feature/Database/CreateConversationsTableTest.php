<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('conversations has the expected columns', function () {
    expect(Schema::hasTable('conversations'))->toBeTrue();
    expect(Schema::hasColumns('conversations', [
        'id', 'sender_id', 'receiver_id', 'title', 'sender_viewed', 'receiver_viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('conversations')->insert([
        'sender_id' => 1,
        'receiver_id' => 2,
    ]);

    $this->assertDatabaseHas('conversations', [
        'sender_id' => 1,
        'receiver_id' => 2,
        'sender_viewed' => 1,
        'receiver_viewed' => 0,
    ]);
});

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('messages has the expected columns', function () {
    expect(Schema::hasTable('messages'))->toBeTrue();
    expect(Schema::hasColumns('messages', [
        'id', 'conversation_id', 'user_id', 'message', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('messages')->insert([
        'conversation_id' => 1,
        'user_id' => 1,
        'message' => 'Hello there',
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => 1,
        'user_id' => 1,
        'message' => 'Hello there',
    ]);
});

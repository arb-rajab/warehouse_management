<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('firebase_notifications has the expected columns', function () {
    expect(Schema::hasTable('firebase_notifications'))->toBeTrue();
    expect(Schema::hasColumns('firebase_notifications', [
        'id', 'title', 'text', 'item_type', 'item_type_id', 'receiver_id', 'is_read', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('firebase_notifications')->insert([
        'item_type' => 'order',
        'item_type_id' => 1,
        'receiver_id' => 1,
    ]);

    $this->assertDatabaseHas('firebase_notifications', [
        'item_type' => 'order',
        'receiver_id' => 1,
        'is_read' => false,
    ]);
});

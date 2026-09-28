<?php

use Illuminate\Support\Facades\Schema;

test('firebase_notifications table has the expected columns', function () {
    expect(Schema::hasTable('firebase_notifications'))->toBeTrue();
    expect(Schema::hasColumns('firebase_notifications', [
        'id', 'title', 'text', 'item_type', 'item_type_id', 'receiver_id',
        'is_read', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

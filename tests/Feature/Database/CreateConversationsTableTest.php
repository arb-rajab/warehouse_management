<?php

use Illuminate\Support\Facades\Schema;

test('conversations has the expected columns', function () {
    expect(Schema::hasTable('conversations'))->toBeTrue();
    expect(Schema::hasColumns('conversations', [
        'id', 'sender_id', 'receiver_id', 'title', 'sender_viewed', 'receiver_viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the ticket_replies table has the expected columns', function () {
    expect(Schema::hasTable('ticket_replies'))->toBeTrue();
    expect(Schema::hasColumns('ticket_replies', [
        'id', 'ticket_id', 'user_id', 'reply', 'files', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('ticket_replies')->insert([
        'ticket_id' => 1,
        'user_id' => 1,
        'reply' => 'We are looking into this.',
    ]);

    $this->assertDatabaseHas('ticket_replies', [
        'ticket_id' => 1,
        'user_id' => 1,
        'reply' => 'We are looking into this.',
    ]);
});

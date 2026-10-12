<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the wallets table has the expected columns', function () {
    expect(Schema::hasTable('wallets'))->toBeTrue();
    expect(Schema::hasColumns('wallets', [
        'id', 'user_id', 'amount', 'payment_method', 'payment_details', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('wallets')->insert([
        'user_id' => 1,
        'amount' => 25.50,
    ]);

    $this->assertDatabaseHas('wallets', [
        'user_id' => 1,
        'amount' => 25.50,
    ]);
});

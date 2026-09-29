<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the subscribers table has the expected columns', function () {
    expect(Schema::hasTable('subscribers'))->toBeTrue();
    expect(Schema::hasColumns('subscribers', [
        'id', 'email', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('subscribers')->insert([
        'email' => 'subscriber@example.com',
    ]);

    $this->assertDatabaseHas('subscribers', [
        'email' => 'subscriber@example.com',
    ]);
});

test('email must be unique across subscribers', function () {
    DB::table('subscribers')->insert([
        'email' => 'duplicate@example.com',
    ]);

    expect(fn () => DB::table('subscribers')->insert([
        'email' => 'duplicate@example.com',
    ]))->toThrow(QueryException::class);
});

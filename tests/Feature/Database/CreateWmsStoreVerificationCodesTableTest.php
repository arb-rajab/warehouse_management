<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function insertVerificationCode(int $userId, string $hash = 'hash'): void
{
    DB::table('wms_store_verification_codes')->insert([
        'user_id' => $userId, 'code_hash' => $hash, 'issued_at' => now(), 'expires_at' => now()->addMinutes(15),
    ]);
}

test('wms_store_verification_codes has the expected columns', function () {
    expect(Schema::hasColumns('wms_store_verification_codes', [
        'id', 'user_id', 'code_hash', 'attempts', 'issued_at', 'expires_at',
    ]))->toBeTrue();
});

test('a user has at most one live code', function () {
    insertVerificationCode(1);

    expect(fn () => insertVerificationCode(1, 'another'))->toThrow(QueryException::class);
});

test('different users each have their own code and none has tried yet', function () {
    insertVerificationCode(1);
    insertVerificationCode(2);

    expect(DB::table('wms_store_verification_codes')->count())->toBe(2)
        ->and(DB::table('wms_store_verification_codes')->where('attempts', '>', 0)->count())->toBe(0);
});

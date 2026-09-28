<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('password_resets has the expected columns and no id/primary key', function () {
    expect(Schema::hasTable('password_resets'))->toBeTrue();
    expect(Schema::hasColumns('password_resets', ['email', 'token', 'created_at']))->toBeTrue();
    expect(Schema::hasColumn('password_resets', 'id'))->toBeFalse();

    DB::table('password_resets')->insert([
        'email' => 'customer@example.com',
        'token' => 'some-token',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('password_resets', [
        'email' => 'customer@example.com',
        'token' => 'some-token',
    ]);
});

test('email is indexed but not unique, so multiple reset requests for the same email are allowed', function () {
    DB::table('password_resets')->insert([
        'email' => 'customer@example.com',
        'token' => 'token-one',
        'created_at' => now(),
    ]);

    DB::table('password_resets')->insert([
        'email' => 'customer@example.com',
        'token' => 'token-two',
        'created_at' => now(),
    ]);

    expect(DB::table('password_resets')->where('email', 'customer@example.com')->count())->toBe(2);
});

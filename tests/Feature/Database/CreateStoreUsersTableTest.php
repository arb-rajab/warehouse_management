<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the users table has the expected columns', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasColumns('users', [
        'id', 'referred_by', 'provider', 'provider_id', 'refresh_token', 'access_token',
        'user_type', 'name', 'email', 'email_verified_at', 'verification_code',
        'new_email_verificiation_code', 'password', 'remember_token', 'device_token',
        'avatar', 'avatar_original', 'address', 'country', 'state', 'city', 'postal_code',
        'phone', 'balance', 'banned', 'referral_code', 'customer_package_id',
        'remaining_uploads', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('users')->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'user_type' => 'customer',
        'balance' => 0,
        'banned' => 0,
        'remaining_uploads' => 0,
    ]);
});

test('email must be unique across users', function () {
    DB::table('users')->insert([
        'name' => 'First User',
        'email' => 'duplicate@example.com',
    ]);

    expect(fn () => DB::table('users')->insert([
        'name' => 'Second User',
        'email' => 'duplicate@example.com',
    ]))->toThrow(QueryException::class);
});

test('multiple users may have a null email', function () {
    DB::table('users')->insert(['name' => 'No Email One']);
    DB::table('users')->insert(['name' => 'No Email Two']);

    $this->assertDatabaseHas('users', ['name' => 'No Email One', 'email' => null]);
    $this->assertDatabaseHas('users', ['name' => 'No Email Two', 'email' => null]);
});

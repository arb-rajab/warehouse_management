<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('notifications has the expected columns', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
    expect(Schema::hasColumns('notifications', [
        'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('id is a uuid primary key, not auto-incrementing', function () {
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\SomeNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => 1,
        'data' => '{}',
    ]);

    $this->assertDatabaseHas('notifications', [
        'id' => $id,
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => 1,
    ]);
});

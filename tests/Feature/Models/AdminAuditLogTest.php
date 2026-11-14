<?php

use App\Models\AdminAuditLog;
use App\Models\StoreUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

test('user() resolves the store account the row names, not another one', function () {
    $user = createStoreStaff('smtp_settings');
    createStoreStaff('smtp_settings');
    $row = AdminAuditLog::factory()->create(['user_id' => $user->id]);

    expect($row->user)->toBeInstanceOf(StoreUser::class)
        ->and($row->user->is($user))->toBeTrue();
});

test('changed_fields casts to an array and created_at to an immutable date', function () {
    $row = AdminAuditLog::factory()->create(['changed_fields' => ['host' => 'changed', 'password' => 'removed']])->fresh();

    expect($row->changed_fields)->toBe(['host' => 'changed', 'password' => 'removed'])
        ->and($row->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($row->getAttributes())->not->toHaveKey('updated_at');
});

test('record() snapshots who, the action, the field names, the IP and the browser', function () {
    $user = createStoreStaff('smtp_settings');
    $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => str_repeat('A', 300)]);

    $row = AdminAuditLog::record($user, AdminAuditLog::SMTP_SETTINGS_UPDATED, ['host' => 'set'], $request)->fresh();

    expect($row->user_id)->toBe($user->id)
        ->and($row->user_name)->toBe($user->name)
        ->and($row->user_email)->toBe($user->email)
        ->and($row->action)->toBe('smtp_settings.updated')
        ->and($row->changed_fields)->toBe(['host' => 'set'])
        ->and($row->ip_address)->toBe('203.0.113.7')
        ->and($row->user_agent)->toBe(str_repeat('A', 255));
});

test('forActions() keeps only the given actions', function () {
    $updated = AdminAuditLog::factory()->create();
    $failed = AdminAuditLog::factory()->passwordFailed()->create();
    AdminAuditLog::factory()->create(['action' => 'something_else.updated']);

    expect(AdminAuditLog::query()->forActions([AdminAuditLog::SMTP_SETTINGS_UPDATED])->pluck('id')->all())->toBe([$updated->id])
        ->and(AdminAuditLog::query()->forActions([AdminAuditLog::SMTP_SETTINGS_UPDATED, AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$updated->id, $failed->id])->sort()->values()->all());
});

test('recent() returns the newest rows of the actions first, at most the limit, without the user agent', function () {
    $this->freezeTime();
    $rows = collect(range(1, 12))->map(fn (int $minutes) => AdminAuditLog::factory()->create(['created_at' => now()->subMinutes($minutes)]));
    AdminAuditLog::factory()->create(['action' => 'something_else.updated', 'created_at' => now()]);

    $recent = AdminAuditLog::recent([AdminAuditLog::SMTP_SETTINGS_UPDATED]);

    expect($recent)->toHaveCount(10)
        ->and($recent->pluck('id')->all())->toBe($rows->take(10)->pluck('id')->all())
        ->and($recent->first()->getAttributes())->not->toHaveKey('user_agent');
});

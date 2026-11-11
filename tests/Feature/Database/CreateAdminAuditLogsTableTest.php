<?php

use App\Models\AdminAuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the admin audit log has the columns the SMTP page writes and reads, and no updated_at', function () {
    expect(Schema::hasColumns('admin_audit_logs', [
        'id', 'user_id', 'user_name', 'user_email', 'action', 'changed_fields', 'ip_address', 'user_agent', 'created_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('admin_audit_logs', 'updated_at'))->toBeFalse();
});

test('the user, action and changed fields are required; the snapshot, IP and browser are optional', function () {
    $columns = collect(Schema::getColumns('admin_audit_logs'))->keyBy('name');

    expect($columns['user_id']['nullable'])->toBeFalse()
        ->and($columns['action']['nullable'])->toBeFalse()
        ->and($columns['changed_fields']['nullable'])->toBeFalse()
        ->and($columns['user_name']['nullable'])->toBeTrue()
        ->and($columns['user_email']['nullable'])->toBeTrue()
        ->and($columns['ip_address']['nullable'])->toBeTrue()
        ->and($columns['user_agent']['nullable'])->toBeTrue();

    expect(fn () => DB::table('admin_audit_logs')->insert(['user_id' => 1, 'changed_fields' => '[]']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('admin_audit_logs')->insert(['action' => 'smtp_settings.updated', 'changed_fields' => '[]']))->toThrow(QueryException::class);
});

test('a row outlives the store account it names: no foreign key, the raw id stays', function () {
    $user = createStoreStaff('smtp_settings');
    $noise = createStoreStaff('smtp_settings');
    $row = AdminAuditLog::factory()->create(['user_id' => $user->id]);
    $other = AdminAuditLog::factory()->create(['user_id' => $noise->id]);

    $user->forceDelete();

    expect(DB::table('admin_audit_logs')->where('id', $row->id)->value('user_id'))->toBe($user->id)
        ->and($row->fresh()->user)->toBeNull()
        ->and($other->fresh()->user?->is($noise))->toBeTrue();
});

test('the recent-entries index covers the action and the time', function () {
    $indexes = collect(Schema::getIndexes('admin_audit_logs'))->pluck('columns')->all();

    expect($indexes)->toContain(['action', 'created_at'])
        ->and($indexes)->toContain(['user_id']);
});

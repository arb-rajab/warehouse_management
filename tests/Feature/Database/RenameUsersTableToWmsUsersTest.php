<?php

use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadRenameUsersTableToWmsUsersMigration(): object
{
    return require database_path('migrations/2026_09_02_000000_rename_users_table_to_wms_users.php');
}

test('the users table is renamed to wms_users', function () {
    expect(Schema::hasTable('wms_users'))->toBeTrue();
    expect(Schema::hasTable('users'))->toBeFalse();
});

test('deleting a user referenced by a cell status log is still restricted after the rename', function () {
    $log = CellStatusLog::factory()->create();
    $user = $log->user;

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting the user who acknowledged a flag still nulls acknowledged_by after the rename', function () {
    $user = User::factory()->create();
    $flag = CellStatusLogFlag::factory()->create([
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    $user->delete();

    $fresh = $flag->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->acknowledged_by)->toBeNull();
});

test('a user row and its dependents survive a down() and up() round trip', function () {
    $user = User::factory()->create(['email' => 'keeper@example.test']);
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    loadRenameUsersTableToWmsUsersMigration()->down();

    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('wms_users'))->toBeFalse();
    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('keeper@example.test');

    loadRenameUsersTableToWmsUsersMigration()->up();

    expect(Schema::hasTable('wms_users'))->toBeTrue();
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(DB::table('wms_users')->where('id', $user->id)->value('email'))->toBe('keeper@example.test');
    expect($log->fresh()->user->id)->toBe($user->id);
});

test('the migration leaves the store app\'s users table alone', function () {
    // Stands in for the shared production database, where `users` belongs to
    // the store app and `wms_users` is this app's own table. The migration
    // must rename neither.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('store_only_column');
    });
    DB::table('users')->insert(['store_only_column' => 'owned by the store app']);
    $wmsUser = User::factory()->create(['email' => 'wms@example.test']);

    loadRenameUsersTableToWmsUsersMigration()->up();

    expect(Schema::hasColumn('users', 'store_only_column'))->toBeTrue();
    expect(DB::table('users')->value('store_only_column'))->toBe('owned by the store app');
    expect(DB::table('wms_users')->where('id', $wmsUser->id)->value('email'))->toBe('wms@example.test');

    Schema::drop('users');
});

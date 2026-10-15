<?php

use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadRenameUsersTableToWmsUsersMigration(): object
{
    return require database_path('migrations/2026_09_02_000000_rename_users_table_to_wms_users.php');
}

test('the users table is renamed to wms_users', function () {
    expect(Schema::hasTable('wms_users'))->toBeTrue();
    // `users` exists too, but it's a different, unrelated table now — the
    // legacy store's own (.ai/rules/legacy-albaraka-holland.md §3.1),
    // created later by `create_users_table` and never renamed. It is not
    // the pre-rename WMS table this migration moved away.
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('deleting a user referenced by a cell status log is still restricted after the rename', function () {
    $log = CellStatusLog::factory()->create();
    $user = $log->user;

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting the user who acknowledged a flag still nulls acknowledged_by after the rename', function () {
    $user = User::factory()->create();
    $flag = CellStatusLogFlag::factory()->create([
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    $user->forceDelete();

    $fresh = $flag->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->acknowledged_by)->toBeNull();
});

test('a user row and its dependents survive a down() and up() round trip', function () {
    $user = User::factory()->create(['email' => 'keeper@example.test']);
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    // The legacy store's own `users` table (see
    // .ai/rules/legacy-albaraka-holland.md §3.1) exists permanently after
    // every migrate now, and the migration's own guard
    // (`! Schema::hasTable('users')`) would otherwise correctly refuse to
    // rename `wms_users` back onto an occupied name. Drop it first — it's
    // unrelated to this WMS-only rename round trip.
    Schema::dropIfExists('users');

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
    // This used to stand in for the shared production database with a
    // manually-created fixture table, back when this repo had no `users`
    // migration of its own. It's the real thing now: `create_users_table`
    // (.ai/rules/legacy-albaraka-holland.md §3.1) already created the legacy
    // store's own `users` table via RefreshDatabase's initial migrate, so
    // this test writes into that real table instead of a stand-in. The
    // migration must rename neither it nor touch its data.
    DB::table('users')->insert(['name' => 'Store Customer', 'user_type' => 'customer']);
    $wmsUser = User::factory()->create(['email' => 'wms@example.test']);

    loadRenameUsersTableToWmsUsersMigration()->up();

    expect(DB::table('users')->where('name', 'Store Customer')->value('user_type'))->toBe('customer');
    expect(DB::table('wms_users')->where('id', $wmsUser->id)->value('email'))->toBe('wms@example.test');
});

<?php

use App\Models\CellStatusLog;
use App\Models\CellVerificationRound;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Models\User;
use Database\Seeders\CellStatusLogSeeder;
use Database\Seeders\CellVerificationRoundSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StagingRealisticDataSeeder;
use Database\Seeders\StoreRoleSeeder;
use Database\Seeders\StoreUserSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Runs the callback with the application in the given environment, the way
 * DatabaseSeederTest does, and restores `testing` afterwards.
 */
function inEnvironment(string $environment, Closure $callback): mixed
{
    app()->instance('env', $environment);

    try {
        return $callback();
    } finally {
        app()->instance('env', 'testing');
    }
}

/**
 * @return array<string, int>
 */
function accountAndRoleCounts(): array
{
    return [
        'warehouse users' => User::query()->count(),
        'store users' => StoreUser::query()->count(),
        'roles' => DB::table('wms_roles')->count(),
        'role grants' => DB::table('wms_role_has_permissions')->count(),
        'model roles' => DB::table('wms_model_has_roles')->count(),
        'cell status logs' => CellStatusLog::query()->count(),
        'verification rounds' => CellVerificationRound::query()->count(),
    ];
}

dataset('account-writing seeders', [
    'StoreUserSeeder' => StoreUserSeeder::class,
    'CellStatusLogSeeder' => CellStatusLogSeeder::class,
    'CellVerificationRoundSeeder' => CellVerificationRoundSeeder::class,
    'StagingRealisticDataSeeder' => StagingRealisticDataSeeder::class,
    'DatabaseSeeder' => DatabaseSeeder::class,
]);

test('every seeder that writes demo accounts refuses production and writes nothing', function (string $seeder) {
    // Fill the database first, so each seeder has everything it needs to run
    // and would write accounts if the guard were missing.
    (new DatabaseSeeder)->run();
    $before = accountAndRoleCounts();

    expect(fn () => inEnvironment('production', fn () => (new $seeder)->run()))
        ->toThrow(RuntimeException::class, 'in production');

    expect(accountAndRoleCounts())->toBe($before);
})->with('account-writing seeders');

test('the refused store admin seeder leaves no known-password Super Admin behind on an empty database', function () {
    Role::findOrCreate(Role::SUPER_ADMIN, 'store');

    expect(fn () => inEnvironment('production', fn () => (new StoreUserSeeder)->run()))
        ->toThrow(RuntimeException::class);

    expect(StoreUser::where('email', 'store-admin@example.com')->exists())->toBeFalse()
        ->and(DB::table('wms_model_has_roles')->count())->toBe(0);
});

test('the store admin seeder still seeds a Super Admin in local and testing', function (string $environment) {
    inEnvironment($environment, function (): void {
        (new StoreRoleSeeder)->run();
        (new StoreUserSeeder)->run();
    });

    $admin = StoreUser::where('email', 'store-admin@example.com')->sole();

    expect($admin->hasRole(Role::SUPER_ADMIN, 'store'))->toBeTrue();
})->with(['local', 'testing']);

test('the store role seeder keeps creating Super Admin and the permissions in production, but not the Clerk demo role', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();

    inEnvironment('production', function (): void {
        (new StoreRoleSeeder)->run();
        (new StoreRoleSeeder)->run();
    });

    expect(Role::query()->pluck('name')->all())->toBe([Role::SUPER_ADMIN])
        ->and(Permission::query()->where('guard_name', 'store')->exists())->toBeTrue()
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0);
});

test('the store role seeder creates the Clerk demo role in local and testing only', function (string $environment, bool $hasClerk) {
    inEnvironment($environment, fn () => (new StoreRoleSeeder)->run());

    expect(Role::query()->where('name', 'Clerk')->exists())->toBe($hasClerk)
        ->and(Role::query()->where('name', Role::SUPER_ADMIN)->exists())->toBeTrue();
})->with([
    'local' => ['local', true],
    'testing' => ['testing', true],
    'staging' => ['staging', false],
    'production' => ['production', false],
]);

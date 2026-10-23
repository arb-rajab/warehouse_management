<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Support\StorePermissions;
use Database\Seeders\StoreUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const CREATE_ADMIN_PASSWORD_PROMPT = 'Password (at least 12 characters)';
const CREATE_ADMIN_VALID_PASSWORD = 'correct horse battery staple';

/**
 * @return array<string, int>
 */
function storeAccountAndGrantCounts(): array
{
    return [
        'store users' => StoreUser::query()->count(),
        'roles' => DB::table('wms_roles')->count(),
        'permissions' => DB::table('wms_permissions')->count(),
        'role grants' => DB::table('wms_role_has_permissions')->count(),
        'user permissions' => DB::table('wms_model_has_permissions')->count(),
        'user roles' => DB::table('wms_model_has_roles')->count(),
    ];
}

test('store:create-admin creates the role, a verified admin user and the assignment, and prints what it did', function () {
    $bystander = createStoreUser('customer', 'bystander@example.com');
    expect(Role::query()->count())->toBe(0);

    $this->artisan('store:create-admin', ['email' => 'Owner@Example.com', '--name' => 'The Owner'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->expectsOutputToContain('Created the Super Admin role (guard store).')
        ->expectsOutputToContain('with email owner@example.com (user_type admin, email verified).')
        ->expectsOutputToContain('Assigned the Super Admin role to it.')
        ->doesntExpectOutputToContain(CREATE_ADMIN_VALID_PASSWORD)
        ->assertSuccessful();

    $admin = StoreUser::query()->where('email', 'owner@example.com')->sole();

    expect($admin->name)->toBe('The Owner')
        ->and($admin->user_type)->toBe('admin')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->password)->not->toBe(CREATE_ADMIN_VALID_PASSWORD)
        ->and(Hash::check(CREATE_ADMIN_VALID_PASSWORD, $admin->password))->toBeTrue()
        ->and($admin->hasRole(Role::SUPER_ADMIN, 'store'))->toBeTrue()
        ->and(Role::query()->pluck('name')->all())->toBe([Role::SUPER_ADMIN])
        ->and($bystander->fresh()->getRoleNames()->all())->toBe([]);
});

test('store:create-admin defaults the name to the part of the email before the @', function () {
    $this->artisan('store:create-admin', ['email' => 'first.owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->assertSuccessful();

    expect(StoreUser::query()->where('email', 'first.owner@example.com')->value('name'))->toBe('first.owner');
});

test('store:create-admin never writes a grant other than the Super Admin assignment', function () {
    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->assertSuccessful();

    $admin = StoreUser::query()->where('email', 'owner@example.com')->sole();
    $assignments = DB::table('wms_model_has_roles')->get();

    expect($assignments)->toHaveCount(1)
        ->and($assignments->first()->model_id)->toBe($admin->id)
        ->and($assignments->first()->model_type)->toBe(StoreUser::class)
        ->and(Role::query()->findOrFail($assignments->first()->role_id)->name)->toBe(Role::SUPER_ADMIN)
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0)
        ->and(DB::table('wms_model_has_permissions')->count())->toBe(0);
});

test('store:create-admin refuses an email that already belongs to a store user, without asking for a password', function () {
    $existing = createStoreUser('customer', 'owner@example.com');
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'OWNER@example.com'])
        ->expectsOutputToContain('already exists (id '.$existing->id.')')
        ->expectsOutputToContain('--promote')
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before)
        ->and($existing->fresh()->user_type)->toBe('customer');
});

test('store:create-admin --promote only gives an existing back-office user the Super Admin role', function () {
    $existing = createStoreUser('admin', 'owner@example.com', 'old-password-1234');
    $other = createStoreUser('staff', 'staff@example.com');
    $passwordHash = $existing->password;
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com', '--promote' => true, '--name' => 'Ignored'])
        ->expectsOutputToContain('Assigned the Super Admin role to existing store user '.$existing->id)
        ->assertSuccessful();

    $after = storeAccountAndGrantCounts();
    $existing->refresh();

    expect($existing->hasRole(Role::SUPER_ADMIN, 'store'))->toBeTrue()
        ->and($existing->password)->toBe($passwordHash)
        ->and($existing->name)->not->toBe('Ignored')
        ->and($other->fresh()->getRoleNames()->all())->toBe([])
        ->and($after['store users'])->toBe($before['store users'])
        ->and($after['user roles'])->toBe($before['user roles'] + 1)
        ->and($after['role grants'])->toBe(0)
        ->and($after['user permissions'])->toBe(0);
});

test('store:create-admin --promote on a user who already has Super Admin changes nothing', function () {
    $admin = createStoreAdmin();
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => $admin->email, '--promote' => true])
        ->expectsOutputToContain('already has the Super Admin role; nothing assigned.')
        ->assertSuccessful();

    expect(storeAccountAndGrantCounts())->toBe($before);
});

test('store:create-admin --promote refuses a user who could not sign in to the back office', function (string $userType, bool $banned) {
    $user = createStoreUser($userType, 'someone@example.com');
    $user->forceFill(['banned' => $banned ? 1 : 0])->save();
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'someone@example.com', '--promote' => true])
        ->expectsOutputToContain('cannot sign in to the back office')
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before);
})->with([
    'customer' => ['customer', false],
    'seller' => ['seller', false],
    'banned admin' => ['admin', true],
]);

test('store:create-admin writes nothing when the password confirmation does not match', function () {
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', 'a different long password')
        ->expectsOutputToContain('The passwords do not match. Nothing was written.')
        ->doesntExpectOutputToContain(CREATE_ADMIN_VALID_PASSWORD)
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before)
        ->and(Role::query()->count())->toBe(0);
});

test('store:create-admin writes nothing for a password shorter than 12 characters', function () {
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, 'short-11-ch')
        ->expectsOutputToContain('The password must be at least 12 characters. Nothing was written.')
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before);
});

test('store:create-admin writes nothing for an empty password, as when there is no terminal to answer', function () {
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, '')
        ->expectsOutputToContain('The password must be at least 12 characters.')
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before);
});

test('store:create-admin writes nothing for an invalid email', function (string $email) {
    $before = storeAccountAndGrantCounts();

    $this->artisan('store:create-admin', ['email' => $email])
        ->expectsOutputToContain('is not a valid email address.')
        ->assertFailed();

    expect(storeAccountAndGrantCounts())->toBe($before);
})->with(['no at sign' => 'owner.example.com', 'no domain' => 'owner@', 'spaces' => 'own er@example.com']);

test('store:create-admin has no way to pass the password on the command line', function () {
    $definition = Artisan::all()['store:create-admin']->getDefinition();
    $names = array_merge(array_keys($definition->getArguments()), array_keys($definition->getOptions()));

    expect(collect($names)->filter(fn (string $name): bool => str_contains($name, 'pass') && $name !== 'promote'))->toBeEmpty();

    expect(fn () => Artisan::call('store:create-admin', ['email' => 'owner@example.com', '--password' => CREATE_ADMIN_VALID_PASSWORD]))
        ->toThrow(Exception::class);

    expect(StoreUser::query()->where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('store:create-admin runs in production, where the demo seeder refuses', function () {
    app()->instance('env', 'production');

    try {
        expect(fn () => (new StoreUserSeeder)->run())->toThrow(RuntimeException::class);

        $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
            ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
            ->assertSuccessful();
    } finally {
        app()->instance('env', 'testing');
    }

    expect(StoreUser::query()->where('email', 'store-admin@example.com')->exists())->toBeFalse()
        ->and(StoreUser::query()->where('email', 'owner@example.com')->sole()->hasRole(Role::SUPER_ADMIN, 'store'))->toBeTrue();
});

test('store:create-admin syncs the store permissions only when there are none, and grants none of them', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();
    $web = DB::table('wms_permissions')->where('guard_name', 'web')->count();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->expectsOutputToContain('The store permissions table was empty: created '.count(StorePermissions::all()).' store permissions')
        ->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(StorePermissions::all()))
        ->and(DB::table('wms_permissions')->where('guard_name', 'web')->count())->toBe($web)
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0);
});

test('store:create-admin tells the operator to run the sync when some permissions are missing, and does not create them', function () {
    Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->delete();
    $total = Permission::query()->count();

    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->expectsOutputToContain('2 store permissions defined in code are missing from the database: run "php artisan store:sync-permissions"')
        ->assertSuccessful();

    expect(Permission::query()->count())->toBe($total);
});

test('store:create-admin says nothing about the sync when every permission exists', function () {
    $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
        ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
        ->doesntExpectOutputToContain('store:sync-permissions')
        ->doesntExpectOutputToContain('table was empty')
        ->assertSuccessful();
});

test('store:create-admin rolls the role and permissions back when the user cannot be saved', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();
    $before = storeAccountAndGrantCounts();

    StoreUser::saving(fn () => throw new RuntimeException('boom'));

    try {
        expect(fn () => $this->artisan('store:create-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion(CREATE_ADMIN_PASSWORD_PROMPT, CREATE_ADMIN_VALID_PASSWORD)
            ->expectsQuestion('Confirm password', CREATE_ADMIN_VALID_PASSWORD)
            ->run())
            ->toThrow(RuntimeException::class, 'boom');
    } finally {
        StoreUser::flushEventListeners();
    }

    expect(storeAccountAndGrantCounts())->toBe($before)
        ->and(Role::query()->count())->toBe(0);
});

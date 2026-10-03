<?php

use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * The columns that decide privilege and verification are not mass assignable
 * on StoreUser, so a request's input can never set them through create() or
 * fill(). Code that means to set them uses forceFill() or a property.
 */
const STORE_USER_PRIVILEGED_COLUMNS = ['user_type', 'is_rep', 'admin_verified', 'email_verified_at', 'verification_code', 'rep_id'];

test('the privileged columns are not mass assignable', function (string $column) {
    expect((new StoreUser)->isFillable($column))->toBeFalse();
})->with(STORE_USER_PRIVILEGED_COLUMNS);

test('the profile columns are still mass assignable', function (string $column) {
    expect((new StoreUser)->isFillable($column))->toBeTrue();
})->with(['name', 'email', 'password', 'phone', 'company_name', 'country']);

test('forging a privileged column through create() is refused and nothing is stored', function (string $column, mixed $value) {
    $before = StoreUser::count();

    expect(fn () => StoreUser::create(['name' => 'Forger', 'email' => 'forger@example.com', $column => $value]))
        ->toThrow(MassAssignmentException::class);

    expect(StoreUser::count())->toBe($before);
})->with([
    ['user_type', 'admin'],
    ['is_rep', true],
    ['admin_verified', true],
    ['email_verified_at', '2026-01-01 00:00:00'],
    ['verification_code', '123456'],
    ['rep_id', '7'],
]);

test('fill() leaves an existing user untouched when a privileged column is forged', function () {
    $customer = createStoreUser('customer');

    expect(fn () => $customer->fill(['name' => 'Renamed', 'user_type' => 'admin', 'is_rep' => true]))
        ->toThrow(MassAssignmentException::class);

    $fresh = $customer->fresh();
    expect($fresh->user_type)->toBe('customer')
        ->and($fresh->is_rep)->toBeFalse()
        ->and($fresh->name)->not->toBe('Renamed');
});

test('the password is hashed on assignment and a hash is not hashed twice', function () {
    $plain = StoreUser::create(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret-pass-1']);
    $hash = Hash::make('already-hashed-1');
    $prehashed = StoreUser::create(['name' => 'Hashed', 'email' => 'hashed@example.com', 'password' => $hash]);

    expect($plain->fresh()->password)->not->toBe('secret-pass-1')
        ->and(Hash::check('secret-pass-1', $plain->fresh()->password))->toBeTrue()
        ->and($prehashed->fresh()->password)->toBe($hash)
        ->and(Hash::check('already-hashed-1', $prehashed->fresh()->password))->toBeTrue();
});

test('the flags and the verification time are cast', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['is_rep' => 1, 'admin_verified' => 1, 'registration_completed' => 0])->save();

    $fresh = $user->fresh();
    expect($fresh->is_rep)->toBeTrue()
        ->and($fresh->admin_verified)->toBeTrue()
        ->and($fresh->registration_completed)->toBeFalse()
        ->and($fresh->email_verified_at)->toBeInstanceOf(CarbonInterface::class);
});

test('creating a customer through the admin form ignores forged privileged fields', function () {
    Schema::create('customers', function ($table) {
        $table->id();
        $table->unsignedInteger('user_id');
        $table->timestamps();
    });
    $noise = createStoreUser('customer', 'noise@example.com');
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/customers', [
        'name' => 'Forged Customer',
        'email' => 'forged@example.com',
        'phone' => '31600000001',
        'user_type' => 'admin',
        'is_rep' => 1,
        'admin_verified' => 1,
        'verification_code' => '000000',
        'rep_id' => '99',
    ])->assertOk();

    $created = StoreUser::where('email', 'forged@example.com')->firstOrFail();
    expect($created->user_type)->toBe('customer')
        ->and($created->is_rep)->toBeFalse()
        ->and($created->admin_verified)->toBeFalsy()
        ->and($created->verification_code)->toBeNull()
        ->and($created->rep_id)->toBeNull()
        ->and($created->roles()->count())->toBe(0)
        ->and($noise->fresh()->user_type)->toBe('customer');
});

test('a customer cannot create customers through the admin form', function () {
    actingAsStoreUser(createStoreUser('customer'));
    $before = StoreUser::count();

    $this->post('/temp/admin/customers', ['name' => 'X', 'email' => 'x@example.com', 'phone' => '31600000002'])
        ->assertNotFound();

    expect(StoreUser::count())->toBe($before)
        ->and(StoreUser::where('email', 'x@example.com')->exists())->toBeFalse();
});

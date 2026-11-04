<?php

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsRepresentativeOrAdmin;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The back-office role gates (IsAdmin, IsRepresentativeOrAdmin), the isAdmin()
 * helper, the back-office notification feed and `store:create-admin` all read
 * one list of back-office user types, StoreUser::ADMIN_USER_TYPES, through
 * StoreUser::hasAdminAccess(). The gates' own ban and strictness cases live in
 * StoreRoleMiddlewareTest.
 */
dataset('store user types', [
    'admin' => ['admin', true],
    'staff' => ['staff', true],
    'accountant' => ['accountant', true],
    'customer' => ['customer', false],
    'seller' => ['seller', false],
    'delivery boy' => ['delivery_boy', false],
    'driver' => ['driver', false],
]);

test('the back-office user types are admin, staff and accountant', function () {
    expect(StoreUser::ADMIN_USER_TYPES)->toBe(['admin', 'staff', 'accountant']);
});

test('hasAdminAccess() is true only for the back-office user types', function (string $type, bool $expected) {
    expect(createStoreUser($type)->hasAdminAccess())->toBe($expected);
})->with('store user types');

test('hasAdminAccess() compares the user type strictly', function () {
    // `true == 'admin'` holds in PHP; the list must not be satisfied by it.
    $user = new StoreUser;
    $user->user_type = true;

    expect($user->hasAdminAccess())->toBeFalse();
});

test('isAdmin() follows hasAdminAccess() for a signed-in store user', function (string $type, bool $expected) {
    Auth::guard('store')->setUser(createStoreUser($type));
    Auth::shouldUse('store');

    expect(isAdmin())->toBe($expected);
})->with('store user types');

test('isAdmin() is false for a guest', function () {
    Auth::shouldUse('store');

    expect(isAdmin())->toBeFalse();
});

test('isAdmin() is false for a warehouse user, whatever its attributes', function () {
    $warehouseUser = new User;
    $warehouseUser->user_type = 'admin';
    Auth::guard('web')->setUser($warehouseUser);
    Auth::shouldUse('web');

    expect(isAdmin())->toBeFalse();
});

test('the admin gates admit exactly the types hasAdminAccess() does', function (string $middleware, string $type, bool $expected) {
    $user = createStoreUser($type);
    $request = Request::create('/guarded');
    $request->setUserResolver(fn () => $user);

    try {
        $reached = (new $middleware)->handle($request, fn () => response('reached'))->getContent() === 'reached';
    } catch (HttpException) {
        $reached = false;
    }

    expect($reached)->toBe($expected);
})->with([IsAdmin::class, IsRepresentativeOrAdmin::class])->with('store user types');

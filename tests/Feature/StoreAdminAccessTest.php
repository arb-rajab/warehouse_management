<?php

use App\Http\Middleware\IsAdmin;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The isAdmin() helper and the IsAdmin middleware share one list of back-office
 * user types, StoreUser::ADMIN_USER_TYPES, through StoreUser::hasAdminAccess().
 * The middleware's own allow/ban/strictness cases live in StoreRoleMiddlewareTest.
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

test('hasAdminAccess() is true only for the back-office user types', function (string $type, bool $expected) {
    expect(createStoreUser($type)->hasAdminAccess())->toBe($expected);
})->with('store user types');

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

test('the IsAdmin gate admits exactly the types hasAdminAccess() does', function () {
    $types = ['admin', 'staff', 'accountant', 'customer', 'seller', 'delivery_boy', 'driver'];

    foreach ($types as $type) {
        $user = createStoreUser($type);
        $request = Request::create('/guarded');
        $request->setUserResolver(fn () => $user);

        $reached = rescue(
            fn () => (new IsAdmin)->handle($request, fn () => response('reached'))->getContent() === 'reached',
            false,
            false,
        );

        expect($reached)->toBe($user->hasAdminAccess(), $type);
    }
});

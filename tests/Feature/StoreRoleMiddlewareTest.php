<?php

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsAppUserUnbanned;
use App\Http\Middleware\IsCustomer;
use App\Http\Middleware\IsDeliveryBoy;
use App\Http\Middleware\IsDriver;
use App\Http\Middleware\IsSeller;
use App\Http\Middleware\IsUser;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The store's role gates compared `user_type` loosely and, except IsSeller,
 * never looked at `banned`, so a banned admin, customer or driver kept every
 * route their role opens. They share one check now
 * (RequiresUnbannedStoreUser); IsAppUserUnbanned, which answered a banned
 * user with a 200 and 500'd on a request with no user or token, is the API's
 * own ban gate.
 */
function reachesRoute(string $middleware, ?StoreUser $user): bool
{
    $request = Request::create('/guarded');
    $request->setUserResolver(fn () => $user);

    try {
        $response = (new $middleware)->handle($request, fn () => response('reached'));
    } catch (HttpException) {
        return false;
    }

    return $response->getContent() === 'reached';
}

dataset('role gates', [
    'admin' => [IsAdmin::class, ['admin', 'staff', 'accountant']],
    'customer' => [IsCustomer::class, ['customer']],
    'user' => [IsUser::class, ['customer', 'seller', 'delivery_boy']],
    'seller' => [IsSeller::class, ['seller']],
    'driver' => [IsDriver::class, ['driver']],
    'delivery boy' => [IsDeliveryBoy::class, ['delivery_boy']],
]);

test('a role gate lets through an unbanned user of each type it allows', function (string $middleware, array $allowedTypes) {
    foreach ($allowedTypes as $type) {
        expect(reachesRoute($middleware, createStoreUser($type)))->toBeTrue("$middleware refused an unbanned $type");
    }
})->with('role gates');

test('a role gate refuses a banned user of a type it allows', function (string $middleware, array $allowedTypes) {
    foreach ($allowedTypes as $type) {
        $banned = createStoreUser($type);
        $banned->forceFill(['banned' => 1])->save();

        expect(reachesRoute($middleware, $banned->fresh()))->toBeFalse("$middleware let a banned $type through");
    }
})->with('role gates');

test('a role gate refuses a user of any other type', function (string $middleware, array $allowedTypes) {
    $others = array_diff(['admin', 'staff', 'accountant', 'customer', 'seller', 'delivery_boy', 'driver'], $allowedTypes);

    foreach ($others as $type) {
        expect(reachesRoute($middleware, createStoreUser($type)))->toBeFalse("$middleware let a $type through");
    }
})->with('role gates');

test('a role gate refuses a request with no user', function (string $middleware, array $allowedTypes) {
    expect(reachesRoute($middleware, null))->toBeFalse();
})->with('role gates');

test('a role gate compares the user type strictly', function (string $middleware, array $allowedTypes) {
    // `true == 'admin'` holds in PHP; no gate may be satisfied by it.
    $user = new StoreUser;
    $user->user_type = true;

    expect(reachesRoute($middleware, $user))->toBeFalse();
})->with('role gates');

test('a role gate refuses a user of the warehouse app, which has no store type', function (string $middleware, array $allowedTypes) {
    $request = Request::create('/guarded');
    $request->setUserResolver(fn () => User::factory()->make());

    try {
        $reached = (new $middleware)->handle($request, fn () => response('reached'))->getContent() === 'reached';
    } catch (HttpException) {
        $reached = false;
    }

    expect($reached)->toBeFalse();
})->with('role gates');

test('a banned store admin is turned away from the store admin panel', function () {
    $admin = createStoreAdmin();
    $admin->forceFill(['banned' => 1])->save();
    actingAsStoreUser($admin->fresh());

    $this->get(route('shop.admin.dashboard'))->assertNotFound();
});

test('the app ban gate answers a banned user with a 403 and revokes the token they used', function () {
    $banned = createStoreUser('customer');
    $banned->forceFill(['banned' => 1])->save();
    $token = storeApiToken($banned);
    // Noise: another customer's token must survive.
    $bystanderToken = storeApiToken(createStoreUser('customer'));

    $this->withToken($token)->getJson('/temp/api/v2/wallet/history')
        ->assertForbidden()
        ->assertJsonPath('result', false)
        ->assertJsonPath('result_key', 'banned');

    expect(DB::table('wms_personal_access_tokens')->count())->toBe(1);

    $this->withToken($token)->getJson('/temp/api/v2/wallet/history')->assertUnauthorized();
    expect($this->withToken($bystanderToken)->getJson('/temp/api/v2/wallet/history')->status())->not->toBe(403);
});

test('the app ban gate lets an unbanned user through', function () {
    $response = $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/temp/api/v2/wallet/history');

    expect($response->status())->not->toBe(403)
        ->and($response->json('result_key'))->toBeNull();
});

test('the app ban gate answers a banned user with no token to revoke instead of failing', function () {
    $banned = new StoreUser;
    $banned->banned = 1;
    $request = Request::create('/guarded');
    $request->setUserResolver(fn () => $banned);

    $response = (new IsAppUserUnbanned)->handle($request, fn () => response('reached'));

    expect($response->getStatusCode())->toBe(403);
});

test('the app ban gate lets a request with no user on to the auth middleware instead of failing', function () {
    $request = Request::create('/guarded');
    $request->setUserResolver(fn () => null);

    expect((new IsAppUserUnbanned)->handle($request, fn () => response('reached'))->getContent())->toBe('reached');
});

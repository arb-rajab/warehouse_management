<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Every route that changes data in the store's admin (routes/shop-admin.php)
 * and seller (routes/shop-seller.php) areas, called by someone who must not
 * reach it: nobody signed in, or an account of another kind. Both areas gate on
 * the account type before any controller runs, so the request is refused and
 * not one row of any table changes.
 *
 * The per-record ownership rules (another seller's coupon, a conversation one is
 * not in, a staff account, a driver) are pinned in LegacyOwnershipGapsTest and
 * the converted modules' tests under tests/Feature/Store/Admin; this sweep is
 * what stops a newly added mutating route from skipping the gate altogether.
 *
 * @return list<RoutingRoute>
 */
function shopMutatingRoutes(string $uriPrefix): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_starts_with($route->uri(), $uriPrefix)
            && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [],
    ));
}

/**
 * A fingerprint of every row of every table, keyed by table name.
 *
 * @return array<string, string>
 */
function shopTableFingerprints(): array
{
    $ignored = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
    $fingerprints = [];

    foreach (Schema::getTables() as $table) {
        if (! in_array($table['name'], $ignored, true)) {
            $fingerprints[$table['name']] = md5(DB::table($table['name'])->get()->toJson());
        }
    }

    return $fingerprints;
}

/**
 * A seller with a shop, a coupon, a withdraw request and a conversation, so
 * the sweep has real rows a successful request could change or delete.
 */
function seedShopAccessFixtures(): void
{
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');

    createShopFor($seller, 100);
    createWithdrawRequestFor($seller, 10);
    couponFor($seller);
    createStoreOrder($customer, ['seller_id' => $seller->id]);
    DB::table('conversations')->insert([
        'sender_id' => $customer->id, 'receiver_id' => $seller->id, 'title' => 'Hello',
        'sender_viewed' => 1, 'receiver_viewed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('a caller of the wrong kind is refused on every mutating admin and seller route and changes nothing', function (string $uriPrefix, ?string $callerType, int $atLeast) {
    seedShopAccessFixtures();

    if ($callerType !== null) {
        actingAsStoreUser(createStoreUser($callerType));
    }

    $before = shopTableFingerprints();
    $routes = shopMutatingRoutes($uriPrefix);
    $notRefused = [];

    foreach ($routes as $route) {
        $verb = array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0];
        $response = $this->call($verb, '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri()));
        $status = $response->getStatusCode();

        // A guest on the admin area is sent to the login page; every other
        // refusal is the gate's 404 or a 401/403.
        $sentToLogin = $status === 302 && str_contains((string) $response->headers->get('Location'), 'login');

        if (! $sentToLogin && ! in_array($status, [401, 403, 404], true)) {
            $notRefused[] = "$status $verb {$route->uri()}";
        }
    }

    expect(count($routes))->toBeGreaterThan($atLeast)
        ->and($notRefused)->toBe([])
        ->and(array_keys(array_diff_assoc(shopTableFingerprints(), $before)))->toBe([]);
})->with([
    'a guest on the admin area' => ['temp/admin/', null, 150],
    'a customer on the admin area' => ['temp/admin/', 'customer', 150],
    'a seller on the admin area' => ['temp/admin/', 'seller', 150],
    'a guest on the seller area' => ['temp/seller/', null, 30],
    'a customer on the seller area' => ['temp/seller/', 'customer', 30],
    'an admin on the seller area' => ['temp/seller/', 'admin', 30],
]);

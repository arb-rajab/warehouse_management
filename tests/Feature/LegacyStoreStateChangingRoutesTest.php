<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The legacy store changed data on GET: its delete, ban, approve, duplicate,
 * restore and similar endpoints were plain links. A GET can be fired by any page
 * the signed-in user visits (a link, a redirect, a prefetch), and the session
 * cookie goes with a top-level navigation, so an admin who opened a hostile page
 * could delete a brand, ban a customer or approve a seller without ever
 * clicking in the store. The routes below take POST now, and the CSRF token
 * rides along: a link marked `data-method="post"` submits a form through
 * aiz-core.js, and the confirmation modals pass the mark on to their button.
 * A module moved to Inertia deletes with DELETE instead (`roles.destroy`,
 * `staffs.destroy`), which Inertia sends with its CSRF header.
 *
 * Left as GET on purpose: the signed email-verification link, the terms
 * activation link and the payment gateways' redirects back to the shop.
 *
 * @var list<string>
 */
const STATE_CHANGING_STORE_ROUTES = [
    'account_delete',
    'addresses.destroy',
    'addresses.set_default',
    'admin.notifications.delete',
    'admin.rep.payments.accept',
    'admin.rep.payments.reject',
    'admin.rep.visits.destroy',
    'attributes.destroy',
    'blog-category.destroy',
    'blog.destroy',
    'brands.destroy',
    'cache.clear',
    'carriers.destroy',
    'categories.destroy',
    'cities.destroy',
    'classified_products.destroy',
    'colors.destroy',
    'compare.reset',
    'conversations.destroy',
    'coupon.destroy',
    'custom-pages.destroy',
    'customer_packages.destroy',
    'customer_products.destroy',
    'customers.ban',
    'customers.change_activity_status',
    'customers.destroy',
    'customers.important',
    'customers.not_important',
    'customers.restore',
    'customers.sendAgreement',
    'customers.verify',
    'destroy-attribute-value',
    'digitalproducts.destroy',
    'drivers.destroy',
    'dynamic-popups.destroy',
    'flash_deals.destroy',
    'followed_seller.remove',
    'followed_seller.store',
    'languages.destroy',
    'offer_deals.destroy',
    'offer_deals.store',
    'offers.destroy',
    'orders.destroy',
    'pick_up_points.destroy',
    'products.destroy',
    'products.duplicate',
    'purchase_history.destroy',
    'rep.packages.destroy',
    'roles.destroy',
    'seller.addresses.destroy',
    'seller.addresses.set_default',
    'seller.coupon.destroy',
    'seller.digitalproducts.destroy',
    'seller.my_uploads.destroy',
    'seller.products.destroy',
    'seller.products.duplicate',
    'sellers.approve',
    'sellers.ban',
    'sellers.destroy',
    'sellers.reject',
    'staffs.destroy',
    'subscriber.destroy',
    'tax.destroy',
    'trips.destroy',
    'trucks.destroy',
    'uploaded-files.destroy',
    'zones.destroy',
];

/** Actions that read or are reached by a link from outside the shop. */
const GET_ROUTES_THAT_MAY_STAY = [
    'CustomerController@change_password',
    'VerificationController@verify',
    'NagadController@verify',
];

/**
 * @return list<RoutingRoute>
 */
function storeWebGetRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_starts_with($route->uri(), 'temp')
            && ! str_starts_with($route->uri(), 'temp/api')
            && in_array('GET', $route->methods(), true),
    ));
}

function storeRoutePath(RoutingRoute $route): string
{
    return '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
}

test('every state-changing store route takes POST (DELETE once on Inertia) and no GET', function () {
    $wrong = [];

    foreach (STATE_CHANGING_STORE_ROUTES as $name) {
        $route = Route::getRoutes()->getByName($name);

        if ($route === null || ! in_array($route->methods(), [['POST'], ['DELETE']], true)) {
            $wrong[] = $name;
        }
    }

    expect(count(STATE_CHANGING_STORE_ROUTES))->toBeGreaterThan(60)
        ->and($wrong)->toBe([]);
});

test('no store web route that deletes, bans, approves or otherwise changes data answers GET', function () {
    $pattern = '/@(destroy\w*|delete\w*|order_cancel|approve_\w+|reject_\w+|ban|restore|duplicate|change_\w+|accept|reject|set_default|remove|reset|clearCache|account_deletion|verify|important|not_important|terms_agreements|store|update\w*)$/i';
    $offenders = [];

    foreach (storeWebGetRoutes() as $route) {
        $action = class_basename($route->getActionName());

        if (preg_match($pattern, $action) && ! in_array($action, GET_ROUTES_THAT_MAY_STAY, true)) {
            $offenders[] = $action.' '.$route->uri();
        }
    }

    expect(count(storeWebGetRoutes()))->toBeGreaterThan(150)
        ->and($offenders)->toBe([]);
});

test('a GET to a state-changing route is refused and changes nothing', function () {
    $admin = createStoreAdmin();
    $brandId = DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme']);
    actingAsStoreUser($admin);

    $this->get("/temp/admin/brands/destroy/$brandId")->assertMethodNotAllowed();
    $this->assertDatabaseHas('brands', ['id' => $brandId]);

    $this->post("/temp/admin/brands/destroy/$brandId")->assertRedirect(route('brands.index'));
    $this->assertDatabaseMissing('brands', ['id' => $brandId]);
});

test('a visitor who is not signed in cannot delete through the POST route either', function () {
    $brandId = DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme']);

    expect($this->post("/temp/admin/brands/destroy/$brandId")->getStatusCode())->toBeIn([302, 401, 403]);
    $this->assertDatabaseHas('brands', ['id' => $brandId]);
});

test('a signed-in admin\'s GET to a state-changing route is refused (405, or 404 where a one-segment path falls to the CMS page route)', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));
    $notRefused = [];

    foreach (STATE_CHANGING_STORE_ROUTES as $name) {
        $route = Route::getRoutes()->getByName($name);
        $status = $this->get(storeRoutePath($route))->getStatusCode();

        if (! in_array($status, [404, 405], true)) {
            $notRefused[] = "$status $name";
        }
    }

    expect($notRefused)->toBe([]);
});

test('every link to a state-changing route is marked to submit as a POST', function () {
    // The links that open a confirmation modal carry no mark; the modal's own button does.
    $modalButtons = [
        'resources/views/shop/frontend/partials/account_delete_modal.blade.php' => ['id="account_delete_link"'],
        'resources/views/shop/backend/sellers/index.blade.php' => ['id="confirmation"', 'id="confirmationunban"'],
    ];
    $openedByModal = ['customers.ban', 'sellers.ban', 'account_delete'];
    $offenders = [];

    foreach ($modalButtons as $path => $buttons) {
        $source = file_get_contents(base_path($path));

        foreach ($buttons as $button) {
            if (! preg_match('/'.preg_quote($button, '/').'\s+data-method="post"/', $source)) {
                $offenders[] = "$path: $button needs data-method=\"post\"";
            }
        }
    }

    foreach (sourceFilesUnder(['resources/views']) as $path => $source) {
        foreach (array_diff(STATE_CHANGING_STORE_ROUTES, $openedByModal) as $name) {
            $usage = '/route\(\s*[\'"]'.preg_quote($name, '/').'[\'"]/';

            foreach (preg_split('/(?=<(?:a|form)\b)/', $source) as $element) {
                if (preg_match($usage, $element)
                    && ! str_contains($element, 'data-method="post"')
                    && ! preg_match('/^<form\b[^>]*method="post"/i', $element)) {
                    $offenders[] = "$path: $name";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the confirmation modals pass the post mark on, and a marked link submits a form with the csrf token', function () {
    $script = file_get_contents(public_path('assets/js/aiz-core.js'));

    expect($script)->toContain('a[data-method="post"]')
        ->and($script)->toContain('name: "_token"')
        ->and($script)->toContain('value: AIZ.data.csrf')
        ->and(substr_count($script, 'carryMethod('))->toBeGreaterThanOrEqual(5);
});

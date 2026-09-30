<?php

use App\Http\Middleware\WmsAuthMiddleware;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * routes/shop-api_warehouse.php shipped with 4 of its 11 routes carrying no
 * middleware at all — unauthenticated, exposing customer PII and orders —
 * before this app ever mounted it (see
 * .ai/rules/legacy-albaraka-holland.md section 0.3). Every route there now
 * carries wms.auth. This is the regression test for that fix: it proves the
 * middleware itself rejects a missing/wrong token and lets a correct one
 * through, and separately proves every one of those 11 routes is actually
 * wired to it, over real HTTP, rather than trusting the route file to stay
 * that way.
 */
test('WmsAuthMiddleware rejects a request with no private-token header', function () {
    $called = false;

    $response = (new WmsAuthMiddleware)->handle(
        Request::create('/api/v2/warehouse/customers'),
        function () use (&$called) {
            $called = true;

            return new Response('ok');
        },
    );

    expect($called)->toBeFalse()
        ->and($response->getData(true))->toBe([
            'result' => false,
            'message' => 'invalid token, who are you?',
            'status' => 401,
        ]);
});

test('WmsAuthMiddleware rejects an incorrect private-token header', function () {
    $called = false;

    $request = Request::create('/api/v2/warehouse/customers');
    $request->headers->set('private-token', 'not-the-real-token');

    (new WmsAuthMiddleware)->handle($request, function () use (&$called) {
        $called = true;

        return new Response('ok');
    });

    expect($called)->toBeFalse();
});

test('WmsAuthMiddleware admits the configured private-token and passes the request through', function () {
    $called = false;

    $request = Request::create('/api/v2/warehouse/customers');
    $request->headers->set('private-token', config('app.wms_token'));

    $response = (new WmsAuthMiddleware)->handle($request, function ($req) use (&$called) {
        $called = true;

        return new Response('ok');
    });

    expect($called)->toBeTrue()
        ->and($response->getContent())->toBe('ok');
});

test('every routes/shop-api_warehouse.php route rejects a request with no private-token header', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    $response->assertJson([
        'result' => false,
        'message' => 'invalid token, who are you?',
    ]);
})->with([
    'GET /customers' => ['GET', '/temp/api/v2/warehouse/customers'],
    'GET /reps' => ['GET', '/temp/api/v2/warehouse/reps'],
    'GET /customer/{id}' => ['GET', '/temp/api/v2/warehouse/customer/1'],
    'POST /customer/update/{id}' => ['POST', '/temp/api/v2/warehouse/customer/update/1'],
    'GET /orders' => ['GET', '/temp/api/v2/warehouse/orders'],
    'GET /order/{id}' => ['GET', '/temp/api/v2/warehouse/order/1'],
    'GET /order_items/{id}' => ['GET', '/temp/api/v2/warehouse/order_items/1'],
    'POST /update_order' => ['POST', '/temp/api/v2/warehouse/update_order'],
    'POST /order/update-customer' => ['POST', '/temp/api/v2/warehouse/order/update-customer'],
    'GET /products' => ['GET', '/temp/api/v2/warehouse/products'],
    'GET /categories' => ['GET', '/temp/api/v2/warehouse/categories'],
]);

test('an unauthenticated customer update request does not change the customer', function () {
    // Only columns the current users table migration actually has (see
    // .ai/rules/legacy-albaraka-holland.md — schema migrations covered
    // shop.sql's tables, not the legacy repo's later `alter table users add
    // company_name` etc. migrations, so several of StoreUser's own
    // $fillable entries, like company_name, have no matching column yet).
    $customer = StoreUser::create([
        'name' => 'Original Name',
        'email' => 'customer@example.com',
        'user_type' => 'customer',
        'phone' => '0000000000',
    ]);

    $this->postJson("/temp/api/v2/warehouse/customer/update/{$customer->id}", [
        'name' => 'Attacker-Supplied Name',
        'phone' => '9999999999',
    ]);

    expect($customer->fresh())
        ->name->toBe('Original Name')
        ->phone->toBe('0000000000');
});

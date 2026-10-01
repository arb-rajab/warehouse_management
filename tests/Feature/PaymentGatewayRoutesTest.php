<?php

use App\Http\Controllers\Payment\PayhereController;
use App\Models\Wallet;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * The legacy store's hand-rolled payment gateways answered at /temp whether or
 * not the store used them, and their callbacks are unauthenticated routes that
 * finish orders, wallet top-ups and packages. None is enabled by default now
 * (config/store.php, `enabled_payment_gateways`): every route that reaches a
 * gateway controller is a 404 until its gateway is listed.
 *
 * PayHere's notify handlers also verified their signature against
 * env('PAYHERE_SECRET'), which is null once config is cached, so anyone could
 * sign a payment-complete call; and they took the merchant id from the request.
 */
function gatewayRoutes(): array
{
    $gatewayByController = [];

    foreach (config('store.payment_gateway_controllers') as $gateway => $controllers) {
        foreach ($controllers as $controller) {
            $gatewayByController[$controller] = $gateway;
        }
    }

    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => isset($gatewayByController[$route->getControllerClass()]))
        ->map(fn (RoutingRoute $route) => [$route, $gatewayByController[$route->getControllerClass()]])
        ->values()
        ->all();
}

test('every route that reaches a payment gateway controller carries that gateway\'s guard', function () {
    $unguarded = collect(gatewayRoutes())
        ->reject(fn (array $pair) => in_array('payment.gateway:'.$pair[1], $pair[0]->gatherMiddleware(), true))
        ->map(fn (array $pair) => $pair[0]->uri())
        ->values()
        ->all();

    expect(count(gatewayRoutes()))->toBeGreaterThan(40)
        ->and($unguarded)->toBe([]);
});

test('a new controller under Payment/ is a gateway the guard knows about, or is listed as not one', function () {
    $mapped = collect(config('store.payment_gateway_controllers'))->flatten()->map(fn (string $class) => class_basename($class))->all();
    $notGateways = ['CashOnDeliveryController', 'WalletController'];

    $unknown = collect(glob(app_path('Http/Controllers/Payment/*Controller.php')))
        ->map(fn (string $path) => basename($path, '.php'))
        ->reject(fn (string $name) => in_array($name, [...$mapped, ...$notGateways], true))
        ->values()
        ->all();

    expect($unknown)->toBe([])
        ->and(collect(config('store.payment_gateway_controllers'))->flatten()->every(fn (string $class) => class_exists($class)))->toBeTrue();
});

test('with no gateway enabled every gateway route is a 404, for a signed-in customer too', function () {
    $customer = createStoreUser('customer');
    $token = storeApiToken($customer);
    actingAsStoreUser($customer);
    $notFound = 0;
    $answered = [];

    foreach (gatewayRoutes() as [$route]) {
        $verb = array_values(array_diff($route->methods(), ['HEAD']))[0];
        $status = $this->withToken($token)->call($verb, '/'.preg_replace('/\{[^}?]+\??\}/', '1', $route->uri()))->getStatusCode();

        $status === 404 ? $notFound++ : $answered[] = "$status $verb {$route->uri()}";
    }

    expect($answered)->toBe([])
        ->and($notFound)->toBe(count(gatewayRoutes()));
});

test('enabling one gateway opens its routes and no other gateway\'s', function () {
    config(['store.enabled_payment_gateways' => ['payhere']]);
    actingAsStoreUser(createStoreUser('customer'));

    expect($this->post(route('payhere.checkout.cancel'))->getStatusCode())->not->toBe(404)
        ->and($this->get(route('bkash.callback'))->getStatusCode())->toBe(404)
        ->and($this->post('/temp/sslcommerz/ipn')->getStatusCode())->toBe(404);
});

function payhereNotification(string $secret, array $overrides = []): array
{
    $post = $overrides + [
        'merchant_id' => 'MERCHANT-1', 'order_id' => '1001', 'payhere_amount' => '25.00',
        'payhere_currency' => 'LKR', 'status_code' => '2', 'custom_1' => '1',
    ];

    $post['md5sig'] ??= strtoupper(md5($post['merchant_id'].$post['order_id'].$post['payhere_amount'].$post['payhere_currency'].$post['status_code'].strtoupper(md5($secret))));

    return $post;
}

function payhereIsAuthentic(array $post): bool
{
    return (new ReflectionMethod(PayhereController::class, 'notificationIsAuthentic'))->invoke(null, $post);
}

test('a PayHere notification is authentic only when signed with the configured secret for the configured merchant', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);

    expect(payhereIsAuthentic(payhereNotification('real-secret')))->toBeTrue()
        ->and(payhereIsAuthentic(array_merge(payhereNotification('real-secret'), ['md5sig' => strtolower(payhereNotification('real-secret')['md5sig'])])))->toBeTrue()
        ->and(payhereIsAuthentic(payhereNotification('another-secret')))->toBeFalse()
        ->and(payhereIsAuthentic(payhereNotification('')))->toBeFalse()
        ->and(payhereIsAuthentic(payhereNotification('real-secret', ['merchant_id' => 'MERCHANT-2'])))->toBeFalse()
        ->and(payhereIsAuthentic(payhereNotification('real-secret', ['status_code' => '-2'])))->toBeFalse()
        ->and(payhereIsAuthentic(array_merge(payhereNotification('real-secret'), ['payhere_amount' => '2500.00'])))->toBeFalse()
        ->and(payhereIsAuthentic([]))->toBeFalse();
});

test('a PayHere notification is refused while no secret or merchant is configured, even one signed with the empty secret', function () {
    config(['services.payhere.secret' => null, 'services.payhere.merchant_id' => 'MERCHANT-1']);
    expect(payhereIsAuthentic(payhereNotification('')))->toBeFalse();

    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => null]);
    expect(payhereIsAuthentic(payhereNotification('real-secret', ['merchant_id' => ''])))->toBeFalse();
});

test('a forged PayHere wallet notification adds nothing to the wallet', function () {
    config(['store.enabled_payment_gateways' => ['payhere'], 'services.payhere.secret' => null, 'services.payhere.merchant_id' => null]);
    $customer = createStoreUser('customer');
    $customer->forceFill(['balance' => 0])->save();

    $_POST = payhereNotification('', ['payhere_amount' => '1000.00', 'custom_1' => (string) $customer->id]);

    try {
        $this->post(route('payhere.wallet.notify'));
    } finally {
        $_POST = [];
    }

    expect($customer->fresh()->balance)->toEqual(0)
        ->and(Wallet::count())->toBe(0);
});

test('a correctly signed PayHere wallet notification credits the wallet once', function () {
    config(['store.enabled_payment_gateways' => ['payhere'], 'services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);
    $customer = createStoreUser('customer');
    $customer->forceFill(['balance' => 0])->save();

    $_POST = payhereNotification('real-secret', ['payhere_amount' => '25.00', 'custom_1' => (string) $customer->id]);

    try {
        $this->post(route('payhere.wallet.notify'))->assertOk();
    } finally {
        $_POST = [];
    }

    expect($customer->fresh()->balance)->toEqual(25)
        ->and(Wallet::count())->toBe(1);
});

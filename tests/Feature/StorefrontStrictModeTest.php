<?php

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The web storefront twin of StoreApiStrictModeTest: Eloquent's strict mode
 * (local and testing) refuses a lazy load on a result set of two or more
 * models, so a page that lists several products, addresses, order lines or
 * messages threw for a real customer while the one-row tests stayed green.
 * Everything is built in twos and threes by strictApiStore() (tests/Pest.php),
 * and the last test drives every parameterless GET route of /temp as each kind
 * of caller, so a page added later is covered without a test of its own.
 */
function storefrontStrictCause(Throwable $exception): ?Throwable
{
    for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
        if ($cause instanceof LazyLoadingViolationException || $cause instanceof MissingAttributeException) {
            return $cause;
        }
    }

    return null;
}

// Pages that render a list of 2+ models for a signed-in customer, with a
// fragment of what the list holds so a page that renders empty fails too.
dataset('customer pages', [
    'dashboard' => ['/temp/dashboard', 'Snacks'],
    'profile with its address book' => ['/temp/profile', 'A1'],
    'checkout shipping step' => ['/temp/checkout', 'A1'],
    'wishlist' => ['/temp/wishlists', 'Prod 1'],
    'followed sellers' => ['/temp/followed-seller', 'Seller Shop'],
    'wallet' => ['/temp/wallet', 'Cash'],
    'conversations' => ['/temp/conversations', 'hi'],
    'support tickets' => ['/temp/support_ticket', 'Ticket 1'],
    'purchase history' => ['/temp/purchase_history', null],
]);

test('a customer page that lists several records renders for a customer with two or more of everything', function (string $path, ?string $text) {
    $store = strictApiStore();
    actingAsStoreUser($store['customer']);

    $response = $this->get($path);

    $response->assertOk();
    if ($text !== null) {
        $response->assertSee($text);
    }
})->with('customer pages');

test('the order, ticket and conversation pages render their several lines, replies and messages', function () {
    $store = strictApiStore();
    actingAsStoreUser($store['customer']);
    $ticket = DB::table('tickets')->value('id');
    $conversation = DB::table('conversations')->value('id');

    $this->get('/temp/purchase_history/details/'.encrypt($store['orders'][0]->id))->assertOk()->assertSee('Prod 2');
    $this->get('/temp/support_ticket/'.encrypt($ticket))->assertOk()->assertSee('second');
    $this->get('/temp/conversations/'.encrypt($conversation))->assertOk()->assertSee('yo');
    $this->get('/temp/invoice/'.$store['orders'][0]->id)->assertOk();
});

test('the home, deal and in-house pages list several products, from the cache as from the database', function () {
    useSerializingCache();
    $store = strictApiStore();

    foreach (['miss', 'hit'] as $read) {
        foreach (['/temp', '/temp/todays-deal', '/temp/inhouse'] as $path) {
            $this->get($path)->assertOk()->assertSee('Prod 1');
        }
    }

    foreach ($store['products'] as $product) {
        $this->get('/temp/product/'.$product->slug)->assertOk();
    }
});

test('the catalogue pages list several products, shops, brands and categories', function () {
    $store = strictApiStore();

    foreach (['/temp/search', '/temp/category/snacks', '/temp/brand/acme', '/temp/flash-deals', '/temp/flash-deal/flash-1', '/temp/shop/seller-shop', '/temp/brands'] as $path) {
        expect($this->get($path)->getStatusCode())->toBeIn([200], $path);
    }
});

test('a seller sees the orders, conversations and commissions that hold several lines', function () {
    $store = strictApiStore();
    actingAsStoreUser($store['seller']);

    $this->get('/temp/seller/orders/'.encrypt($store['orders'][0]->id))->assertOk()->assertSee('Prod 3');
    $this->get('/temp/seller/commission-history')->assertOk();
    $this->get('/temp/seller/conversations')->assertOk();
    $this->get('/temp/seller/invoice/'.$store['orders'][0]->id)->assertOk();
});

test('a representative sees the receipts and customers of several rows', function () {
    $store = strictApiStore();
    actingAsStoreUser($store['rep']);

    $this->get('/temp/representative/payments/index')->assertOk()->assertSee('R-p1');
});

test('the cart summary and coupon fragments price several cart lines', function () {
    $store = strictApiStore();
    actingAsStoreUser($store['customer']);
    $this->withoutMiddleware(ThrottleRequests::class);
    Cart::query()->update(['address_id' => addStoreAddress($store['customer']->id)]);

    $this->post('/temp/checkout/apply_coupon_code', ['code' => 'C1'])->assertOk()->assertJsonStructure(['response_message', 'html']);
    $this->post('/temp/checkout/remove_coupon_code', ['code' => 'C1'])->assertOk()->assertSee('Prod 1');
});

dataset('placing customers', ['a customer' => ['customer'], 'a representative' => ['rep']]);

test('an order of several cart lines is placed from the checkout, brand offers included', function (string $who) {
    $store = strictApiStore();
    $user = $store[$who];
    $address = addStoreAddress($user->id);
    Cart::query()->forceDelete();
    foreach (array_slice($store['products'], 0, 3) as $product) {
        Cart::create([
            'user_id' => $user->id, 'owner_id' => $product->user_id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '', 'price' => 2.5,
            'address_id' => $address, 'shipping_type' => 'home_delivery', 'for_customer' => $who === 'rep' ? $store['customer']->id : null,
        ]);
    }
    $before = Order::count();
    actingAsStoreUser($user);

    $this->post('/temp/checkout/payment', ['payment_option' => 'cash_on_delivery'])->assertRedirect(route('order_confirmed'));
    $this->get(route('order_confirmed'))->assertOk();

    // One order per seller (the in-house products and the seller's).
    expect(Order::count() - $before)->toBe(2)
        ->and(Cart::where('user_id', $user->id)->count())->toBe(0);
})->with('placing customers');

test('no GET route of the storefront trips Eloquent strict mode for any kind of caller', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->withoutExceptionHandling();
    $store = strictApiStore();
    $skipped = '#logout|account-deletion|/delete|wishlists/remove|duplicate|sslcommerz|bkash|nagad|iyzico|^temp/api#';
    $callers = ['guest' => null, 'customer' => $store['customer'], 'rep' => $store['rep'], 'seller' => $store['seller'], 'admin' => createStoreAdmin(true)];
    $violations = [];

    foreach ($callers as $name => $user) {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'temp') || ! in_array('GET', $route->methods(), true) || preg_match($skipped, $uri)) {
                continue;
            }
            app('auth')->forgetGuards();
            $user ? actingAsStoreUser($user) : null;
            // A parameter becomes a real slug where the page looks one up and 1
            // elsewhere; a page that decrypts its id gets it encrypted on a second try.
            $fill = fn (bool $encrypted): string => '/'.preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($uri, $store, $encrypted): string {
                $value = match (true) {
                    $match[1] === 'slug' && str_contains($uri, 'product/') => $store['products'][0]->slug,
                    $match[1] === 'slug' && str_contains($uri, 'flash-deal') => 'flash-1',
                    $match[1] === 'slug' && str_contains($uri, 'shop/') => 'seller-shop',
                    $match[1] === 'type' => 'all-products',
                    $match[1] === 'category_slug' => 'snacks',
                    $match[1] === 'brand_slug' => 'acme',
                    default => '1',
                };

                return $encrypted ? encrypt($value) : $value;
            }, $uri);

            try {
                try {
                    $this->get($fill(false));
                } catch (DecryptException) {
                    $this->get($fill(true));
                }
            } catch (Throwable $exception) {
                // Anything else (a signature, a missing record, a handler not written) is a different defect.
                if ($cause = storefrontStrictCause($exception)) {
                    $violations["$name GET /$uri"] = $cause->getMessage();
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

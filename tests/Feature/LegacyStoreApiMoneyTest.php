<?php

use App\Models\Cart;
use App\Models\ClubPoint;
use App\Models\Order;
use App\Models\StoreUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Four mobile-API endpoints found a record by an id from the request and acted
 * on it whoever it belonged to: converting club points into wallet balance (any
 * account's points, any number of times), submitting an offline payment for any
 * order (and the route needed no sign-in), and changing a cart line's quantity
 * (any account's, to any number, negative included).
 */
function createClubPointTables(): void
{
    Schema::create('club_points', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('order_id')->nullable();
        $table->double('points')->default(0);
        $table->tinyInteger('convert_status')->default(0);
        $table->timestamps();
    });
    Schema::create('club_point_details', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('club_point_id');
        $table->unsignedBigInteger('product_id')->nullable();
        $table->double('point')->default(0);
        $table->double('converted_amount')->default(0);
        $table->tinyInteger('refunded')->default(0);
        $table->timestamps();
    });
    DB::table('business_settings')->insert(['type' => 'club_point_convert_rate', 'value' => '10']);
}

function clubPointFor(StoreUser $customer, float $point): ClubPoint
{
    $clubPoint = new ClubPoint;
    $clubPoint->forceFill(['user_id' => $customer->id, 'points' => $point])->save();
    DB::table('club_point_details')->insert(['club_point_id' => $clubPoint->id, 'point' => $point, 'refunded' => 0]);

    return $clubPoint;
}

test('club points convert into the wallet of the account that owns them, once', function () {
    createClubPointTables();
    $owner = createStoreUser('customer');
    $other = createStoreUser('customer');
    $clubPoint = clubPointFor($owner, 100);
    $token = storeApiToken($owner);

    $this->withToken($token)->postJson('/temp/api/v2/clubpoint/convert-into-wallet', ['id' => $clubPoint->id])
        ->assertOk()->assertJsonPath('success', true);
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/clubpoint/convert-into-wallet', ['id' => $clubPoint->id])
        ->assertOk()->assertJsonPath('success', false);

    expect((float) $owner->fresh()->balance)->toBe(10.0)
        ->and((float) $other->fresh()->balance)->toBe(0.0)
        ->and(DB::table('wallets')->where('user_id', $owner->id)->count())->toBe(1);
});

test('another account\'s club points cannot be converted into your wallet', function () {
    createClubPointTables();
    $victim = createStoreUser('customer');
    $thief = createStoreUser('customer');
    $clubPoint = clubPointFor($victim, 100);

    $this->withToken(storeApiToken($thief))->postJson('/temp/api/v2/clubpoint/convert-into-wallet', ['id' => $clubPoint->id])
        ->assertOk()->assertJsonPath('success', false);

    expect((float) $thief->fresh()->balance)->toBe(0.0)
        ->and((int) $clubPoint->fresh()->convert_status)->toBe(0)
        ->and(DB::table('wallets')->count())->toBe(0);
});

test('an offline payment is submitted for your own unpaid order only', function () {
    addManualPaymentColumnsToOrders();

    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $unpaid = createStoreOrder($owner, ['payment_status' => 'unpaid']);
    $paid = createStoreOrder($owner, ['payment_status' => 'paid']);
    $payload = fn (Order $order) => ['order_id' => $order->id, 'name' => 'A', 'amount' => 5, 'trx_id' => 'T1', 'payment_option' => 'manual_payment_1'];

    $this->postJson('/temp/api/v2/offline/payment/submit', $payload($unpaid))->assertUnauthorized();
    $this->withToken(storeApiToken($stranger))->postJson('/temp/api/v2/offline/payment/submit', $payload($unpaid))->assertJsonPath('result', false);
    expect($unpaid->fresh()->payment_status)->toBe('unpaid');

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($owner))->postJson('/temp/api/v2/offline/payment/submit', $payload($paid))->assertJsonPath('result', false);
    expect($paid->fresh()->payment_status)->toBe('paid');

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($owner))->postJson('/temp/api/v2/offline/payment/submit', $payload($unpaid))->assertJsonPath('result', true);
    expect($unpaid->fresh()->payment_status)->toBe('Submitted')
        ->and(Order::where('payment_status', 'Submitted')->count())->toBe(1);
});

test('a cart line changes quantity for its owner only, and only to a whole number of at least one', function () {
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $product = createStoreProduct();
    $line = Cart::create(['user_id' => $owner->id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '']);
    $token = storeApiToken($owner);

    $this->withToken(storeApiToken($stranger))->postJson('/temp/api/v2/carts/change-quantity', ['id' => $line->id, 'quantity' => 7])
        ->assertJsonPath('result', false);
    expect($line->fresh()->quantity)->toBe(2);

    foreach ([-3, 0, 'many', 1.5] as $bad) {
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/temp/api/v2/carts/change-quantity', ['id' => $line->id, 'quantity' => $bad])
            ->assertJsonPath('result', false);
        expect($line->fresh()->quantity)->toBe(2);
    }

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/carts/change-quantity', ['id' => $line->id, 'quantity' => 4])
        ->assertJsonPath('result', true);
    expect($line->fresh()->quantity)->toBe(4);
});

test('processing the cart skips lines that are not yours and refuses a bad quantity', function () {
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $product = createStoreProduct();
    $theirs = Cart::create(['user_id' => $owner->id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '']);

    $this->withToken(storeApiToken($stranger))->postJson('/temp/api/v2/carts/process', ['cart_ids' => (string) $theirs->id, 'cart_quantities' => '9']);
    expect($theirs->fresh()->quantity)->toBe(2);

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($owner))->postJson('/temp/api/v2/carts/process', ['cart_ids' => (string) $theirs->id, 'cart_quantities' => '-5'])
        ->assertJsonPath('result', false);
    expect($theirs->fresh()->quantity)->toBe(2);
});

test('adding to the cart refuses a quantity below one', function () {
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['min_qty' => 0]);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/carts/add', ['id' => $product->id, 'quantity' => -4, 'variant' => ''])
        ->assertJsonPath('result', false);

    expect(Cart::where('user_id', $customer->id)->count())->toBe(0);
});

test('an offline payment needs an amount, a transaction id and clean proof, and is accepted once', function () {
    addManualPaymentColumnsToOrders();
    $owner = createStoreUser('customer');
    $order = createStoreOrder($owner, ['payment_status' => 'unpaid']);
    $noise = createStoreOrder($owner, ['payment_status' => 'unpaid']);
    $valid = ['order_id' => $order->id, 'name' => 'A', 'amount' => 5, 'trx_id' => 'T1', 'payment_option' => 'manual_payment_1'];
    $token = storeApiToken($owner);

    foreach ([
        ['amount' => -5], ['amount' => 0], ['amount' => 'lots'], ['trx_id' => null], ['name' => null],
        ['photo' => '<img src=x>'], ['payment_option' => null], ['order_id' => 'abc'],
    ] as $forged) {
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/temp/api/v2/offline/payment/submit', [...$valid, ...$forged])->assertUnprocessable();
    }
    expect($order->fresh()->payment_status)->toBe('unpaid');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/offline/payment/submit', [...$valid, 'photo' => '42'])->assertJsonPath('result', true);
    expect(json_decode($order->fresh()->manual_payment_data, true))->toBe(['name' => 'A', 'amount' => 5, 'trx_id' => 'T1', 'photo' => '42']);

    // A second submission would overwrite the first one's proof.
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/offline/payment/submit', [...$valid, 'trx_id' => 'T2'])->assertJsonPath('result', false);

    expect(json_decode($order->fresh()->manual_payment_data, true)['trx_id'])->toBe('T1')
        ->and($noise->fresh()->payment_status)->toBe('unpaid');
});

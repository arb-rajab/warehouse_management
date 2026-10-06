<?php

use App\Models\Order;
use App\Models\StoreUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery-boy API took a delivery boy's id from the URL or the request and an
 * order id from the request, for any signed-in account: a customer could read a
 * delivery boy's earnings, mark any order delivered (credited to whichever
 * delivery boy they named, every time they repeated it) or put in a cancellation
 * request. The routes need a delivery boy now, who sees and changes their own
 * assigned orders only.
 */
function createDeliveryBoyTables(): void
{
    Schema::create('delivery_boys', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->double('total_earning')->default(0);
        $table->double('total_collection')->default(0);
        $table->timestamps();
    });
    Schema::create('delivery_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('delivery_boy_id');
        $table->string('delivery_status')->nullable();
        $table->string('payment_type')->nullable();
        $table->double('earning')->default(0);
        $table->double('collection')->default(0);
        $table->timestamps();
    });

    foreach ([
        'assign_delivery_boy' => fn (Blueprint $table) => $table->unsignedBigInteger('assign_delivery_boy')->nullable(),
        'cancel_request' => fn (Blueprint $table) => $table->tinyInteger('cancel_request')->default(0),
        'cancel_request_at' => fn (Blueprint $table) => $table->dateTime('cancel_request_at')->nullable(),
        'delivery_history_date' => fn (Blueprint $table) => $table->dateTime('delivery_history_date')->nullable(),
    ] as $column => $define) {
        if (! Schema::hasColumn('orders', $column)) {
            Schema::table('orders', $define);
        }
    }

    DB::table('business_settings')->insert([
        ['type' => 'delivery_boy_payment_type', 'value' => 'commission'],
        ['type' => 'delivery_boy_commission', 'value' => '2'],
    ]);
}

function deliveryBoy(): StoreUser
{
    $boy = createStoreUser('delivery_boy');
    DB::table('delivery_boys')->insert(['user_id' => $boy->id, 'created_at' => now(), 'updated_at' => now()]);

    return $boy;
}

function assignedOrder(StoreUser $boy): Order
{
    return createStoreOrder(createStoreUser('customer'), ['payment_type' => 'wallet', 'assign_delivery_boy' => $boy->id]);
}

test('the delivery-boy routes need a delivery boy', function () {
    createDeliveryBoyTables();
    $boy = deliveryBoy();
    $customer = createStoreUser('customer');

    $this->getJson("/temp/api/v2/delivery-boy/collection/{$boy->id}")->assertUnauthorized();

    $token = storeApiToken($customer);
    $this->withToken($token)->getJson("/temp/api/v2/delivery-boy/collection/{$boy->id}")->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/delivery-boy/change-delivery-status', ['order_id' => 1, 'status' => 'delivered'])->assertForbidden();
});

test('a delivery boy reads their own figures and no one else\'s', function () {
    createDeliveryBoyTables();
    $boy = deliveryBoy();
    $other = deliveryBoy();
    $token = storeApiToken($boy);

    $this->withToken($token)->getJson("/temp/api/v2/delivery-boy/earning/{$other->id}")->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson("/temp/api/v2/delivery-boy/earning-summary/{$other->id}")->assertForbidden();
    app('auth')->forgetGuards();
    expect($this->withToken($token)->getJson("/temp/api/v2/delivery-boy/collection/{$boy->id}")->getStatusCode())->toBe(200);
});

test('a delivery boy changes the status of their own assigned orders only', function () {
    createDeliveryBoyTables();
    $boy = deliveryBoy();
    $rival = deliveryBoy();
    $mine = assignedOrder($boy);
    $theirs = assignedOrder($rival);
    $token = storeApiToken($boy);

    $this->withToken($token)->postJson('/temp/api/v2/delivery-boy/change-delivery-status', ['order_id' => $theirs->id, 'status' => 'delivered', 'delivery_boy_id' => $boy->id])->assertNotFound();
    expect($theirs->fresh()->delivery_status)->not->toBe('delivered')
        ->and((float) DB::table('delivery_boys')->where('user_id', $boy->id)->value('total_earning'))->toBe(0.0);

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/delivery-boy/change-delivery-status', ['order_id' => $mine->id, 'status' => 'shipped-to-mars'])->assertUnprocessable();

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/delivery-boy/change-delivery-status', ['order_id' => $mine->id, 'status' => 'delivered', 'delivery_boy_id' => $rival->id])->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/delivery-boy/change-delivery-status', ['order_id' => $mine->id, 'status' => 'delivered'])->assertOk();

    // Credited to the signed-in delivery boy, once.
    expect($mine->fresh()->delivery_status)->toBe('delivered')
        ->and((float) DB::table('delivery_boys')->where('user_id', $boy->id)->value('total_earning'))->toBe(2.0)
        ->and((float) DB::table('delivery_boys')->where('user_id', $rival->id)->value('total_earning'))->toBe(0.0);
});

test('a cancellation request is put in for an assigned order only', function () {
    createDeliveryBoyTables();
    $boy = deliveryBoy();
    $mine = assignedOrder($boy);
    $theirs = assignedOrder(deliveryBoy());
    $token = storeApiToken($boy);

    $this->withToken($token)->getJson("/temp/api/v2/delivery-boy/cancel-request/{$theirs->id}")->assertNotFound();
    expect((int) $theirs->fresh()->cancel_request)->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson("/temp/api/v2/delivery-boy/cancel-request/{$mine->id}")->assertOk();
    expect((int) $mine->fresh()->cancel_request)->toBe(1);
});

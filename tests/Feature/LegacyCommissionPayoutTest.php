<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * Paying a seller out of the admin panel moves the shop's `admin_to_pay`
 * balance, marks the withdraw request paid and records a payment, all in one
 * locked transaction. A forged or negative amount, a request that is already
 * paid or belongs to another seller, must leave every one of those untouched.
 */
function createShopFor(StoreUser $seller, float $balance): int
{
    return DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop of '.$seller->id, 'admin_to_pay' => $balance]);
}

function createWithdrawRequestFor(StoreUser $seller, float $amount, ?int $status = 0): int
{
    return DB::table('seller_withdraw_requests')->insertGetId(['user_id' => $seller->id, 'amount' => $amount, 'status' => $status, 'viewed' => 0]);
}

function shopBalance(int $shopId): float
{
    return (float) DB::table('shops')->where('id', $shopId)->value('admin_to_pay');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payoutForm(int $shopId, array $overrides = []): array
{
    return ['shop_id' => $shopId, 'amount' => 40, 'payment_option' => 'cash', ...$overrides];
}

test('an admin pays a seller out: the balance drops once and a payment is recorded', function () {
    $seller = createStoreUser('seller');
    $shopId = createShopFor($seller, 100);
    $noiseShopId = createShopFor(createStoreUser('seller'), 70);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, ['txn_code' => 'T-1']))->assertRedirect(route('sellers.index'));

    expect(shopBalance($shopId))->toBe(60.0)
        ->and(shopBalance($noiseShopId))->toBe(70.0)
        ->and(DB::table('payments')->count())->toBe(1)
        ->and((float) DB::table('payments')->where('seller_id', $seller->id)->value('amount'))->toBe(40.0);
});

test('paying out a withdraw request marks it paid and a second payout for it is refused', function () {
    $seller = createStoreUser('seller');
    $shopId = createShopFor($seller, 100);
    $requestId = createWithdrawRequestFor($seller, 40);
    $noiseRequestId = createWithdrawRequestFor(createStoreUser('seller'), 15);
    actingAsStoreUser(createStoreAdmin());
    $form = payoutForm($shopId, ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $requestId]);

    $this->post('/temp/admin/pay_to_seller', $form)->assertRedirect(route('withdraw_requests_all'));
    $this->post('/temp/admin/pay_to_seller', $form)->assertNotFound();

    expect(shopBalance($shopId))->toBe(60.0)
        ->and(DB::table('payments')->count())->toBe(1)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $requestId)->value('status'))->toBe(1)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $noiseRequestId)->value('status'))->toBe(0);
});

test('a withdraw request that belongs to another seller cannot be paid from this shop', function () {
    $seller = createStoreUser('seller');
    $shopId = createShopFor($seller, 100);
    $requestId = createWithdrawRequestFor(createStoreUser('seller'), 40);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $requestId]))
        ->assertNotFound();

    expect(shopBalance($shopId))->toBe(100.0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $requestId)->value('status'))->toBe(0);
});

test('a withdraw payout without a request id is rejected', function () {
    $shopId = createShopFor(createStoreUser('seller'), 100);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, ['payment_withdraw' => 'withdraw_request']))
        ->assertSessionHasErrors('withdraw_request_id');

    expect(shopBalance($shopId))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
});

test('a forged amount or shop is rejected and nothing moves', function (array $overrides) {
    $shopId = createShopFor(createStoreUser('seller'), 100);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, $overrides))->assertSessionHasErrors();

    expect(shopBalance($shopId))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
})->with([
    'negative amount' => [['amount' => -50]],
    'zero amount' => [['amount' => 0]],
    'text amount' => [['amount' => 'lots']],
    'missing amount' => [['amount' => null]],
    'unknown shop' => [['shop_id' => 9999]],
    'missing payment option' => [['payment_option' => null]],
]);

test('a seller paying the admin raises the balance by the amount', function () {
    $shopId = createShopFor(createStoreUser('seller'), 100);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, ['payment_option' => 'paypal']))->assertRedirect(route('sellers.index'));

    expect(shopBalance($shopId))->toBe(140.0)->and(DB::table('payments')->count())->toBe(1);
});

test('a customer and a guest cannot pay sellers out', function () {
    $seller = createStoreUser('seller');
    $shopId = createShopFor($seller, 100);
    $requestId = createWithdrawRequestFor($seller, 40);
    $form = payoutForm($shopId, ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $requestId]);

    $this->post('/temp/admin/pay_to_seller', $form)->assertRedirect();
    actingAsStoreUser(createStoreUser('customer'));
    $this->post('/temp/admin/pay_to_seller', $form)->assertNotFound();

    expect(shopBalance($shopId))->toBe(100.0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $requestId)->value('status'))->toBe(0);
});

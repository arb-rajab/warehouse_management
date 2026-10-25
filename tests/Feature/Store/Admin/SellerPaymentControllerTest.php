<?php

use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Paying a seller out of the admin panel moves the shop's `admin_to_pay`
 * balance, marks the withdraw request paid and records a payment, all in one
 * locked transaction. A forged or negative amount, a request that is already
 * paid or belongs to another seller, must leave every one of those untouched.
 */

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
        ->assertSessionHasErrors('withdraw_request_id');

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
    $shopId = createShopFor(createStoreUser('seller'), -100);
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/pay_to_seller', payoutForm($shopId, ['payment_option' => 'paypal']))->assertRedirect(route('sellers.index'));

    expect(shopBalance($shopId))->toBe(-60.0)->and(DB::table('payments')->count())->toBe(1);
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

test('payout methods wallet and messaging retain their existing money effects within the balance limit', function (string $method, float $delta, string $recorded) {
    Mail::fake();
    Notification::fake();
    $seller = createStoreUser('seller');
    $seller->forceFill(['balance' => 91])->save();
    $initial = $method === 'arbitrary_manual_option' ? -200 : 200;
    $shop = createShopFor($seller, $initial);
    actingAsStoreUser(createStoreAdmin());
    $this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['amount' => 150.25, 'payment_option' => $method, 'txn_code' => 'BANK-191']))->assertRedirect(route('sellers.index'));
    expect(shopBalance($shop))->toBe($initial + $delta)
        ->and((float) $seller->fresh()->balance)->toBe(91.0);
    $payment = DB::table('payments')->sole();
    expect((float) $payment->amount)->toBe(150.25)->and($payment->seller_id)->toBe($seller->id)
        ->and($payment->payment_method)->toBe($recorded)->and($payment->txn_code)->toBe('BANK-191')->and($payment->payment_details)->toBeNull();
    Mail::assertNothingSent();
    Notification::assertNothingSent();
})->with([['cash', -150.25, 'cash'], ['bank_payment', -150.25, 'bank_payment'], ['arbitrary_manual_option', 150.25, 'Seller paid to admin']]);

test('partial withdrawal payment is refused without changing its amount message or money', function () {
    Mail::fake();
    Notification::fake();
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 80);
    DB::table('seller_withdraw_requests')->where('id', $id)->update(['message' => '<b>original</b>']);
    actingAsStoreUser(createStoreAdmin());
    $this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['amount' => 12.5, 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id]))->assertSessionHasErrors('amount');
    $row = DB::table('seller_withdraw_requests')->find($id);
    expect(shopBalance($shop))->toBe(100.0)->and((int) $row->status)->toBe(0)->and((int) $row->viewed)->toBe(0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and((float) $row->amount)->toBe(80.0)->and($row->message)->toBe('<b>original</b>');
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

test('seller payment history and the seller scoped history render as Inertia', function () {
    actingAsStoreUser(createStoreStaff('seller_payment_history'));
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);
    $this->get(route('sellers.payment_histories'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Store/Admin/Sellers/Payments/Index')->where('seller', null));
    $this->get(route('sellers.payment_history', $seller))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Store/Admin/Sellers/Payments/Index')->has('payments.data', 0));
    $this->get(route('sellers.payment_history', 999999))->assertNotFound();
});

test('payment history payload preserves all methods txn codes amounts ordering and seller scoping', function () {
    actingAsStoreUser(createStoreStaff('seller_payment_history'));
    $seller = createStoreUser('seller');
    $seller->forceFill(['name' => 'Seller Name'])->save();
    $shop = createShopFor($seller, 100);
    DB::table('shops')->where('id', $shop)->update(['name' => 'Seller shop']);
    $id = DB::table('payments')->insertGetId(['seller_id' => $seller->id, 'amount' => 12.5, 'payment_method' => 'bank_payment', 'txn_code' => 'T-1', 'payment_details' => 'private', 'created_at' => '2026-10-19 08:00:00']);
    DB::table('payments')->insert(['seller_id' => createStoreUser('seller')->id, 'amount' => 100, 'payment_method' => 'cash']);
    $this->get(route('sellers.payment_history', $seller))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('filters', ['per_page' => 20])->has('seller', fn (Assert $s) => $s->where('id', $seller->id)->where('name', 'Seller Name')->where('shop_name', 'Seller shop'))
        ->has('payments.data', 1)->where('payments.meta.total', 1)
        ->has('payments.data.0', fn (Assert $row) => $row->where('id', $id)->where('date', '2026-10-19 08:00:00')->where('amount', single_price(12.5))->where('payment_method', 'Bank payment')->where('txn_code', 'T-1')
            ->has('seller', fn (Assert $s) => $s->where('id', $seller->id)->where('name', 'Seller Name')->where('shop_name', 'Seller shop'))));
});

test('both payment histories paginate twenty retain query and reject invalid per page', function (bool $scoped) {
    actingAsStoreUser(createStoreStaff('seller_payment_history'));
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);
    foreach (range(1, 23) as $i) {
        DB::table('payments')->insert(['seller_id' => $seller->id, 'amount' => $i, 'payment_method' => 'cash']);
    }
    $route = $scoped ? route('sellers.payment_history', $seller) : route('sellers.payment_histories');
    $this->get($route)->assertInertia(fn (Assert $p) => $p->has('payments.data', 20)->where('payments.meta.total', 23));
    $this->get($route.'?page=2')->assertInertia(fn (Assert $p) => $p->has('payments.data', 3));
    $this->get($route.'?per_page=10')->assertInertia(fn (Assert $p) => $p->has('payments.data', 10)->where('filters.per_page', 10)->where('payments.links.next', fn ($url) => str_contains($url, 'per_page=10')));
    $this->getJson($route.'?per_page=7')->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with([true, false]);

test('global history preserves orphan pagination totals while hiding their rows as Blade did', function () {
    actingAsStoreUser(createStoreStaff('seller_payment_history'));
    DB::table('payments')->insert(['seller_id' => 999999, 'amount' => 42, 'payment_method' => 'cash']);
    $this->get(route('sellers.payment_histories'))->assertInertia(fn (Assert $p) => $p->has('payments.data', 0)->where('payments.meta.total', 1));
});

test('seller payment modal returns the complete typed bank payload without making a payment', function () {
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
    $shop = Shop::findOrFail(createShopFor(createStoreUser('seller'), -12.5));
    $shop->forceFill(['bank_payment_status' => 1, 'cash_on_delivery_status' => 1, 'bank_name' => '<script>bank</script>', 'bank_acc_name' => 'Account', 'bank_acc_no' => '123', 'bank_routing_no' => '456'])->save();
    $this->postJson(route('sellers.payment_modal'), ['id' => $shop->id])->assertOk()->assertExactJson(['shop' => ['id' => $shop->id, 'due' => single_price(12.5), 'admin_to_pay' => -12.5, 'cash_enabled' => true, 'bank_enabled' => true, 'bank_name' => '<script>bank</script>', 'bank_acc_name' => 'Account', 'bank_acc_no' => '123', 'bank_routing_no' => '456'], 'withdrawal' => null]);
    $shop->bank_payment_status = 0;
    $shop->save();
    $this->postJson(route('sellers.payment_modal'), ['id' => $shop->id])->assertJsonPath('shop.bank_name', null);
    $shop->forceFill(['bank_payment_status' => 2, 'cash_on_delivery_status' => 2])->save();
    $this->postJson(route('sellers.payment_modal'), ['id' => $shop->id])->assertOk()->assertJsonPath('shop.bank_enabled', false)->assertJsonPath('shop.cash_enabled', false);
    $this->postJson(route('sellers.payment_modal'), ['id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('id');
    $this->postJson(route('sellers.payment_modal'))->assertUnprocessable()->assertJsonValidationErrors('id');
    expect(DB::table('payments')->count())->toBe(0)->and(shopBalance($shop->id))->toBe(-12.5);
});

test('seller payment actions refuse each caller with payment wallet balance request and session unchanged', function (string $caller) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('sellers.payment_histories')), $caller);
    assertStoreCallerRefused($this->get(route('sellers.payment_history', $seller)), $caller);
    assertStoreCallerRefused($this->post(route('sellers.payment_modal'), ['id' => $shop]), $caller);
    assertStoreCallerRefused($this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id])), $caller);
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $id)->value('status'))->toBe(0);
    expect(session()->has('payment_data'))->toBeFalse();
    expect(session()->has('payment_type'))->toBeFalse();
})->with('refused store admin callers');

test('payout rolls every write back on record failure and always clears its temporary session', function () {
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    Event::listen('eloquent.creating: '.Payment::class, fn () => throw new RuntimeException('payment record failure'));
    try {
        $this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id]))->assertServerError();
    } finally {
        Event::forget('eloquent.creating: '.Payment::class);
    }
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $id)->value('status'))->toBe(0);
    expect(session()->has('payment_data'))->toBeFalse();
    expect(session()->has('payment_type'))->toBeFalse();
});

test('payout reference and field lengths are validation failures without writes', function (array $changes, string $field) {
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
    $shop = createShopFor(createStoreUser('seller'), 100);
    $this->postJson(route('commissions.pay_to_seller'), payoutForm($shop, $changes))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
})->with([
    [['withdraw_request_id' => 99999, 'payment_withdraw' => 'withdraw_request'], 'withdraw_request_id'],
    [['txn_code' => str_repeat('a', 192)], 'txn_code'],
    [['payment_option' => str_repeat('a', 101)], 'payment_option'],
    [['payment_withdraw' => 'bad'], 'payment_withdraw'],
]);

test('only pending zero or null withdrawals transition to paid and nonpending status remains unchanged', function (?int $status, bool $allowed) {
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 50, $status);
    $response = $this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['amount' => 50, 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id]));
    if ($allowed) {
        $response->assertRedirect(route('withdraw_requests_all'));
    } else {
        $response->assertNotFound();
    }
    expect(shopBalance($shop))->toBe($allowed ? 50.0 : 100.0)
        ->and(DB::table('payments')->count())->toBe($allowed ? 1 : 0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $id)->value('status'))->toBe($allowed ? 1 : $status);
})->with([[null, true], [0, true], [1, false], [2, false]]);

test('a non cash bank option keeps legacy credit semantics even when a withdrawal is supplied', function () {
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, -100);
    $id = createWithdrawRequestFor($seller, 40);
    $this->post(route('commissions.pay_to_seller'), payoutForm($shop, ['payment_option' => 'manual', 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id]))->assertRedirect(route('sellers.index'));
    expect(shopBalance($shop))->toBe(-60.0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $id)->value('status'))->toBe(0)
        ->and(DB::table('payments')->sole()->txn_code)->toBeNull();
});

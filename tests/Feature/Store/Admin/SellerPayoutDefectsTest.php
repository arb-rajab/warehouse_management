<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedStoreCurrency();
    actingAsStoreUser(createStoreStaff('pay_to_seller'));
});

/** @return array<string, mixed> */
function sellerPayoutPayload(int $shop, array $changes = []): array
{
    return ['shop_id' => $shop, 'amount' => 40, 'payment_option' => 'cash', ...$changes];
}

/** @return array<string, mixed> */
function sellerPayoutSnapshot(): array
{
    return [
        'shops' => DB::table('shops')->orderBy('id')->get()->toJson(),
        'requests' => DB::table('seller_withdraw_requests')->orderBy('id')->get()->toJson(),
        'payments' => DB::table('payments')->orderBy('id')->get()->toJson(),
        'wallets' => DB::table('users')->orderBy('id')->pluck('balance', 'id')->toJson(),
    ];
}

test('D2 refuses a clear due submission without its required method', function () {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, -40);
    createWithdrawRequestFor($seller, 20);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), ['shop_id' => $shop, 'amount' => 40])
        ->assertUnprocessable()->assertJsonValidationErrors('payment_option');
    expect(sellerPayoutSnapshot())->toBe($before);
});

test('D5 refuses withdrawal request limit plus one without changing any rows', function () {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'amount' => 41, 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id,
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'amount' => __('messages.seller_payout_full_request', ['amount' => DB::table('seller_withdraw_requests')->where('id', $id)->value('amount')]),
    ]);
    expect(sellerPayoutSnapshot())->toBe($before);
});

test('D1 refuses a partial withdrawal without changing any money or request', function (string $locale) {
    $this->withSession(['locale' => $locale]);
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 80);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'amount' => 12.5, 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id,
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'amount' => __('messages.seller_payout_full_request', ['amount' => DB::table('seller_withdraw_requests')->where('id', $id)->value('amount')]),
    ]);
    expect(sellerPayoutSnapshot())->toBe($before);
})->with(['en', 'ar']);

test('D5 refuses balance limit plus one without changing any rows', function () {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    createWithdrawRequestFor($seller, 40);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, ['amount' => 101]))
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(sellerPayoutSnapshot())->toBe($before);
});

test('D2 and D5 the exact balance limit pays once with the established method direction', function (string $method, float $initial, string $recorded) {
    $seller = createStoreUser('seller');
    $seller->forceFill(['balance' => 77])->save();
    $shop = createShopFor($seller, $initial);
    $noise = createShopFor(createStoreUser('seller'), 70);
    $id = createWithdrawRequestFor($seller, 20);
    $form = sellerPayoutPayload($shop, ['amount' => 40, 'payment_option' => $method]);
    $this->post(route('commissions.pay_to_seller'), $form)->assertRedirect(route('sellers.index'));
    $after = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), $form)->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(sellerPayoutSnapshot())->toBe($after)
        ->and(shopBalance($shop))->toBe(0.0)->and(shopBalance($noise))->toBe(70.0)
        ->and((float) $seller->fresh()->balance)->toBe(77.0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $id)->value('status'))->toBe(0);
    $row = DB::table('payments')->sole();
    expect($row->seller_id)->toBe($seller->id)->and((float) $row->amount)->toBe(40.0)
        ->and($row->payment_method)->toBe($recorded)->and($row->txn_code)->toBeNull()->and($row->payment_details)->toBeNull();
})->with([
    ['cash', 40.0, 'cash'], ['bank_payment', 40.0, 'bank_payment'],
    ['seller_paid_to_admin', -40.0, 'Seller paid to admin'], ['arbitrary_manual_option', -40.0, 'Seller paid to admin'],
]);

test('D5 refuses amounts outside the balance direction and limit in both locales', function (float $balance, string $method, float $amount, string $locale) {
    $this->withSession(['locale' => $locale]);
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, $balance);
    createWithdrawRequestFor($seller, 40);
    $before = sellerPayoutSnapshot();
    $limit = max(0, in_array($method, ['cash', 'bank_payment'], true) ? $balance : -$balance);
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, ['amount' => $amount, 'payment_option' => $method]))
        ->assertUnprocessable()->assertJsonValidationErrors([
            'amount' => __('messages.seller_payout_balance_limit', ['amount' => (string) $limit]),
        ]);
    expect(sellerPayoutSnapshot())->toBe($before)
        ->and(session()->has('payment_data'))->toBeFalse()->and(session()->has('payment_type'))->toBeFalse();
})->with([
    'debit limit plus one' => [100.0, 'cash', 101.0],
    'bank limit plus one' => [100.0, 'bank_payment', 101.0],
    'credit limit plus one' => [-100.0, 'manual', 101.0],
    'wrong debit direction' => [-100.0, 'cash', 1.0],
    'wrong credit direction' => [100.0, 'manual', 1.0],
    'zero debit balance' => [0.0, 'bank_payment', 1.0],
    'zero credit balance' => [0.0, 'seller_paid_to_admin', 1.0],
])->with(['en', 'ar']);

test('D1 and D3 a full withdrawal closes once and preserves the optional transaction code', function (string $method, ?string $txn, ?int $status) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 40.25);
    $id = createWithdrawRequestFor($seller, 40.25, $status);
    DB::table('seller_withdraw_requests')->where('id', $id)->update(['message' => 'Original request']);
    $noise = createWithdrawRequestFor(createStoreUser('seller'), 15);
    $form = sellerPayoutPayload($shop, ['amount' => '40.25', 'payment_option' => $method, 'txn_code' => $txn,
        'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id]);
    $this->post(route('commissions.pay_to_seller'), $form)->assertRedirect(route('withdraw_requests_all'));
    $after = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), $form)->assertNotFound();
    expect(sellerPayoutSnapshot())->toBe($after)->and(shopBalance($shop))->toBe(0.0)
        ->and((int) DB::table('seller_withdraw_requests')->where('id', $noise)->value('status'))->toBe(0);
    $request = DB::table('seller_withdraw_requests')->find($id);
    $payment = DB::table('payments')->sole();
    expect((int) $request->status)->toBe(1)->and((int) $request->viewed)->toBe(1)
        ->and((float) $request->amount)->toBe(40.25)->and($request->message)->toBe('Original request')
        ->and($payment->seller_id)->toBe($seller->id)->and((float) $payment->amount)->toBe(40.25)
        ->and($payment->payment_method)->toBe($method)->and($payment->txn_code)->toBe($txn)->and($payment->payment_details)->toBeNull();
})->with(['cash', 'bank_payment'])->with([null, str_repeat('ت', 191)])->with([0, null]);

test('D1 and D5 a full request larger than the balance is refused under the shop lock', function () {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 40);
    $id = createWithdrawRequestFor($seller, 80);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'amount' => 80, 'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['amount' => __('messages.seller_payout_balance_limit', ['amount' => '40'])]);
    expect(sellerPayoutSnapshot())->toBe($before);
});

test('payout field and ownership refusals leave all rows unchanged', function (array $changes, string $error, bool $withdrawal) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    createWithdrawRequestFor(createStoreUser('seller'), 40);
    $form = sellerPayoutPayload($shop, [
        ...($withdrawal ? ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id] : []), ...$changes,
    ]);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), $form)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(sellerPayoutSnapshot())->toBe($before);
})->with([
    'zero' => [['amount' => 0], 'amount'], 'negative' => [['amount' => -1], 'amount'],
    'missing method' => [['payment_option' => null], 'payment_option'],
    'invalid method type' => [['payment_option' => ['cash']], 'payment_option'],
    'method too long' => [['payment_option' => str_repeat('a', 101)], 'payment_option'],
    'txn 192 characters' => [['txn_code' => str_repeat('ت', 192)], 'txn_code'],
])->with([false, true]);

test('another sellers withdrawal is refused without mutation', function () {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    createWithdrawRequestFor($seller, 40);
    $id = createWithdrawRequestFor(createStoreUser('seller'), 40);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('withdraw_request_id');
    expect(sellerPayoutSnapshot())->toBe($before);
});

test('payout limits compare decimal strings without rounding away an excess', function (bool $withdrawal) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40.25);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'amount' => $withdrawal ? '40.250000000000000001' : '100.000000000000000001',
        ...($withdrawal ? ['payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id] : []),
    ]))->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(sellerPayoutSnapshot())->toBe($before);
})->with([false, true]);

test('a request without a positive requested amount cannot be paid', function (?float $requested) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    DB::table('seller_withdraw_requests')->where('id', $id)->update(['amount' => $requested]);
    $before = sellerPayoutSnapshot();
    $this->postJson(route('commissions.pay_to_seller'), sellerPayoutPayload($shop, [
        'payment_withdraw' => 'withdraw_request', 'withdraw_request_id' => $id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(sellerPayoutSnapshot())->toBe($before);
})->with([null, 0.0, -1.0]);

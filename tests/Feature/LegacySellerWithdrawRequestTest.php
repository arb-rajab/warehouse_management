<?php

use Illuminate\Support\Facades\DB;

/**
 * A seller can only ask for what their shop is owed minus what they already
 * asked for and has not been paid; the check and the insert share one locked
 * transaction.
 */
function withdrawRequestCount(): int
{
    return DB::table('seller_withdraw_requests')->count();
}

function postApiWithdrawRequest(object $test, string $token, array $body)
{
    return $test->withToken($token)->postJson('/temp/api/v2/seller/withdraw-request/store', $body);
}

test('a seller can request up to what the shop is owed', function () {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);

    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => 40, 'message' => 'please'])
        ->assertOk()->assertJsonPath('result', true);

    $row = DB::table('seller_withdraw_requests')->first();
    expect($row->user_id)->toBe($seller->id)
        ->and((float) $row->amount)->toBe(40.0)
        ->and((int) $row->status)->toBe(0);
});

test('a second request cannot stack past the balance minus the pending ones', function () {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);
    createWithdrawRequestFor($seller, 70);
    createWithdrawRequestFor(createStoreUser('seller'), 90);

    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => 40])
        ->assertOk()->assertJsonPath('result', false);
    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => 30])
        ->assertOk()->assertJsonPath('result', true);

    expect(withdrawRequestCount())->toBe(3);
});

test('a request above the balance is refused', function () {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);

    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => 100.01])
        ->assertOk()->assertJsonPath('result', false);

    expect(withdrawRequestCount())->toBe(0);
});

test('a paid request frees its amount for a new one', function () {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);
    createWithdrawRequestFor($seller, 70, 1);

    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => 90])
        ->assertOk()->assertJsonPath('result', true);

    expect(withdrawRequestCount())->toBe(2);
});

test('a forged amount is rejected by the API and nothing is stored', function (mixed $amount) {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);

    postApiWithdrawRequest($this, storeApiToken($seller), ['amount' => $amount])->assertUnprocessable();

    expect(withdrawRequestCount())->toBe(0);
})->with(['negative' => -10, 'zero' => 0, 'text' => 'lots', 'missing' => null]);

test('an account without a shop cannot open a withdraw request', function () {
    $customer = createStoreUser('customer');

    postApiWithdrawRequest($this, storeApiToken($customer), ['amount' => 10])
        ->assertOk()->assertJsonPath('result', false);

    expect(withdrawRequestCount())->toBe(0);
});

test('a guest cannot open a withdraw request through the API', function () {
    $this->postJson('/temp/api/v2/seller/withdraw-request/store', ['amount' => 10])->assertUnauthorized();

    expect(withdrawRequestCount())->toBe(0);
});

test('the seller panel stores a valid request and refuses a forged one', function () {
    $seller = createStoreUser('seller');
    createShopFor($seller, 100);
    actingAsStoreUser($seller);

    $this->post('/temp/seller/money-withdraw-request/store', ['amount' => -5])->assertSessionHasErrors('amount');
    $this->post('/temp/seller/money-withdraw-request/store', ['amount' => 500])->assertRedirect();
    expect(withdrawRequestCount())->toBe(0);

    $this->post('/temp/seller/money-withdraw-request/store', ['amount' => 60])
        ->assertRedirect(route('seller.money_withdraw_requests.index'));
    expect(withdrawRequestCount())->toBe(1);
});

test('a customer cannot use the seller panel withdraw form', function () {
    actingAsStoreUser(createStoreUser('customer'));

    $this->post('/temp/seller/money-withdraw-request/store', ['amount' => 10])->assertNotFound();

    expect(withdrawRequestCount())->toBe(0);
});

test('the admin panel has no endpoint that creates a withdraw request', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/withdraw_requests', ['amount' => 10])->assertStatus(405);

    expect(withdrawRequestCount())->toBe(0);
});

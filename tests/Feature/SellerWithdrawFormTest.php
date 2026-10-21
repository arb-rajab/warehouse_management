<?php

use App\Models\BusinessSetting;
use App\Models\SellerWithdrawRequest;
use App\Models\StoreUser;

function withdrawSeller(float $balance = 100): StoreUser
{
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    createShopFor($seller, $balance);

    return actingAsStoreUser($seller->fresh());
}

test('withdraw form has the shared amount mark and legend, limits, and no optional requirement', function () {
    withdrawSeller();
    $xpath = htmlXPath($this->get(route('seller.money_withdraw_requests.index'))->assertOk());
    $form = formPostingTo($xpath, route('seller.money_withdraw_request.store'));
    $fields = formFieldMarks($xpath, $form);
    expect($fields['amount']['mark'])->toBeTrue()->and($fields['amount']['required'])->toBeTrue()
        ->and($fields['message']['mark'])->toBeFalse()->and($fields['message']['required'])->toBeFalse()
        ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
        ->and($xpath->query('.//textarea[@name="message"]', $form)->item(0)->getAttribute('maxlength'))->toBe('2000');
});

test('withdraw request accepts a decimal amount and message boundary for the caller only', function () {
    $seller = withdrawSeller();
    $other = createStoreUser('seller');
    createShopFor($other, 500);
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => '25.50', 'message' => str_repeat('é', 2000)])
        ->assertRedirect(route('seller.money_withdraw_requests.index'))->assertSessionHasNoErrors();
    expect(SellerWithdrawRequest::sole()->user_id)->toBe($seller->id)
        ->and((float) SellerWithdrawRequest::sole()->amount)->toBe(25.5)
        ->and(flashedMessage())->toBe('Request has been sent successfully');
});

test('withdraw refuses malformed input without entering balance arithmetic or writing rows', function (array $input, string $field) {
    withdrawSeller(0);
    $this->post(route('seller.money_withdraw_request.store'), $input)->assertSessionHasErrors([$field]);
    expect(SellerWithdrawRequest::count())->toBe(0);
})->with([
    'missing' => [[], 'amount'],
    'zero' => [['amount' => 0], 'amount'],
    'negative' => [['amount' => -1], 'amount'],
    'array' => [['amount' => [10]], 'amount'],
    'text' => [['amount' => 'lots'], 'amount'],
    'html' => [['amount' => '<b>10</b>'], 'amount'],
    'overflow' => [['amount' => '1e999'], 'amount'],
    'long message' => [['amount' => 10, 'message' => str_repeat('é', 2001)], 'message'],
    'array message' => [['amount' => 10, 'message' => ['please']], 'message'],
    'unshown owner' => [['amount' => 10, 'user_id' => 123], 'user_id'],
    'unshown status' => [['amount' => 10, 'status' => 1], 'status'],
    'unknown empty' => [['amount' => 10, 'unexpected' => null], 'unexpected'],
]);

test('withdraw balance and minimum checks retain their order and messages and write nothing', function (float $balance, float $amount, float $minimum, string $notice) {
    withdrawSeller($balance);
    BusinessSetting::forceCreate(['type' => 'minimum_seller_amount_withdraw', 'value' => (string) $minimum]);
    $this->from(route('seller.money_withdraw_requests.index'))->post(route('seller.money_withdraw_request.store'), ['amount' => $amount])
        ->assertRedirect(route('seller.money_withdraw_requests.index'))->assertSessionHasNoErrors();
    expect(SellerWithdrawRequest::count())->toBe(0)->and(flashedMessage())->toBe($notice);
})->with([
    'balance first' => [5, 1, 20, 'You do not have enough balance to send withdraw request'],
    'minimum' => [100, 19.99, 20, 'Invalid amount'],
    'above balance' => [100, 100.01, 0, 'Invalid amount'],
]);

test('withdraw excludes other sellers pending rows and counts the callers pending requests', function () {
    $seller = withdrawSeller();
    createWithdrawRequestFor(createStoreUser('seller'), 1000);
    createWithdrawRequestFor($seller, 70);
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => 30.01])->assertRedirect();
    expect(SellerWithdrawRequest::count())->toBe(2);
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => 30])->assertSessionHasNoErrors();
    expect(SellerWithdrawRequest::count())->toBe(3)->and((float) SellerWithdrawRequest::where('user_id', $seller->id)->sum('amount'))->toBe(100.0);
});

test('withdraw keeps text escaped and renders old input and field errors on refusal', function () {
    withdrawSeller();
    $this->from(route('seller.money_withdraw_requests.index'))->post(route('seller.money_withdraw_request.store'), ['amount' => 0, 'message' => '<b>keep</b>']);
    $page = $this->get(route('seller.money_withdraw_requests.index'))->assertOk()->assertSee('&lt;b&gt;keep&lt;/b&gt;', false);
    $xpath = htmlXPath($page);
    $form = formPostingTo($xpath, route('seller.money_withdraw_request.store'));
    expect($xpath->query('.//input[@name="amount"]', $form)->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query('.//*[contains(@class,"text-danger")]', $form)->length)->toBeGreaterThan(0);
});

test('withdraw stores markup in message and history escapes it', function () {
    withdrawSeller();
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => 10, 'message' => '<b>please</b>'])->assertSessionHasNoErrors();
    expect(SellerWithdrawRequest::sole()->message)->toBe('<b>please</b>');
    $this->get(route('seller.money_withdraw_requests.index'))->assertOk()->assertSee('&lt;b&gt;please&lt;/b&gt;', false);
});

test('guests and customers cannot submit a seller withdrawal', function (bool $guest) {
    if (! $guest) {
        actingAsStoreUser(createStoreUser());
    }
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => 10])->assertNotFound();
    expect(SellerWithdrawRequest::count())->toBe(0);
})->with([true, false]);

test('withdraw refusal still renders after array input', function () {
    withdrawSeller();
    $this->from(route('seller.money_withdraw_requests.index'))->post(route('seller.money_withdraw_request.store'), ['amount' => [10], 'message' => ['bad']]);
    $this->get(route('seller.money_withdraw_requests.index'))->assertOk();
});

test('withdraw refuses a real second sellers id and creates no request', function () {
    $seller = withdrawSeller();
    $other = createStoreUser('seller');
    createShopFor($other, 500);
    $this->post(route('seller.money_withdraw_request.store'), ['amount' => 10, 'user_id' => $other->id])->assertSessionHasErrors('user_id');
    expect(SellerWithdrawRequest::count())->toBe(0);
});

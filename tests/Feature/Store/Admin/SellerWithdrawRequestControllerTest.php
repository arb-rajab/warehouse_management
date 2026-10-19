<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedStoreCurrency());

test('legacy withdrawal approve reject and delete routes do not change anything', function () {
    Mail::fake();
    Notification::fake();
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    actingAsStoreUser(createStoreStaff('view_seller_payout_requests'));
    $before = DB::table('seller_withdraw_requests')->find($id);
    foreach ([1, 2, 0] as $status) {
        $this->put(route('withdraw_requests.update', $id), ['status' => $status, 'message' => 'changed'])->assertRedirect(route('withdraw_requests_all'));
        expect(DB::table('seller_withdraw_requests')->find($id))->toEqual($before);
    }
    $this->delete(route('withdraw_requests.destroy', $id))->assertRedirect(route('withdraw_requests_all'));
    expect(DB::table('seller_withdraw_requests')->find($id))->toEqual($before);
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('both withdrawal lists render and paginate with complete narrow payloads', function () {
    actingAsStoreUser(createStoreStaff('view_seller_payout_requests'));
    $seller = createStoreUser('seller');
    $seller->forceFill(['name' => 'Withdraw Seller'])->save();
    $shop = createShopFor($seller, 100);
    DB::table('shops')->where('id', $shop)->update(['name' => 'Withdrawal Shop']);
    $id = createWithdrawRequestFor($seller, 40);
    DB::table('seller_withdraw_requests')->where('id', $id)->update(['message' => '<script>seller message</script>', 'created_at' => '2020-10-19 08:00:00']);
    foreach (['withdraw_requests.index', 'withdraw_requests_all'] as $route) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Store/Admin/Sellers/Withdrawals/Index')->where('filters', ['per_page' => 20])->has('withdrawals.data.0', fn (Assert $row) => $row
            ->where('id', $id)->where('date', '2020-10-19 08:00:00')->where('due', single_price(100))->where('amount', single_price(40))->where('message', '<script>seller message</script>')->where('paid', false)
            ->has('seller', fn (Assert $s) => $s->where('id', $seller->id)->where('name', 'Withdraw Seller')->where('shop_name', 'Withdrawal Shop'))));
    }
    foreach (range(1, 22) as $i) {
        createWithdrawRequestFor($seller, $i, 1);
    }
    foreach (['withdraw_requests.index', 'withdraw_requests_all'] as $route) {
        $this->get(route($route))->assertInertia(fn (Assert $p) => $p->has('withdrawals.data', 20)->where('withdrawals.meta.total', 23)->where('withdrawals.data.0.paid', true));
        $this->get(route($route, ['page' => 2]))->assertInertia(fn (Assert $p) => $p->has('withdrawals.data', 3));
        $this->get(route($route, ['per_page' => 10]))->assertInertia(fn (Assert $p) => $p->has('withdrawals.data', 10)->where('filters.per_page', 10)->where('withdrawals.links.next', fn ($url) => str_contains($url, 'per_page=10')));
        $this->getJson(route($route, ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
});

test('withdrawal modal and message endpoints keep their own read gate without granting pay permission', function () {
    actingAsStoreUser(createStoreStaff('view_seller_payout_requests'));
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    DB::table('seller_withdraw_requests')->where('id', $id)->update(['message' => 'original']);
    $this->postJson(route('withdraw_request.payment_modal'), ['id' => $seller->id, 'seller_withdraw_request_id' => $id])->assertOk()->assertExactJson(['shop' => ['id' => $shop, 'due' => single_price(100), 'admin_to_pay' => 100, 'cash_enabled' => false, 'bank_enabled' => false, 'bank_name' => null, 'bank_acc_name' => null, 'bank_acc_no' => null, 'bank_routing_no' => null], 'withdrawal' => ['id' => $id, 'amount' => 40]]);
    $this->postJson(route('withdraw_request.message_modal'), ['id' => $id])->assertOk()->assertExactJson(['id' => $id, 'message' => 'original']);
    $this->postJson(route('commissions.pay_to_seller'), ['shop_id' => $shop, 'amount' => 40, 'payment_option' => 'cash'])->assertForbidden();
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
    $this->postJson(route('withdraw_request.message_modal'), ['id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('id');
    $this->postJson(route('withdraw_request.payment_modal'), ['id' => $seller->id, 'seller_withdraw_request_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('seller_withdraw_request_id');
    $this->postJson(route('withdraw_request.payment_modal'), ['id' => 999999, 'seller_withdraw_request_id' => $id])->assertUnprocessable()->assertJsonValidationErrors(['id', 'seller_withdraw_request_id']);
    $other = createWithdrawRequestFor(createStoreUser('seller'), 15);
    $this->postJson(route('withdraw_request.payment_modal'), ['id' => $seller->id, 'seller_withdraw_request_id' => $other])->assertUnprocessable();
});

test('retained empty resource routes redirect without adding approve reject or delete behavior and missing records 404', function () {
    actingAsStoreUser(createStoreStaff('view_seller_payout_requests'));
    $id = createWithdrawRequestFor(createStoreUser('seller'), 40);
    $this->get(route('withdraw_requests.create'))->assertRedirect(route('withdraw_requests_all'));
    foreach (['show', 'edit'] as $action) {
        $this->get(route('withdraw_requests.'.$action, $id))->assertRedirect(route('withdraw_requests_all'));
        $this->get(route('withdraw_requests.'.$action, 999999))->assertNotFound();
    }
    $this->put(route('withdraw_requests.update', 999999), ['status' => 1])->assertNotFound();
    $this->delete(route('withdraw_requests.destroy', 999999))->assertNotFound();
    $this->post('/temp/admin/withdraw_requests', ['amount' => 10])->assertMethodNotAllowed();
    expect(DB::table('seller_withdraw_requests')->count())->toBe(1);
});

test('each withdrawal action refuses every unauthorized caller and leaves all data unchanged', function (string $caller) {
    $seller = createStoreUser('seller');
    $shop = createShopFor($seller, 100);
    $id = createWithdrawRequestFor($seller, 40);
    $before = DB::table('seller_withdraw_requests')->find($id);
    actingAsRefusedStoreCaller($caller);
    foreach (['withdraw_requests.index', 'withdraw_requests_all', 'withdraw_requests.create'] as $route) {
        assertStoreCallerRefused($this->get(route($route)), $caller);
    }
    foreach (['show', 'edit'] as $action) {
        assertStoreCallerRefused($this->get(route('withdraw_requests.'.$action, $id)), $caller);
    }
    assertStoreCallerRefused($this->post(route('withdraw_request.payment_modal'), ['id' => $seller->id, 'seller_withdraw_request_id' => $id]), $caller);
    assertStoreCallerRefused($this->post(route('withdraw_request.message_modal'), ['id' => $id]), $caller);
    assertStoreCallerRefused($this->put(route('withdraw_requests.update', $id), ['status' => 1, 'message' => 'changed']), $caller);
    expect(DB::table('seller_withdraw_requests')->find($id))->toEqual($before);
    assertStoreCallerRefused($this->delete(route('withdraw_requests.destroy', $id)), $caller);
    expect(DB::table('seller_withdraw_requests')->find($id))->toEqual($before);
    expect(shopBalance($shop))->toBe(100.0)->and(DB::table('payments')->count())->toBe(0);
})->with('refused store admin callers');

<?php

use App\Models\Subscriber;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the subscriber list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_all_subscribers'));
    $this->get(route('admin.subscribers.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Marketing/Subscribers/Index')
    );
});

test('subscribers return their full narrow payload with a date only', function () {
    $subscriber = Subscriber::forceCreate(['email' => 'reader@example.com']);
    backdate($subscriber, '2026-10-08 12:45:00');
    actingAsStoreUser(createStoreStaff('view_all_subscribers'));
    $this->get(route('admin.subscribers.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('filters', ['per_page' => 20])->has('subscribers.data', 1)
            ->has('subscribers.data.0', fn (Assert $row) => $row->where('id', $subscriber->id)
                ->where('email', 'reader@example.com')->where('created_at', '2026-10-08'))
    );
});

test('subscribers paginate and validate page sizes', function () {
    foreach (range(1, 23) as $number) {
        Subscriber::forceCreate(['email' => "reader{$number}@example.com"]);
    }
    actingAsStoreUser(createStoreStaff('view_all_subscribers'));
    assertPagesWithoutOverlap(
        fn (int $page) => $this->get(route('admin.subscribers.index', ['page' => $page])),
        fn ($response) => array_column($response->inertiaProps('subscribers.data'), 'id'), 23, 20
    );
    $this->get(route('admin.subscribers.index', ['per_page' => 10]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('subscribers.data', 10)->where('filters.per_page', 10)
    );
    $this->getJson(route('admin.subscribers.index', ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('deleting a subscriber preserves filters and other subscribers', function () {
    $subscriber = Subscriber::forceCreate(['email' => 'reader@example.com']);
    $other = Subscriber::forceCreate(['email' => 'other@example.com']);
    actingAsStoreUser(createStoreStaff('delete_subscriber'));
    $this->delete(route('subscriber.destroy', [$subscriber, 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('admin.subscribers.index', ['page' => 2, 'per_page' => 10]));
    $this->assertModelMissing($subscriber);
    $this->assertModelExists($other);
});

test('deleting a missing subscriber returns 404', function () {
    actingAsStoreUser(createStoreStaff('delete_subscriber'));
    $this->delete(route('subscriber.destroy', 999999))->assertNotFound();
});

test('the subscriber list refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('admin.subscribers.index')), $caller);
})->with('refused store admin callers');

test('deleting subscribers refuses unauthorized callers and deletes nothing', function (string $caller) {
    $subscriber = Subscriber::forceCreate(['email' => 'reader@example.com']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete(route('subscriber.destroy', $subscriber)), $caller);
    $this->assertModelExists($subscriber);
})->with('refused store admin callers');

test('the public subscription route remains separate and preserves existing subscriptions', function () {
    Subscriber::forceCreate(['email' => 'existing@example.com']);
    expect(route('subscribers.index', absolute: false))->toBe('/temp/subscribers')
        ->and(route('admin.subscribers.index', absolute: false))->toBe('/temp/admin/subscribers');
    $this->from('/temp')->post(route('subscribers.store'), ['email' => 'reader@example.com'])->assertRedirect('/temp');
    $this->post(route('subscribers.store'), ['email' => 'reader@example.com']);
    $this->assertDatabaseCount('subscribers', 2);
    $this->assertDatabaseHas('subscribers', ['email' => 'existing@example.com']);
});

test('the documented unused offer brand route and views are retired', function () {
    expect(Route::has('offer_deals.brand_discount'))->toBeFalse()
        ->and(view()->exists('shop.backend.marketing.offer_deals.brands.index'))->toBeFalse()
        ->and(view()->exists('shop.backend.marketing.offer_deals.brands.offer_deal_discount'))->toBeFalse();
});

<?php

use App\Enums\TripStatus;
use App\Models\StoreUser;
use App\Services\OrderService;
use Database\Seeders\StoreAccountFilterSeeder;
use Database\Seeders\StoreActivityFilterSeeder;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    putenv('STORE_FILTER_SEED_PASSWORD=isolated-activity-test-password');
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    createStoreProduct();
    (new StoreAccountFilterSeeder)->run();
    (new StoreActivityFilterSeeder)->run();
});

afterEach(function () {
    putenv('STORE_FILTER_SEED_PASSWORD');
});

test('activity fixtures populate every order delivery and payment option in each list', function () {
    actingAsStoreUser(StoreUser::findOrFail(app(StoreFilterSeedData::class)->user('admin')));
    foreach (['all_orders.index', 'inhouse_orders.index', 'seller_orders.index', 'pick_up_point.index'] as $route) {
        foreach (OrderService::DELIVERY_STATUSES as $status) {
            $this->get(route($route, ['delivery_status' => $status]))->assertOk()->assertInertia(
                fn (Assert $page) => $page->where('orders.meta.total', fn ($total) => $total > 0)
            );
        }
        foreach (['paid', 'unpaid'] as $status) {
            $this->get(route($route, ['payment_status' => $status]))->assertOk()->assertInertia(
                fn (Assert $page) => $page->where('orders.meta.total', fn ($total) => $total > 0)
            );
        }
    }
});

test('activity fixtures populate every trip and representative payment status and date searches', function () {
    actingAsStoreUser(StoreUser::findOrFail(app(StoreFilterSeedData::class)->user('admin')));
    foreach (['trips.index', 'trips.today-trips'] as $route) {
        foreach (TripStatus::cases() as $status) {
            $this->get(route($route, ['status' => [$status->value]]))->assertOk()->assertInertia(
                fn (Assert $page) => $page->where('trips.meta.total', fn ($total) => $total > 0)
            );
        }
    }
    foreach (['accepted', 'pending', 'rejected'] as $status) {
        $this->get(route('admin.rep.payments.index', [$status => 1, 'search' => 'FILTER-RECEIPT']))->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('payments.meta.total', fn ($total) => $total > 0)
        );
    }
    $this->get(route('admin.rep.visits.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('visits.meta.total', 2)
    );
});

test('representatives see owned payments and current visits through their actual API', function () {
    $data = app(StoreFilterSeedData::class);
    $rep = StoreUser::findOrFail($data->user('representative'));
    $token = storeApiToken($rep);
    $response = $this->withToken($token)->getJson('/temp/api/v2/representatives/payments/index?search=FILTER-RECEIPT')->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    $this->getJson('/temp/api/v2/representatives/current-visit')->assertOk()->assertJsonPath('result', true);
    $customer = StoreUser::findOrFail($data->user('customer'));
    $this->getJson('/temp/api/v2/representatives/get-customers?'.http_build_query(['name' => $customer->name]))->assertOk()
        ->assertJsonPath('data.0.id', $customer->id);
    foreach (['company_name' => 'Filter coverage company', 'otajer_id' => '987654321'] as $filter => $value) {
        $this->getJson('/temp/api/v2/representatives/get-customers?'.http_build_query([$filter => $value]))->assertOk()->assertJsonPath('data.0.id', $customer->id);
    }
    expect(DB::table('visits')->where('rep_id', $rep->id)->whereNull('order_id')->count())->toBe(1);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

<?php

use Database\Seeders\StoreEngagementFilterSeeder;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedStoreCurrency();
    $this->seed(StoreEngagementFilterSeeder::class);
});

test('engagement fixtures satisfy actual support and campaign search filters', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets', 'view_blogs', 'view_all_flash_deals', 'view_all_offer_deals'));
    $data = app(StoreFilterSeedData::class);
    $ticketId = $data->find('tickets', 'engagement-ticket-pending');
    $response = $this->get(route('support_ticket.admin_index', ['search' => '987650', 'page' => 2]))->assertOk();
    expect(array_column($response->inertiaProps('tickets.data'), 'id'))->toBe([$ticketId]);
    foreach ([['blog.index', 'blogs', 'Pantry Journal', 'blogs', 'engagement-blog-1'], ['flash_deals.index', 'deals', 'Grocery', 'flash_deals', 'engagement-flash-deal'], ['offer_deals.index', 'offers', 'Restock', 'offers', 'engagement-offer']] as [$route, $prop, $search, $table, $key]) {
        $rows = $this->get(route($route, ['search' => $search, 'page' => 2]))->assertOk()->inertiaProps($prop.'.data');
        expect($rows)->not->toBeEmpty();
        expect(DB::table($table)->where('id', $data->find($table, $key))->exists())->toBeTrue();
    }
    $this->get(route('support_ticket.admin_index', ['search' => 'no-such-fixture']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('tickets.data', 0));
});

test('engagement fixtures satisfy both seller verification filters and wallet date windows', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller', 'seller_products_sale_report', 'wallet_transaction_report'));
    $data = app(StoreFilterSeedData::class);
    foreach ([0 => 'seller-unverified', 1 => 'seller'] as $verified => $key) {
        $id = $data->find('shops', $verified ? 'catalogue-shop' : 'engagement-shop-'.$key);
        $sellers = $this->get('/temp/admin/sellers?approved_status='.$verified.'&page=2')->assertOk()->inertiaProps('sellers.data');
        expect(array_column($sellers, 'id'))->toContain($id)->not->toContain($data->find('shops', $verified ? 'engagement-shop-seller-unverified' : 'catalogue-shop'));
        $report = $this->get('/temp/admin/seller_sale_report?verification_status='.$verified.'&page=2')->assertOk()->inertiaProps('rows.data');
        expect(array_column($report, 'id'))->toContain($id);
    }
    $customer = $data->find('users', 'customer');
    foreach ([['created_within_days' => 1], ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]] as $window) {
        $response = $this->get('/temp/admin/wallet-history?'.http_build_query(['user_ids' => [$customer], ...$window, 'page' => 2]))->assertOk();
        expect(array_column($response->inertiaProps('rows.data'), 'id'))->toBe([$data->find('wallets', 'engagement-wallet-'.$customer)]);
    }
});

test('engagement reruns keep existing accounts and records intact without duplicates', function () {
    $existing = createStoreUser('seller');
    $id = DB::table('shops')->insertGetId(['user_id' => $existing->id, 'name' => 'Existing shop', 'slug' => 'existing-shop', 'verification_status' => 1]);
    $oldUser = (array) DB::table('users')->where('id', $existing->id)->first();
    $oldShop = (array) DB::table('shops')->where('id', $id)->first();
    $tables = ['tickets', 'ticket_replies', 'blogs', 'offers', 'flash_deals', 'wallets', 'shops', 'payments'];
    $counts = array_map(fn (string $table): int => DB::table($table)->count(), $tables);
    $this->seed(StoreEngagementFilterSeeder::class);
    expect(array_map(fn (string $table): int => DB::table($table)->count(), $tables))->toBe($counts)
        ->and((array) DB::table('users')->where('id', $existing->id)->first())->toBe($oldUser)
        ->and((array) DB::table('shops')->where('id', $id)->first())->toBe($oldShop);
});

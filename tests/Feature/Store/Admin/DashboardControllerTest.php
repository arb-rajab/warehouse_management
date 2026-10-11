<?php

use App\Http\Controllers\Store\Admin\DashboardController;
use App\Models\Category;
use App\Models\StoreUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard aggregates narrow chart arrays and preserves approved top products', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    seedStoreCurrency();
    $root = Category::forceCreate(['name' => 'Root', 'slug' => 'root', 'level' => 0]);
    $child = Category::forceCreate(['name' => 'Child', 'slug' => 'child', 'level' => 1, 'parent_id' => $root->id]);
    createStoreProduct(['category_id' => $child->id, 'name' => 'Visible Product', 'added_by' => 'admin', 'num_of_sale' => 4, 'current_stock' => 7]);
    createStoreProduct(['category_id' => $root->id, 'added_by' => 'seller', 'num_of_sale' => 20, 'current_stock' => 2]);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Dashboard/Index')->where('stats.products', 2)->where('charts.labels', ['Root'])
        ->where('charts.sales', [24])->where('charts.stock', [9])->has('topProducts', 1)
        ->where('topProducts.0.name', 'Visible Product')->missing('topProducts.0.description')->where('smtp_missing', false));
});

test('dashboard sidebar counts are permission filtered and notifications belong to the current account', function () {
    $staff = createStoreStaff('admin_dashboard', 'view_all_seller', 'view_all_support_tickets', 'view_all_product_conversations');
    DB::table('shops')->insert(['user_id' => createStoreUser('seller')->id, 'name' => 'Pending', 'verification_status' => 0, 'verification_info' => '{}']);
    DB::table('shops')->insert(['user_id' => createStoreUser('seller')->id, 'name' => 'Verified', 'verification_status' => 1, 'verification_info' => '{}']);
    DB::table('tickets')->insert(['code' => 'OPEN', 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => 'Details', 'viewed' => 0]);
    DB::table('conversations')->insert(['sender_id' => createStoreUser()->id, 'receiver_id' => $staff->id, 'title' => 'Unread', 'receiver_viewed' => 0]);
    DB::table('conversations')->insert(['sender_id' => $staff->id, 'receiver_id' => createStoreUser()->id, 'title' => 'Other', 'receiver_viewed' => 0]);
    foreach ([$staff, createStoreStaff('admin_dashboard')] as $recipient) {
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\OrderNotification', 'notifiable_type' => StoreUser::class, 'notifiable_id' => $recipient->id, 'data' => json_encode(['order_code' => 'ORDER-'.$recipient->id, 'status' => 'placed', 'secret' => 'hidden']), 'created_at' => now(), 'updated_at' => now()]);
    }
    actingAsStoreUser($staff);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('store.counts', ['sellers' => 1, 'tickets' => 1, 'conversations' => 1])
        ->where('store.notifications.unread_count', 1)->has('store.notifications.items', 1)
        ->where('store.notifications.items.0.order_code', 'ORDER-'.$staff->id)->missing('store.notifications.items.0.secret'));
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('store.counts', ['sellers' => 0, 'tickets' => 0, 'conversations' => 0]));
});

test('dashboard refuses callers without its permission', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('shop.admin.dashboard')), $caller);
})->with('refused store admin callers');

test('clear cache forgets only dashboard graph keys without invoking operational commands', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));
    Cache::shouldReceive('forget')->once()->with(DashboardController::GRAPH_CACHE_KEY)->andReturnTrue();
    Cache::shouldReceive('forget')->once()->with('cached_graph_data')->andReturnTrue();
    Artisan::shouldReceive('call')->never();
    $this->post(route('cache.clear'))->assertRedirect(route('shop.admin.dashboard'));
});

test('clear cache rejects ordinary admins and GET requests without touching cache', function () {
    actingAsStoreUser(createStoreAdmin());
    Cache::shouldReceive('forget')->never();
    $this->post(route('cache.clear'))->assertForbidden();
    $this->get(route('cache.clear'))->assertMethodNotAllowed();
});

test('warehouse authentication cannot authorize store dashboard or order entry', function () {
    actingAsAdmin();
    $this->get(route('shop.admin.dashboard'))->assertRedirect(route('shop.login'));
    $this->get(route('feat-order.create'))->assertRedirect(route('shop.login'));
});

test('top products omit rows without a public slug instead of failing dashboard rendering', function () {
    actingAsStoreUser(createStoreStaff('admin_dashboard'));
    createStoreProduct(['slug' => null, 'added_by' => 'admin']);
    $this->get(route('shop.admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stats.products', 1)->has('topProducts', 0));
});

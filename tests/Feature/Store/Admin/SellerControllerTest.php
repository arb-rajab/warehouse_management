<?php

use App\Http\Controllers\SellerController as LegacySellerController;
use App\Http\Controllers\Store\Admin\SellerController;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedStoreCurrency());

function sellerAdminShop(array $attributes = []): Shop
{
    $user = createStoreUser('seller');
    $user->forceFill(['name' => 'Seller Person', 'phone' => '123', 'address' => 'User street', 'balance' => 55])->save();

    return Shop::forceCreate(['user_id' => $user->id, 'name' => 'Seller shop', 'slug' => 'seller-'.$user->id, 'address' => 'Shop street', 'admin_to_pay' => 125, ...$attributes]);
}

function sellerAdminForm(array $overrides = []): array
{
    return ['name' => 'New seller', 'email' => 'new-seller@example.com', 'password' => 'seller-password', ...$overrides];
}

test('the seller list can be rendered under strict CSP', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    $response = $this->get(route('sellers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/Sellers/Index'));
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("script-src 'self';");
});

test('the seller list returns the complete narrow payload and filters out orphans', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    $shop = sellerAdminShop(['verification_info' => '[]']);
    Shop::forceCreate(['user_id' => 999999, 'name' => 'Orphan']);
    createStoreProduct(['user_id' => $shop->user_id]);
    createStoreProduct(['user_id' => createStoreUser('seller')->id]);

    $this->get(route('sellers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('sellers.data', 1)
        ->where('filters', ['per_page' => 20, 'search' => '', 'approved_status' => null])
        ->where('payouts', false)->where('payment_links', false)
        ->has('sellers.data.0', fn (Assert $row) => $row
            ->where('id', $shop->id)->where('user_id', $shop->user_id)->where('name', 'Seller shop')
            ->where('account_name', 'Seller Person')->where('email', $shop->user->email)->where('phone', '123')
            ->where('banned', false)->where('approved', false)->where('has_verification', true)->where('products_count', 1)
            ->where('due', null)->where('due_to_admin', false)->where('payment_history_url', null)));
});

test('the list retains payout sign and encrypted history links only for permitted callers', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller', 'seller_payment_history'));
    DB::table('business_settings')->insert(['type' => 'Payouts_Section', 'value' => '1']);
    $shop = sellerAdminShop(['admin_to_pay' => -12.5]);
    $row = $this->get(route('sellers.index'))->assertOk()->inertiaProps('sellers.data.0');
    expect($row['due'])->toBe(single_price(12.5))->and($row['due_to_admin'])->toBeTrue();
    $token = basename(parse_url($row['payment_history_url'], PHP_URL_PATH));
    expect((int) decrypt($token))->toBe($shop->user_id);
});

test('seller pages paginate twenty by default preserve queries and accept only allowed page sizes', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    foreach (range(1, 23) as $i) {
        sellerAdminShop();
    }
    $page = $this->get(route('sellers.index'))->assertOk()->inertiaProps('sellers');
    expect($page['data'])->toHaveCount(20)->and($page['meta']['total'])->toBe(23);
    $this->get(route('sellers.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('sellers.data', 3));
    $this->get(route('sellers.index', ['per_page' => 10, 'search' => 'Seller']))->assertInertia(fn (Assert $page) => $page->has('sellers.data', 10)->where('filters.per_page', 10)
        ->where('sellers.links.next', fn ($url) => str_contains($url, 'search=Seller') && str_contains($url, 'per_page=10')));
    $this->getJson(route('sellers.index', ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson(route('sellers.index', ['approved_status' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('approved_status');
});

test('seller search matches account name or email and zero approval filters without losing noise scoping', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    $match = sellerAdminShop();
    $noise = sellerAdminShop(['verification_status' => 1]);
    $match->user->forceFill(['name' => 'Unique person'])->save();
    $this->get(route('sellers.index', ['search' => 'Unique', 'approved_status' => 0]))->assertInertia(fn (Assert $page) => $page->has('sellers.data', 1)->where('sellers.data.0.id', $match->id));
    $this->get(route('sellers.index', ['search' => $noise->user->email]))->assertInertia(fn (Assert $page) => $page->has('sellers.data', 1)->where('sellers.data.0.id', $noise->id));
});

test('seller create and edit screens can be rendered and edit exposes the complete account form only', function () {
    actingAsStoreUser(createStoreStaff('edit_seller'));
    $shop = sellerAdminShop();
    $this->get(route('sellers.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/Sellers/Create'));
    $this->get(route('sellers.edit', $shop))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/Sellers/Edit')
        ->has('seller', fn (Assert $row) => $row->where('id', $shop->id)->where('name', 'Seller Person')->where('email', $shop->user->email)));
});

test('seller creation preserves email verification policy without granting roles', function (bool $verify) {
    Notification::fake();
    actingAsStoreUser(createStoreStaff('edit_seller'));
    DB::table('business_settings')->insert(['type' => 'email_verification', 'value' => (int) $verify]);
    $this->post(route('sellers.store'), sellerAdminForm())->assertRedirect(route('sellers.index'));
    $user = StoreUser::where('email', 'new-seller@example.com')->sole();
    expect($user->user_type)->toBe('seller')->and($user->name)->toBe('New seller')
        ->and(Hash::check('seller-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at !== null)->toBe(! $verify)->and($user->roles)->toBeEmpty()
        ->and(DB::table('sellers')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('shops')->where('user_id', $user->id)->value('slug'))->toBe('demo-shop-'.$user->id);
    if ($verify) {
        Notification::assertSentTo($user, EmailVerificationNotification::class);
    } else {
        Notification::assertNothingSent();
    }
})->with([false, true]);

test('seller writes validate names emails uniqueness and passwords without partial creation', function () {
    actingAsStoreUser(createStoreStaff('edit_seller'));
    $taken = createStoreUser('customer');
    foreach ([['name' => ''], ['email' => 'bad'], ['email' => $taken->email], ['password' => ''], ['name' => str_repeat('x', 256)], ['password' => str_repeat('x', 256)]] as $bad) {
        $this->postJson(route('sellers.store'), sellerAdminForm($bad))->assertUnprocessable();
    }
    expect(DB::table('sellers')->count())->toBe(0)->and(Shop::count())->toBe(0);
});

test('seller account updates preserve balance shop details verification and optional password', function () {
    Notification::fake();
    actingAsStoreUser(createStoreStaff('edit_seller'));
    $shop = sellerAdminShop();
    $old = $shop->user->password;
    $this->put(route('sellers.update', $shop), sellerAdminForm(['email' => $shop->user->email, 'password' => '']))->assertRedirect(route('sellers.index'));
    expect($shop->user->fresh()->password)->toBe($old)->and((float) $shop->user->fresh()->balance)->toBe(55.0)
        ->and((float) $shop->fresh()->admin_to_pay)->toBe(125.0)->and($shop->fresh()->name)->toBe('Seller shop');
    $this->patch(route('sellers.update', $shop), sellerAdminForm())->assertRedirect(route('sellers.index'));
    expect(Hash::check('seller-password', $shop->user->fresh()->password))->toBeTrue();
    $taken = createStoreUser('customer');
    $this->putJson(route('sellers.update', $shop), sellerAdminForm(['email' => $taken->email]))->assertUnprocessable()->assertJsonValidationErrors('email');
    Notification::assertNothingSent();
});

test('seller ban toggles both ways without notifications mail or money changes', function () {
    Notification::fake();
    Mail::fake();
    actingAsStoreUser(createStoreStaff('ban_seller'));
    $shop = sellerAdminShop();
    foreach ([1, 0] as $banned) {
        $this->post(route('sellers.ban', [$shop, 'page' => 2]))->assertRedirect(route('sellers.index', ['page' => 2]));
        expect((int) $shop->user->fresh()->banned)->toBe($banned);
    }
    expect((float) $shop->fresh()->admin_to_pay)->toBe(125.0)->and((float) $shop->user->fresh()->balance)->toBe(55.0);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('accept reject and approved switch retain distinct verification transitions and cache effects', function () {
    Notification::fake();
    Mail::fake();
    actingAsStoreUser(createStoreStaff('edit_seller'));
    $shop = sellerAdminShop(['verification_info' => '[{"label":"Company","type":"text","value":"Acme"}]']);
    $info = $shop->verification_info;
    Cache::put('verified_sellers_id', [999]);
    $this->post(route('sellers.approve', $shop))->assertRedirect(route('sellers.index'));
    expect((int) $shop->fresh()->verification_status)->toBe(1)->and($shop->fresh()->verification_info)->toBe($info)->and(Cache::has('verified_sellers_id'))->toBeFalse();
    foreach ([0, 1] as $status) {
        Cache::put('verified_sellers_id', [999]);
        $this->post(route('sellers.approved', ['page' => 2]), ['id' => $shop->id, 'status' => $status])->assertRedirect(route('sellers.index', ['page' => 2]));
        expect((int) $shop->fresh()->verification_status)->toBe($status)->and($shop->fresh()->verification_info)->toBe($info)->and(Cache::has('verified_sellers_id'))->toBeFalse();
    }
    Cache::put('verified_sellers_id', [999]);
    $this->post(route('sellers.reject', $shop))->assertRedirect(route('sellers.index'));
    expect((int) $shop->fresh()->verification_status)->toBe(0)->and($shop->fresh()->verification_info)->toBeNull()->and(Cache::has('verified_sellers_id'))->toBeFalse()
        ->and((float) $shop->fresh()->admin_to_pay)->toBe(125.0)->and((float) $shop->user->fresh()->balance)->toBe(55.0);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('seller approval and profile references are validation errors and bulk validation is atomic', function () {
    actingAsStoreUser(createStoreStaff('edit_seller', 'view_seller_profile', 'delete_seller'));
    $shop = sellerAdminShop();
    $this->postJson(route('sellers.approved'), ['id' => 999999, 'status' => 1])->assertUnprocessable()->assertJsonValidationErrors('id');
    $this->postJson(route('sellers.approved'), ['id' => $shop->id, 'status' => 2])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson(route('sellers.profile_modal'), ['id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('id');
    foreach ([[], [$shop->id, 999999], [$shop->id, $shop->id]] as $ids) {
        $this->deleteJson(route('bulk-seller-delete'), ['id' => $ids])->assertUnprocessable();
    }
    expect($shop->fresh())->not->toBeNull()->and((int) $shop->fresh()->verification_status)->toBe(0)->and($shop->user->fresh())->not->toBeNull();
});

test('seller delete and bulk delete preserve the exact legacy deletion boundary', function (bool $bulk) {
    actingAsStoreUser(createStoreStaff('delete_seller'));
    $shop = sellerAdminShop();
    $noise = sellerAdminShop();
    $sellerRow = Seller::forceCreate(['user_id' => $shop->user_id]);
    $product = createStoreProduct(['user_id' => $shop->user_id]);
    $noiseProduct = createStoreProduct(['user_id' => $noise->user_id]);
    $bought = orderStoreProduct($shop->user, $noiseProduct);
    $sold = orderStoreProduct($noise->user, $product);
    $payment = DB::table('payments')->insertGetId(['seller_id' => $shop->user_id, 'amount' => 7]);
    $withdraw = createWithdrawRequestFor($shop->user, 4);
    $extra = $bulk ? sellerAdminShop() : null;
    $response = $bulk ? $this->delete(route('bulk-seller-delete', ['page' => 2]), ['id' => [$shop->id, $extra->id]]) : $this->delete(route('sellers.destroy', [$shop, 'page' => 2]));
    $response->assertRedirect(route('sellers.index', ['page' => 2]));
    expect(Shop::find($shop->id))->toBeNull()->and(StoreUser::find($shop->user_id))->toBeNull()->and(Product::find($product->id))->toBeNull()
        ->and(Order::find($bought->order_id))->toBeNull()->and(OrderDetail::find($bought->id))->toBeNull()
        ->and(DB::table('sellers')->where('id', $sellerRow->id)->exists())->toBeTrue()
        ->and(DB::table('payments')->where('id', $payment)->value('amount'))->toEqual(7)
        ->and(DB::table('seller_withdraw_requests')->where('id', $withdraw)->exists())->toBeTrue()
        ->and(OrderDetail::find($sold->id))->not->toBeNull()->and(Order::find($sold->order_id))->not->toBeNull()
        ->and(Product::find($noiseProduct->id))->not->toBeNull()->and((float) $noise->fresh()->admin_to_pay)->toBe(125.0);
    if ($extra) {
        expect(Shop::find($extra->id))->toBeNull();
    }
})->with([false, true]);

test('log in as seller keeps the store guard remember session and standard Login audit event', function () {
    $warehouse = actingAsAdmin();
    actingAsStoreUser(createStoreStaff('login_as_seller'));
    $shop = sellerAdminShop();
    $events = [];
    Event::listen(Login::class, function (Login $event) use (&$events) {
        $events[] = $event;
    });
    $session = session()->getId();
    $this->get(route('sellers.login', $shop))->assertRedirect(route('seller.dashboard'));
    $this->assertAuthenticatedAs($shop->user, 'store');
    $this->assertAuthenticatedAs($warehouse, 'web');
    expect(session()->getId())->not->toBe($session);
    expect($events)->toHaveCount(1)->and($events[0]->guard)->toBe('store')->and($events[0]->user->id)->toBe($shop->user_id)->and($events[0]->remember)->toBeTrue();
});

test('the verification screen renders full escaped data and confined private file links', function () {
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    $shop = sellerAdminShop(['verification_info' => json_encode([
        ['label' => '<script>label</script>', 'type' => 'text', 'value' => '<img onerror=alert(1)>'],
        ['label' => 'Choices', 'type' => 'multi_select', 'value' => '["A","B"]'],
        ['label' => 'File', 'type' => 'file', 'value' => 'uploads/verification_form/doc.pdf'],
    ])]);
    $this->get(route('sellers.show_verification_request', $shop))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/Sellers/Verification')
        ->has('seller', fn (Assert $row) => $row->where('id', $shop->id)->where('name', 'Seller shop')->where('address', 'Shop street')->where('approved', false)->where('has_verification', true)
            ->where('user', ['name' => 'Seller Person', 'email' => $shop->user->email, 'address' => 'User street', 'phone' => '123'])
            ->where('verification', [
                ['label' => '<script>label</script>', 'value' => '<img onerror=alert(1)>', 'file_url' => null],
                ['label' => 'Choices', 'value' => 'A, B', 'file_url' => null],
                ['label' => 'File', 'value' => 'uploads/verification_form/doc.pdf', 'file_url' => route('sellers.verification_file', 'doc.pdf')],
            ])));
});

test('profile returns only permitted detail fields safe URLs and the original paid-line sum', function () {
    actingAsStoreUser(createStoreStaff('view_seller_profile'));
    $shop = sellerAdminShop(['facebook' => 'javascript:alert(1)', 'twitter' => 'https://example.com/seller', 'bank_name' => '<b>Bank</b>', 'bank_routing_no' => 123]);
    $product = createStoreProduct(['user_id' => $shop->user_id]);
    $buyer = createStoreUser('customer');
    $paidLine = orderStoreProduct($buyer, $product, 'paid');
    $paidLine->update(['payment_status' => 'unpaid', 'quantity' => 9]);
    orderStoreProduct($buyer, $product, 'unpaid');
    orderStoreProduct($buyer, createStoreProduct(['user_id' => createStoreUser('seller')->id]), 'paid');
    expect($this->postJson(route('sellers.profile_modal'), ['id' => $shop->id])->assertOk()->json())->toBe([
        'id' => $shop->id, 'name' => 'Seller Person', 'shop_name' => 'Seller shop', 'address' => 'Shop street', 'phone' => '123', 'avatar_url' => null, 'shop_url' => route('shop.visit', $shop->slug),
        'social' => ['facebook' => null, 'twitter' => 'https://example.com/seller', 'google' => null],
        'bank_name' => '<b>Bank</b>', 'bank_acc_name' => null, 'bank_acc_no' => null, 'bank_routing_no' => '123',
        'products_count' => 1, 'orders_count' => 2, 'sold_amount' => single_price(5), 'wallet_balance' => single_price(55),
    ]);
});

// Moved from LegacyShopVerificationTest; seller upload cases remain there.
test('staff who may view sellers open a verification document and missing files return 404', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/verification_form/doc.pdf', 'pdf bytes');
    actingAsStoreUser(createStoreStaff('view_all_seller'));
    $this->get(route('sellers.verification_file', 'doc.pdf'))->assertOk()->assertDownload('doc.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('sellers.verification_file', 'missing.pdf'))->assertNotFound();
    Storage::disk('local')->put('outside.pdf', 'private other document');
    foreach (['..', '..\\outside.pdf', '%2e%2e%2foutside.pdf'] as $name) {
        expect($this->get(route('sellers.verification_file', $name))->getStatusCode())->toBeIn([403, 404]);
    }
});

foreach (['edit' => 'get', 'update' => 'put', 'destroy' => 'delete', 'ban' => 'post', 'approve' => 'post', 'reject' => 'post', 'login' => 'get', 'show_verification_request' => 'get'] as $action => $method) {
    test("seller {$action} returns 404 for a missing bound shop", function () use ($action, $method) {
        actingAsStoreUser(createStoreAdmin());
        $this->{$method}(route('sellers.'.$action, 999999), sellerAdminForm())->assertNotFound();
    });
}

foreach (['index' => 'get', 'create' => 'get', 'store' => 'post', 'edit' => 'get', 'update' => 'put', 'destroy' => 'delete', 'ban' => 'post', 'approve' => 'post', 'reject' => 'post', 'login' => 'get', 'show_verification_request' => 'get', 'profile_modal' => 'post', 'approved' => 'post', 'verification_file' => 'get', 'bulk-seller-delete' => 'delete'] as $action => $method) {
    test("seller {$action} refuses unpermitted callers and changes no seller data or session identity", function (string $caller) use ($action, $method) {
        $shop = sellerAdminShop();
        $product = createStoreProduct(['user_id' => $shop->user_id]);
        $order = orderStoreProduct($shop->user, $product);
        actingAsRefusedStoreCaller($caller);
        $actor = auth('store')->id();
        $before = [$shop->fresh()->getAttributes(), $shop->user->fresh()->getAttributes(), DB::table('sellers')->count(), StoreUser::count()];
        $name = $action === 'bulk-seller-delete' ? $action : 'sellers.'.$action;
        $params = in_array($action, ['edit', 'update', 'destroy', 'ban', 'approve', 'reject', 'login', 'show_verification_request']) ? [$shop] : ($action === 'verification_file' ? ['doc.pdf'] : []);
        $response = $this->{$method}(route($name, $params), [...sellerAdminForm(), 'id' => $action === 'bulk-seller-delete' ? [$shop->id] : $shop->id, 'status' => 1]);
        assertStoreCallerRefused($response, $caller);
        expect([$shop->fresh()->getAttributes(), $shop->user->fresh()->getAttributes(), DB::table('sellers')->count(), StoreUser::count()])->toBe($before)
            ->and(Product::find($product->id))->not->toBeNull()->and(OrderDetail::find($order->id))->not->toBeNull()->and(Order::find($order->order_id))->not->toBeNull()
            ->and(auth('store')->id())->toBe($actor);
    })->with('refused store admin callers');
}

test('failed account creation leaves no half-created seller or role grants', function () {
    Notification::fake();
    actingAsStoreUser(createStoreStaff('edit_seller'));
    $users = StoreUser::count();
    Event::listen('eloquent.creating: '.Shop::class, fn () => throw new RuntimeException('shop failure'));
    try {
        $this->post(route('sellers.store'), [...sellerAdminForm(), 'roles' => ['Super Admin'], 'balance' => 999, 'user_type' => 'admin'])->assertServerError();
    } finally {
        Event::forget('eloquent.creating: '.Shop::class);
    }
    expect(StoreUser::count())->toBe($users)->and(DB::table('sellers')->count())->toBe(0)->and(Shop::count())->toBe(0);
});

test('bulk deletion rolls back earlier sellers when a later deletion fails', function () {
    actingAsStoreUser(createStoreStaff('delete_seller'));
    $first = sellerAdminShop();
    $second = sellerAdminShop();
    $product = createStoreProduct(['user_id' => $first->user_id]);
    Event::listen('eloquent.deleting: '.Shop::class, function (Shop $shop) use ($second) {
        if ($shop->id === $second->id) {
            throw new RuntimeException('delete failure');
        }
    });
    try {
        $this->delete(route('bulk-seller-delete'), ['id' => [$first->id, $second->id]])->assertServerError();
    } finally {
        Event::forget('eloquent.deleting: '.Shop::class);
    }
    expect($first->fresh())->not->toBeNull()->and($second->fresh())->not->toBeNull()->and($first->user->fresh())->not->toBeNull()->and(Product::find($product->id))->not->toBeNull();
});

test('seller permission names routes and retained payout handler match their previous boundaries', function () {
    $permissions = ['index' => 'view_all_seller', 'create' => 'edit_seller', 'store' => 'edit_seller', 'edit' => 'edit_seller', 'update' => 'edit_seller', 'ban' => 'ban_seller', 'destroy' => 'delete_seller', 'approve' => 'edit_seller', 'reject' => 'edit_seller', 'login' => 'login_as_seller', 'show_verification_request' => 'view_all_seller', 'verification_file' => 'view_all_seller', 'profile_modal' => 'view_seller_profile', 'approved' => 'edit_seller'];
    foreach ($permissions as $name => $permission) {
        $route = Route::getRoutes()->getByName('sellers.'.$name);
        expect($route->getControllerClass())->toBe(SellerController::class)->and($route->gatherMiddleware())->toContain('permission:'.$permission);
    }
    expect(Route::has('sellers.show'))->toBeFalse()
        ->and(Route::getRoutes()->getByName('bulk-seller-delete')->methods())->toBe(['DELETE'])
        ->and(Route::getRoutes()->getByName('sellers.destroy')->methods())->toBe(['DELETE'])
        ->and(Route::getRoutes()->getByName('sellers.payment_modal')->getControllerClass())->toBe(LegacySellerController::class);
    $onlyApproval = createStoreStaff('approve_seller');
    actingAsStoreUser($onlyApproval);
    $shop = sellerAdminShop();
    $this->post(route('sellers.approved'), ['id' => $shop->id, 'status' => 1])->assertForbidden();
    expect((int) $shop->fresh()->verification_status)->toBe(0);
});

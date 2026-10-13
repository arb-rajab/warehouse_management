<?php

use App\Models\StoreUser;
use App\Notifications\OrderNotification;
use App\Notifications\UnorderedCartNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function adminDatabaseNotification(StoreUser $user, string $type = OrderNotification::class, array $data = []): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id, 'type' => $type, 'notifiable_type' => StoreUser::class, 'notifiable_id' => $user->id,
        'data' => json_encode(['order_id' => 7, 'order_code' => 'ORDER-7', 'status' => 'payment_received', 'private' => 'must not leak', ...$data]),
        'read_at' => null, 'created_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-01 12:00:00',
    ]);

    return $id;
}

test('personal admin notifications return a narrow order payload and mark only the callers notifications as read', function () {
    $user = createStoreStaff();
    actingAsStoreUser($user);
    $id = adminDatabaseNotification($user);
    $noise = adminDatabaseNotification(createStoreUser('admin'));
    $this->get(route('admin.all-notification'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Notifications/Index')->where('filters', ['per_page' => 20])
        ->has('notifications.data', 1)->has('notifications.data.0', fn (Assert $row) => $row
        ->where('id', $id)->where('kind', 'order')->where('order_code', 'ORDER-7')->where('status', 'payment_received')
        ->where('customer_name', null)->where('message', null)->where('url', fn (string $url): bool => str_contains($url, '/temp/admin/orders/') && str_ends_with($url, '/show'))
        ->where('created_at', '2026-10-01T12:00:00+00:00')));
    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->not->toBeNull()
        ->and(DB::table('notifications')->where('id', $noise)->value('read_at'))->toBeNull();
});

test('personal notifications preserve cart links and strip unrelated notification data', function () {
    $user = createStoreStaff();
    actingAsStoreUser($user);
    $customer = createStoreUser('customer');
    adminDatabaseNotification($user, UnorderedCartNotification::class, ['user_id' => $customer->id, 'user_name' => '<b>Customer</b>', 'message' => 'Cart waiting']);
    $this->get(route('admin.all-notification'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data.0', fn (Assert $row) => $row->has('id')->where('kind', 'cart')
            ->where('order_code', null)->where('status', null)->where('customer_name', '<b>Customer</b>')
            ->where('message', 'Cart waiting')->where('url', route('customers.whatsapp_contact', $customer))
            ->where('created_at', '2026-10-01T12:00:00+00:00')));
});

test('personal admin notifications paginate and mark unread records beyond the current page', function () {
    $user = createStoreStaff();
    actingAsStoreUser($user);
    foreach (range(1, 23) as $number) {
        adminDatabaseNotification($user);
    }
    adminDatabaseNotification(createStoreUser('admin'));
    $this->get(route('admin.all-notification'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('notifications.data', 20)->where('notifications.meta.total', 23));
    $this->get(route('admin.all-notification', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('notifications.data', 3));
    expect($user->unreadNotifications()->count())->toBe(0);
    $this->get(route('admin.all-notification', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('notifications.data', 10));
    $this->get(route('admin.all-notification', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('personal admin notifications refuse non admin callers without marking anything read', function (string $caller) {
    $user = createStoreUser('admin');
    $id = adminDatabaseNotification($user);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('admin.all-notification')), $caller);
    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->toBeNull();
})->with(['guest', 'customer']);

test('storefront notifications remain server rendered', function () {
    actingAsStoreUser(createStoreUser('customer'));
    $this->get(route('all-notifications'))->assertOk()->assertViewIs('shop.frontend.user.customer.notification.index');
});

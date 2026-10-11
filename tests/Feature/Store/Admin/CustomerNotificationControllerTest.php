<?php

use App\Models\Notification;
use Google\Client as GoogleClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function customerNotification(array $attributes = []): Notification
{
    return Notification::query()->create([
        'user_id' => createStoreUser('customer')->id,
        'title' => 'Hello', 'body' => 'Private notification body', 'status' => 'sent',
        'response' => '{"secret":"provider response"}', ...$attributes,
    ]);
}

function fakeCustomerNotificationCredentials(): void
{
    $client = Mockery::mock(GoogleClient::class);
    $client->shouldReceive('setAuthConfig')->with(storage_path('app/json/file.json'))->once();
    $client->shouldReceive('addScope')->with('https://www.googleapis.com/auth/firebase.messaging')->once();
    $client->shouldReceive('refreshTokenWithAssertion')->once();
    $client->shouldReceive('getAccessToken')->once()->andReturn(['access_token' => 'fake-secret-token']);
    app()->instance(GoogleClient::class, $client);
}

test('customer notification list renders a narrow payload and scoped customer relationship', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $notification = customerNotification();
    $customer = $notification->user;
    createStoreUser('customer');

    $this->get(route('admin.notifications.all'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Notifications/Customers/Index')
        ->where('filters', ['per_page' => 20])
        ->has('notifications.data', 1)
        ->has('notifications.data.0', fn (Assert $row) => $row
            ->where('id', $notification->id)->where('title', 'Hello')->where('status', 'sent')
            ->where('customer', ['id' => $customer->id, 'name' => $customer->name])));
});

test('customer notification list pages deterministically and validates page sizes', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $customer = createStoreUser('customer');
    $ids = collect(range(1, 23))->map(fn () => customerNotification(['user_id' => $customer->id])->id);
    $first = $this->get(route('admin.notifications.all'))->assertOk()->inertiaProps('notifications');
    $second = $this->get(route('admin.notifications.all', ['page' => 2]))->assertOk()->inertiaProps('notifications');
    expect(array_column($first['data'], 'id'))->toBe($ids->take(20)->all())
        ->and(array_column($second['data'], 'id'))->toBe($ids->slice(20, 20)->values()->all())
        ->and($first['meta']['total'])->toBe(23);
    $this->get(route('admin.notifications.all', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('notifications.data', 10));
    $this->get(route('admin.notifications.all', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('customer notification create offers only customers with narrow recipient details in name order', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $a = createStoreUser('customer');
    $a->forceFill(['name' => 'Alpha', 'AccSysID' => 100])->save();
    $z = createStoreUser('customer');
    $z->forceFill(['name' => 'Zulu', 'AccSysID' => null])->save();
    createStoreUser('seller');

    $this->get(route('admin.notifications.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Notifications/Customers/Create')->where('customers', [
            ['id' => $z->id, 'name' => 'Zulu', 'account_id' => null],
            ['id' => $a->id, 'name' => 'Alpha', 'account_id' => 100],
        ]));
});

test('customer notification send uses the selected customer device key and records a successful push', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $customer = createStoreUser('customer');
    $customer->forceFill(['device_key' => 'recipient-key', 'device_token' => 'different-legacy-token'])->save();
    $noise = createStoreUser('customer');
    $noise->forceFill(['device_key' => 'noise-key'])->save();
    fakeCustomerNotificationCredentials();
    config(['services.fcm.project_id' => 'test-project']);
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/123'])]);

    $this->post(route('admin.notifications.send'), ['user_id' => $customer->id, 'title' => 'Hello', 'body' => 'Message body'])
        ->assertRedirect(route('admin.notifications.all'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
        && $request->hasHeader('Authorization', 'Bearer fake-secret-token')
        && $request->data() === ['message' => ['token' => 'recipient-key', 'notification' => ['title' => 'Hello', 'body' => 'Message body']]]);
    $this->assertDatabaseHas('customers_notifications', ['user_id' => $customer->id, 'title' => 'Hello', 'body' => 'Message body', 'status' => 'sent']);
    $this->assertDatabaseMissing('customers_notifications', ['user_id' => $noise->id]);
});

test('customer notification send rejects invalid recipients and fields without sending', function (array $input, string $field) {
    actingAsStoreUser(createStoreStaff('add_notification'));
    Http::fake();
    $this->post(route('admin.notifications.send'), [...['user_id' => createStoreUser('customer')->id, 'title' => 'Hello', 'body' => 'Body'], ...$input])
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('customers_notifications', 0);
    Http::assertNothingSent();
})->with([
    'missing recipient' => [['user_id' => null], 'user_id'],
    'unknown recipient' => [['user_id' => 999999], 'user_id'],
    'missing title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('a', 256)], 'title'],
    'missing body' => [['body' => ''], 'body'],
]);

test('customer notification send refuses a seller recipient and a customer with no device key', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    Http::fake();
    $this->post(route('admin.notifications.send'), ['user_id' => createStoreUser('seller')->id, 'title' => 'Hello', 'body' => 'Body'])->assertSessionHasErrors('user_id');
    $this->post(route('admin.notifications.send'), ['user_id' => createStoreUser('customer')->id, 'title' => 'Hello', 'body' => 'Body'])
        ->assertRedirect(route('admin.notifications.create'))->assertSessionHasErrors('user_id');
    $this->assertDatabaseCount('customers_notifications', 0);
    Http::assertNothingSent();
});

test('customer notification does not claim a failed FCM response was sent', function () {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $customer = createStoreUser('customer');
    $customer->forceFill(['device_key' => 'recipient-key'])->save();
    fakeCustomerNotificationCredentials();
    Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
    $this->post(route('admin.notifications.send'), ['user_id' => $customer->id, 'title' => 'Hello', 'body' => 'Body'])
        ->assertRedirect(route('admin.notifications.create'))->assertSessionHasErrors('body');
    $this->assertDatabaseCount('customers_notifications', 0);
});

test('customer notification delete preserves query and deletes only its bound record', function () {
    actingAsStoreUser(createStoreStaff('delete_notification'));
    $notification = customerNotification();
    $noise = customerNotification();
    $this->delete(route('admin.notifications.delete', ['notification' => $notification->id, 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('admin.notifications.all', ['page' => 2, 'per_page' => 10]));
    $this->assertModelMissing($notification);
    $this->assertModelExists($noise);
    $this->delete(route('admin.notifications.delete', 999999))->assertNotFound();
});

test('customer notification index refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('admin.notifications.all')), $caller);
})->with('refused store admin callers');

test('customer notification create refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('admin.notifications.create')), $caller);
})->with('refused store admin callers');

test('customer notification store refuses unauthorized callers without sending or writing', function (string $caller) {
    $notification = customerNotification();
    actingAsRefusedStoreCaller($caller);
    Http::fake();
    assertStoreCallerRefused($this->post(route('admin.notifications.send'), ['user_id' => $notification->user_id, 'title' => 'Forbidden', 'body' => 'Forbidden']), $caller);
    $this->assertDatabaseCount('customers_notifications', 1);
    $this->assertDatabaseMissing('customers_notifications', ['title' => 'Forbidden']);
    Http::assertNothingSent();
})->with('refused store admin callers');

test('customer notification destroy refuses unauthorized callers without deleting', function (string $caller) {
    $notification = customerNotification();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete(route('admin.notifications.delete', $notification)), $caller);
    $this->assertModelExists($notification);
})->with('refused store admin callers');

test('customer notification send handles missing credentials and connection failures without persisting', function (string $failure) {
    actingAsStoreUser(createStoreStaff('add_notification'));
    $customer = createStoreUser('customer');
    $customer->forceFill(['device_key' => 'recipient-key'])->save();
    Http::fake(['*' => Http::failedConnection()]);
    if ($failure === 'credentials') {
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setAuthConfig')->once()->andThrow(new RuntimeException('secret credential path'));
        app()->instance(GoogleClient::class, $client);
    } else {
        fakeCustomerNotificationCredentials();
    }
    $this->post(route('admin.notifications.send'), ['user_id' => $customer->id, 'title' => 'Hello', 'body' => 'Body'])
        ->assertRedirect(route('admin.notifications.create'))->assertSessionHasErrors(['body' => __('messages.notification_send_failed')]);
    $this->assertDatabaseCount('customers_notifications', 0);
})->with(['credentials', 'connection']);

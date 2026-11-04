<?php

use App\Models\Cart;
use App\Models\FirebaseNotification;
use App\Services\FcmPushService;
use App\Utility\NotificationUtility;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pushes go through Firebase Cloud Messaging's v1 API. The legacy endpoint
 * (fcm/send with a server key) is shut down, so the order pushes sent through
 * it never arrived, and an order placed through the app sent none at all.
 */
beforeEach(function () {
    $directory = storage_path('framework/testing');
    @mkdir($directory, 0755, true);
    file_put_contents($directory.'/fcm-test.json', '{}');
    config(['services.fcm.firebase_credentials' => 'framework/testing/fcm-test.json', 'services.fcm.firebase_project' => 'albarakabv-store']);
    // The service signs in with Google; the test hands it a token instead.
    app()->bind(FcmPushService::class, fn () => new class extends FcmPushService
    {
        protected function fetchAccessToken(): string
        {
            return 'google-access-token';
        }
    });
    Cache::flush();
});

afterEach(fn () => @unlink(storage_path('framework/testing/fcm-test.json')));

test('a push is posted to the v1 endpoint of the configured project with the data the app reads', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/albarakabv-store/messages/1'])]);

    $result = app(FcmPushService::class)->send('device-1', 'Order placed', 'Order 20261103 is placed', ['item_type' => 'order', 'item_type_id' => 77]);

    expect($result)->toBe(FcmPushService::SENT);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/albarakabv-store/messages:send'
        && $request->hasHeader('Authorization', 'Bearer google-access-token')
        && $request['message']['token'] === 'device-1'
        && $request['message']['notification'] === ['title' => 'Order placed', 'body' => 'Order 20261103 is placed']
        && $request['message']['data'] === ['click_action' => 'FLUTTER_NOTIFICATION_CLICK', 'item_type' => 'order', 'item_type_id' => '77']);
});

test('a push is not attempted without credentials, and says why', function () {
    config(['services.fcm.firebase_credentials' => null]);
    Http::fake();

    expect(app(FcmPushService::class)->send('device-1', 'a', 'b'))->toBe(FcmPushService::NOT_CONFIGURED);
    Http::assertNothingSent();
});

test('a token Firebase does not know is reported and cleared from its account only', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND']], 404)]);
    $owner = createStoreUser('customer');
    $owner->forceFill(['device_token' => 'dead-token'])->save();
    $other = createStoreUser('customer');
    $other->forceFill(['device_token' => 'live-token'])->save();

    $result = NotificationUtility::sendFirebaseNotification((object) ['device_token' => 'dead-token', 'title' => 'a', 'text' => 'b', 'type' => 'order', 'id' => 1, 'user_id' => $owner->id]);

    expect($result)->toBe(FcmPushService::INVALID_TOKEN)
        ->and($owner->fresh()->device_token)->toBeNull()
        ->and($other->fresh()->device_token)->toBe('live-token')
        ->and(FirebaseNotification::count())->toBe(0);
});

test('a server error from Firebase is a failure and keeps the token', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
    $owner = createStoreUser('customer');
    $owner->forceFill(['device_token' => 'token'])->save();

    $result = NotificationUtility::sendFirebaseNotification((object) ['device_token' => 'token', 'title' => 'a', 'text' => 'b', 'type' => 'order', 'id' => 1, 'user_id' => $owner->id]);

    expect($result)->toBe(FcmPushService::FAILED)->and($owner->fresh()->device_token)->toBe('token');
});

test('an order placed through the app pushes to the customer when push is switched on', function (int $setting, int $pushes) {
    seedStoreCurrency();
    storeSetting('no_of_decimals', 2);
    storeSetting('cash_payment', 1);
    storeSetting('google_firebase', $setting);
    createStoreUser('admin');
    $customer = createStoreUser('customer');
    $customer->forceFill(['device_token' => 'customer-phone'])->save();
    $product = createStoreProduct(['cash_on_delivery' => 1]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => 1, 'variation' => '', 'price' => 2.5, 'address_id' => addStoreAddress($customer->id)]);
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertOk()->assertJsonPath('result', true);

    $orderId = DB::table('orders')->value('id');
    Http::assertSentCount($pushes);
    if ($pushes > 0) {
        Http::assertSent(fn (Request $request) => $request['message']['token'] === 'customer-phone'
            && $request['message']['data']['item_type'] === 'order'
            && $request['message']['data']['item_type_id'] === (string) $orderId);
    }
    expect(FirebaseNotification::count())->toBe($pushes);
})->with([
    'push on' => [1, 1],
    'push off' => [0, 0],
]);

test('store:send-test-push sends to the token and reports the project', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    $this->artisan('store:send-test-push', ['token' => 'phone-1', '--title' => 'Hello'])
        ->expectsOutputToContain('albarakabv-store')
        ->expectsOutputToContain('Sent')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['message']['token'] === 'phone-1' && $request['message']['notification']['title'] === 'Hello');
});

test('store:send-test-push fails with a reason when push is not set up or the token is not known', function () {
    config(['services.fcm.firebase_credentials' => null]);
    $this->artisan('store:send-test-push', ['token' => 'phone-1'])->expectsOutputToContain('FIREBASE_CREDENTIALS')->assertFailed();

    config(['services.fcm.firebase_credentials' => 'framework/testing/fcm-test.json']);
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404)]);
    $this->artisan('store:send-test-push', ['token' => 'phone-1'])->expectsOutputToContain('another project')->assertFailed();
});

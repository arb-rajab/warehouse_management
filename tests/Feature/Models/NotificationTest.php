<?php

use App\Models\Notification;

test('a customer notification belongs only to its store recipient', function () {
    $customer = createStoreUser('customer');
    $noise = createStoreUser('customer');
    $notification = Notification::query()->create(['user_id' => $customer->id, 'title' => 'Hello', 'body' => 'Message', 'status' => 'sent', 'response' => '{}']);
    Notification::query()->create(['user_id' => $noise->id, 'title' => 'Other', 'body' => 'Other message', 'status' => 'sent']);

    expect($notification->user?->id)->toBe($customer->id)
        ->and($notification->user?->id)->not->toBe($noise->id)
        ->and($notification->getTable())->toBe('customers_notifications')
        ->and($notification->title)->toBe('Hello');
});

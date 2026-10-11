<?php

use App\Models\Visit;

function modelTestVisit(array $overrides = []): Visit
{
    return Visit::create(['rep_id' => createStoreUser('customer')->id, 'customer_id' => createStoreUser('customer')->id, ...$overrides]);
}

test('visits resolve their own representative customer and optional order', function () {
    $rep = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);
    $noise = modelTestVisit();
    $visit = modelTestVisit(['rep_id' => $rep->id, 'customer_id' => $customer->id, 'order_id' => $order->id]);
    expect($visit->rep->id)->toBe($rep->id)->and($visit->customer->id)->toBe($customer->id)->and($visit->order->id)->toBe($order->id)
        ->and($noise->order)->toBeNull()->and($noise->rep->id)->not->toBe($rep->id);
});

test('the last visit helper keeps its existing creation-day boundary', function () {
    $visit = backdate(modelTestVisit(), '2026-10-07 08:00:00');
    $last = backdate(modelTestVisit(), '2026-10-07 10:00:00');
    backdate(modelTestVisit(), '2026-10-08 10:00:00');
    expect($visit->get_last_visit_order_in_same_day()->id)->toBe($last->id);
    $visit->forceFill(['created_at' => '2026-10-01 10:00:00']);
    expect($visit->get_last_visit_order_in_same_day())->toBeNull();
});

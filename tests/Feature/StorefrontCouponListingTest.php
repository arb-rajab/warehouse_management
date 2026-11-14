<?php

use App\Models\Coupon;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

test('the public coupon listing renders every coupon past the third, each with a gradient', function () {
    $admin = createStoreUser('admin');

    foreach (range(0, 8) as $i) {
        Coupon::forceCreate([
            'user_id' => $admin->id,
            'type' => 'cart_base',
            'code' => 'GRADIENT-'.$i,
            'discount' => 1,
            'discount_type' => 'amount',
            'start_date' => now()->subDay()->timestamp,
            'end_date' => now()->addDay()->timestamp,
            'details' => json_encode(['min_buy' => 0, 'max_discount' => 0]),
        ]);
    }

    $content = $this->get(route('coupons.all'))->assertOk()->getContent();

    foreach (range(0, 8) as $i) {
        expect($content)->toContain('GRADIENT-'.$i);
    }

    // Boxes repeat the three gradients in order: 9 coupons -> 3 of each.
    expect(substr_count($content, '#e2583e 0%, #bf1931 100%'))->toBe(3)
        ->and(substr_count($content, '#7cc4c3 0%, #479493 100%'))->toBe(3)
        ->and(substr_count($content, '#98b3d1 0%, #5f4a8b 100%'))->toBe(3);
});

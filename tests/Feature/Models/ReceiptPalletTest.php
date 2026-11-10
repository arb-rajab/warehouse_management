<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptPallet;
use Carbon\CarbonImmutable;

test('an entry belongs to its receipt', function () {
    $receipt = Receipt::factory()->create();
    $entry = ReceiptPallet::factory()->for($receipt)->create();
    Receipt::factory()->create(); // noise

    expect($entry->receipt->id)->toBe($receipt->id);
});

test('an entry belongs to its product', function () {
    $product = Product::factory()->create();
    $entry = ReceiptPallet::factory()->create(['product_id' => $product->id]);
    Product::factory()->create(); // noise

    expect($entry->product->id)->toBe($product->id);
});

test('an entry has no pallet until its receipt is finalized, then belongs to the one that was created', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);
    Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]); // noise
    $draftEntry = ReceiptPallet::factory()->create();
    $entry = ReceiptPallet::factory()->for($receipt)->create(['pallet_id' => $pallet->id]);

    expect($draftEntry->pallet)->toBeNull()
        ->and($entry->pallet->id)->toBe($pallet->id);
});

test('the expiration date is cast to a date and may be null', function () {
    $dated = ReceiptPallet::factory()->create(['expiration_date' => '2027-03-04']);
    $undated = ReceiptPallet::factory()->create(['expiration_date' => null]);

    expect($dated->fresh()->expiration_date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($dated->fresh()->expiration_date->toDateString())->toBe('2027-03-04')
        ->and($undated->fresh()->expiration_date)->toBeNull();
});

test('the factory builds an entry of a draft receipt with no pallet yet', function () {
    $entry = ReceiptPallet::factory()->create();

    expect($entry->receipt->isFinalized())->toBeFalse()
        ->and($entry->pallet_id)->toBeNull()
        ->and($entry->boxes)->toBeGreaterThanOrEqual(1)
        ->and($entry->product)->not->toBeNull();
});

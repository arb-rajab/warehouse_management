<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

test('a pallet belongs to its product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    expect($pallet->product->id)->toBe($product->id);
    expect($pallet->product->id)->not->toBe($otherProduct->id);
});

test('a pallet can be created with its fillable remaining_boxes attribute', function () {
    $pallet = Pallet::factory()->create(['remaining_boxes' => 6]);

    expect($pallet->fresh()->remaining_boxes)->toBe(6);
});

test('a pallet belongs to its cell', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    expect($pallet->cell->id)->toBe($cell->id);
    expect($pallet->cell->id)->not->toBe($otherCell->id);
});

test('the expiration_date attribute is cast to a date', function () {
    $pallet = Pallet::factory()->create(['expiration_date' => '2027-01-15']);

    expect($pallet->fresh()->expiration_date)->toBeInstanceOf(CarbonImmutable::class);
    expect($pallet->fresh()->expiration_date->toDateString())->toBe('2027-01-15');
});

test('the state attribute reads the state of the pallets current cell', function () {
    $fullPallet = Pallet::factory()->create();
    $openedPallet = Pallet::factory()->opened()->create();

    expect($fullPallet->state)->toBe(CellState::Full);
    expect($openedPallet->state)->toBe(CellState::Opened);
});

test('isStaleAfter is false for a freshly stored pallet given any caller-chosen day count', function () {
    $pallet = Pallet::factory()->create();

    expect($pallet->isStaleAfter(3))->toBeFalse();
});

test('isStaleAfter is true once a pallet has been stored longer than the given day count', function () {
    $pallet = Pallet::factory()->stale()->create();

    expect($pallet->fresh()->isStaleAfter(3))->toBeTrue();
});

test('isStaleAfter is true exactly at the given day count boundary', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $days = 5;
    $pallet = backdate(
        Pallet::factory()->create(),
        now()->subDays($days)->toDateTimeString(),
    );

    expect($pallet->fresh()->isStaleAfter($days))->toBeTrue();

    Carbon::setTestNow();
});

test('isStaleAfter is false when the pallet is younger than the given day count', function () {
    $pallet = backdate(
        Pallet::factory()->create(),
        now()->subDays(2)->toDateTimeString(),
    );

    expect($pallet->fresh()->isStaleAfter(5))->toBeFalse();
});

test('toMapSummaryArray labels the pallet with the store\'s Arabic name when the locale is Arabic', function () {
    $product = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    // Noise: another pallet's product must not supply the label.
    Pallet::factory()->create(['product_id' => Product::factory()->create(['ar_name' => 'أدوات'])->id]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    app()->setLocale('ar');

    expect($pallet->toMapSummaryArray()['product_name'])->toBe('ودجات');
});

test('toMapSummaryArray falls back to the base name for a product the store never translated', function () {
    $product = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => '']);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    app()->setLocale('ar');

    expect($pallet->toMapSummaryArray()['product_name'])->toBe('Widgets');
});

test('toMapSummaryArray describes the pallet by its product, expiration date, and added_at', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $product = Product::factory()->imageUrl('https://example.com/widgets.png')->create(['name' => 'Widgets']);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'expiration_date' => '2026-09-15',
    ]);

    expect($pallet->toMapSummaryArray())->toBe([
        'product_id' => $product->id,
        'product_name' => 'Widgets',
        'product_image_url' => 'https://example.com/widgets.png',
        'expiration_date' => '2026-09-15',
        'added_at' => '2026-08-01T10:00:00+00:00',
    ]);

    Carbon::setTestNow();
});

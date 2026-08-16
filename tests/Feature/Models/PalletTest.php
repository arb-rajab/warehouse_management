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

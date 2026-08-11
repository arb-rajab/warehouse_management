<?php

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Database\QueryException;

test('a cell can only hold one pallet', function () {
    $pallet = Pallet::factory()->create();

    expect(fn () => Pallet::factory()->create(['cell_id' => $pallet->cell_id]))->toThrow(QueryException::class);
});

test('deleting a product that has a pallet is restricted', function () {
    $pallet = Pallet::factory()->create();
    $product = $pallet->product;

    expect(fn () => $product->delete())->toThrow(QueryException::class);
    expect(Product::find($product->id))->not->toBeNull();
});

test('deleting a cell that holds a pallet is restricted', function () {
    $pallet = Pallet::factory()->create();
    $cell = $pallet->cell;

    expect(fn () => $cell->delete())->toThrow(QueryException::class);
    expect(Cell::find($cell->id))->not->toBeNull();
});

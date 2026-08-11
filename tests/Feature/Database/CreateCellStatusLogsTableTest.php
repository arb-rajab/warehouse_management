<?php

use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;

test('deleting a cell referenced by a cell status log is restricted', function () {
    $log = CellStatusLog::factory()->create();
    $cell = $log->cell;

    expect(fn () => $cell->delete())->toThrow(QueryException::class);
    expect(Cell::find($cell->id))->not->toBeNull();
});

test('deleting a cell referenced as another logs related cell is restricted', function () {
    $relatedCell = Cell::factory()->create();
    CellStatusLog::factory()->create(['related_cell_id' => $relatedCell->id]);

    expect(fn () => $relatedCell->delete())->toThrow(QueryException::class);
    expect(Cell::find($relatedCell->id))->not->toBeNull();
});

test('deleting a user referenced by a cell status log is restricted', function () {
    $log = CellStatusLog::factory()->create();
    $user = $log->user;

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting a product referenced by a cell status log nulls the logs product_id', function () {
    $product = Product::factory()->create();
    $log = CellStatusLog::factory()->create(['product_id' => $product->id]);

    $product->delete();

    expect($log->fresh()->product_id)->toBeNull();
});

<?php

use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;

test('deleting a verification round referenced by a report is restricted', function () {
    $report = CellVerificationReport::factory()->create();
    $round = $report->round;

    expect(fn () => $round->delete())->toThrow(QueryException::class);
    expect(CellVerificationRound::find($round->id))->not->toBeNull();
});

test('deleting a cell referenced by a report is restricted', function () {
    $report = CellVerificationReport::factory()->create();
    $cell = $report->cell;

    expect(fn () => $cell->delete())->toThrow(QueryException::class);
    expect(Cell::find($cell->id))->not->toBeNull();
});

test('deleting a user referenced by a report is restricted', function () {
    $report = CellVerificationReport::factory()->create();
    $user = $report->user;

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting a product referenced as the expected product nulls the reports expected_product_id', function () {
    $product = Product::factory()->create();
    $report = CellVerificationReport::factory()->create(['expected_product_id' => $product->id]);

    $product->delete();

    expect($report->fresh()->expected_product_id)->toBeNull();
});

test('deleting a product referenced as the reported product nulls the reports reported_product_id', function () {
    $product = Product::factory()->create();
    $report = CellVerificationReport::factory()->create(['reported_product_id' => $product->id]);

    $product->delete();

    expect($report->fresh()->reported_product_id)->toBeNull();
});

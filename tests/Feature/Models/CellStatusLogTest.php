<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

test('a cell status log belongs to its cell', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $log = CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    expect($log->cell->id)->toBe($cell->id);
    expect($log->cell->id)->not->toBe($otherCell->id);
});

test('a cell status log resolves its related cell for a transfer entry', function () {
    $cell = Cell::factory()->create();
    $relatedCell = Cell::factory()->create();
    $log = CellStatusLog::factory()->create([
        'cell_id' => $cell->id,
        'related_cell_id' => $relatedCell->id,
    ]);

    expect($log->relatedCell)->not->toBeNull();
    expect($log->relatedCell->id)->toBe($relatedCell->id);
    expect($log->relatedCell->id)->not->toBe($cell->id);
});

test('a cell status log has no related cell for a plain status update', function () {
    $log = CellStatusLog::factory()->create(['related_cell_id' => null]);

    expect($log->relatedCell)->toBeNull();
});

test('a cell status log belongs to its product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $log = CellStatusLog::factory()->create(['product_id' => $product->id]);

    expect($log->product->id)->toBe($product->id);
    expect($log->product->id)->not->toBe($otherProduct->id);
});

test('a cell status log belongs to its pallet', function () {
    $pallet = Pallet::factory()->create();
    $otherPallet = Pallet::factory()->create();
    $log = CellStatusLog::factory()->create(['pallet_id' => $pallet->id]);

    expect($log->pallet->id)->toBe($pallet->id);
    expect($log->pallet->id)->not->toBe($otherPallet->id);
});

test('a cell status log keeps its pallet_id but resolves a null pallet once the pallet is deleted', function () {
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;
    $log = CellStatusLog::factory()->create(['pallet_id' => $palletId]);

    $pallet->delete();

    $fresh = $log->fresh();

    expect($fresh->pallet_id)->toBe($palletId);
    expect($fresh->pallet)->toBeNull();
});

test('a cell status log belongs to the user who performed it', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($log->user->id)->toBe($user->id);
    expect($log->user->id)->not->toBe($otherUser->id);
});

test('the action attribute is cast to a CellLogAction enum', function () {
    $log = CellStatusLog::factory()->create(['action' => CellLogAction::TransferredOut]);

    expect($log->fresh()->action)->toBe(CellLogAction::TransferredOut);
});

test('the from_state and to_state attributes are cast to CellState enums', function () {
    $log = CellStatusLog::factory()->create([
        'from_state' => CellState::Full,
        'to_state' => CellState::Empty,
    ]);

    $fresh = $log->fresh();

    expect($fresh->from_state)->toBe(CellState::Full);
    expect($fresh->to_state)->toBe(CellState::Empty);
});

test('the filtered scope combines multiple filters with AND, not OR', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $product->id, 'user_id' => $user->id]);
    CellStatusLog::factory()->create(['product_id' => $product->id, 'user_id' => $otherUser->id]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id, 'user_id' => $user->id]);

    $request = Request::create('/', 'GET', ['product_id' => $product->id, 'user_id' => $user->id]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope ignores filters that are absent from the request', function () {
    CellStatusLog::factory()->count(3)->create();

    $results = CellStatusLog::query()->filtered(Request::create('/', 'GET'))->get();

    expect($results)->toHaveCount(3);
});

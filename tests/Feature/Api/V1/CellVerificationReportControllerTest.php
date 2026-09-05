<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

function makeEmptyCell(): Cell
{
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    return $row->cells()->first();
}

test('a worker can report a cell as correct against their own round', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->create();
    $cell = makeEmptyCell();

    $response = $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => true,
    ]);

    $response->assertCreated();

    $report = CellVerificationReport::query()->sole();
    expect($report->user_id)->toBe($user->id);
    expect($report->is_correct)->toBeTrue();
    expect($report->expected_cell_state)->toBe(CellState::Empty);
    expect($report->reported_cell_state)->toBeNull();
});

test('the expected snapshot is derived server-side from the cell\'s current pallet', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->create();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $cell->update(['state' => CellState::Full]);
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create([
        'cell_id' => $cell->id,
        'product_id' => $product->id,
        'remaining_boxes' => 7,
        'expiration_date' => now()->addWeek(),
    ]);

    $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => true,
        // Attempting to smuggle a different expected snapshot — must be ignored.
        'expected_cell_state' => 'empty',
        'expected_product_id' => Product::factory()->create()->id,
    ])->assertCreated();

    $report = CellVerificationReport::query()->sole();
    expect($report->expected_cell_state)->toBe(CellState::Full);
    expect($report->expected_product_id)->toBe($product->id);
    expect($report->expected_boxes_count)->toBe(7);
    expect($report->expected_expiration_date->isSameDay($pallet->expiration_date))->toBeTrue();
});

test('reported_cell_state is required when is_correct is false', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->create();
    $cell = makeEmptyCell();

    $response = $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => false,
    ]);

    $response->assertInvalid(['reported_cell_state']);
    $this->assertDatabaseCount('cell_verification_reports', 0);
});

test('reported product and boxes count are required when the reported state is full', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->create();
    $cell = makeEmptyCell();

    $response = $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => false,
        'reported_cell_state' => 'full',
    ]);

    $response->assertInvalid(['reported_product_id', 'reported_boxes_count']);
    $this->assertDatabaseCount('cell_verification_reports', 0);
});

test('a worker cannot report against another user\'s round', function () {
    actingAsMobileUser();
    $otherUser = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->for($otherUser)->create();
    $cell = makeEmptyCell();

    $response = $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => true,
    ]);

    $response->assertInvalid(['cell_verification_round_id']);
    $this->assertDatabaseCount('cell_verification_reports', 0);
});

test('a worker cannot report against an already-completed round', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->completed()->create();
    $cell = makeEmptyCell();

    $response = $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => true,
    ]);

    $response->assertStatus(409);
    $response->assertJson(['error_code' => 'verification_round_completed']);
    $this->assertDatabaseCount('cell_verification_reports', 0);
});

<?php

use App\Enums\CellState;
use App\Exceptions\InvalidVerificationRoundScopeException;
use App\Exceptions\VerificationRoundCompletedException;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use App\Services\CellVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Called directly rather than through the API so a test can hold on to a
 * round instance across another call — the stale-read window the round lock
 * closes, which route model binding never opens on its own.
 */
function cellVerificationsUnderTest(): CellVerificationService
{
    return app(CellVerificationService::class);
}

test('a report against a round that was completed after the caller loaded it is rejected and nothing is recorded', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->sole();
    $user = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->covering($row)->create(['user_id' => $user->id]);

    $staleCopy = CellVerificationRound::query()->findOrFail($round->id);
    cellVerificationsUnderTest()->completeRound(CellVerificationRound::query()->findOrFail($round->id));

    // The caller's copy still reads as open — only the locked re-read can tell.
    expect($staleCopy->isCompleted())->toBeFalse();

    expect(fn () => cellVerificationsUnderTest()->report($staleCopy, $cell->id, $user->id, ['is_correct' => true]))
        ->toThrow(VerificationRoundCompletedException::class);

    expect(CellVerificationReport::query()->count())->toBe(0);
});

test('a report against a still-open round snapshots the reported cells own pallet', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 2]);
    [$cell, $neighbour] = $row->cells()->where('flat_number', 2)->orderBy('id')->get()->all();
    $user = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->covering($row)->create(['user_id' => $user->id]);
    $product = Product::factory()->boxesCount(10)->create();
    Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id, 'remaining_boxes' => 6, 'expiration_date' => '2027-02-01']);
    // Noise: a different pallet next door must not leak into the snapshot.
    Pallet::factory()->create(['cell_id' => $neighbour->id, 'remaining_boxes' => 1]);

    $report = cellVerificationsUnderTest()->report($round, $cell->id, $user->id, ['is_correct' => true]);

    expect($report->expected_cell_state)->toBe(CellState::Full)
        ->and($report->expected_product_id)->toBe($product->id)
        ->and($report->expected_boxes_count)->toBe(6)
        ->and($report->expected_expiration_date->toDateString())->toBe('2027-02-01');
});

test('completing a round that another request already completed keeps the first completion time', function () {
    $round = CellVerificationRound::factory()->create();
    $staleCopy = CellVerificationRound::query()->findOrFail($round->id);

    $this->travelTo('2026-09-01 10:00:00');
    cellVerificationsUnderTest()->completeRound(CellVerificationRound::query()->findOrFail($round->id));

    $this->travelTo('2026-09-01 11:00:00');
    $returned = cellVerificationsUnderTest()->completeRound($staleCopy);

    expect($returned->completed_at->toDateTimeString())->toBe('2026-09-01 10:00:00')
        ->and($round->fresh()->completed_at->toDateTimeString())->toBe('2026-09-01 10:00:00');
});

test('starting a round over an empty list of rows is rejected and claims nothing', function () {
    $noiseRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $user = User::factory()->mobileUser()->create();

    expect(fn () => cellVerificationsUnderTest()->startRound($user->id, []))
        ->toThrow(InvalidVerificationRoundScopeException::class, __('messages.verification_round_scope_empty'));

    expect(CellVerificationRound::query()->count())->toBe(0)
        ->and(DB::table('cell_verification_round_row')->count())->toBe(0)
        ->and(Row::query()->whereKey($noiseRow->id)->underActiveVerification()->exists())->toBeFalse();
});

test('starting a warehouse-wide round when there are no rows at all is rejected', function () {
    $user = User::factory()->mobileUser()->create();

    expect(fn () => cellVerificationsUnderTest()->startRound($user->id, null))
        ->toThrow(InvalidVerificationRoundScopeException::class, __('messages.verification_round_scope_empty'));

    expect(CellVerificationRound::query()->count())->toBe(0);
});

test('starting a round that names a row which no longer exists is rejected and claims none of the rows that do', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $missingRowId = Row::query()->max('id') + 1000;
    $user = User::factory()->mobileUser()->create();

    expect(fn () => cellVerificationsUnderTest()->startRound($user->id, [$row->id, $missingRowId]))
        ->toThrow(InvalidVerificationRoundScopeException::class, __('messages.verification_round_rows_not_found'));

    expect(CellVerificationRound::query()->count())->toBe(0)
        ->and(DB::table('cell_verification_round_row')->count())->toBe(0)
        ->and(Row::query()->whereKey($row->id)->underActiveVerification()->exists())->toBeFalse();
});

test('naming the same row twice starts a round that claims it once', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $noiseRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $user = User::factory()->mobileUser()->create();

    $round = cellVerificationsUnderTest()->startRound($user->id, [$row->id, $row->id]);

    expect($round->rows()->pluck('rows.id')->all())->toBe([$row->id])
        ->and(Row::query()->whereKey($noiseRow->id)->underActiveVerification()->exists())->toBeFalse();
});

test('a round scope error renders as a 422 carrying its error code', function () {
    $response = (new InvalidVerificationRoundScopeException('verification_round_scope_empty', __('messages.verification_round_scope_empty')))
        ->render(Request::create('/api/v1/cell-verification-rounds', 'POST'));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'message' => __('messages.verification_round_scope_empty'),
            'error_code' => 'verification_round_scope_empty',
        ]);
});

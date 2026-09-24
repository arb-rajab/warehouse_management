<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

test('a verification report belongs to its round', function () {
    $round = CellVerificationRound::factory()->create();
    $otherRound = CellVerificationRound::factory()->create();
    $report = CellVerificationReport::factory()->create(['cell_verification_round_id' => $round->id]);

    expect($report->round->id)->toBe($round->id);
    expect($report->round->id)->not->toBe($otherRound->id);
});

test('a verification report belongs to its cell', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $report = CellVerificationReport::factory()->create(['cell_id' => $cell->id]);

    expect($report->cell->id)->toBe($cell->id);
    expect($report->cell->id)->not->toBe($otherCell->id);
});

test('a verification report belongs to the user who reported it', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = CellVerificationReport::factory()->create(['user_id' => $user->id]);

    expect($report->user->id)->toBe($user->id);
    expect($report->user->id)->not->toBe($otherUser->id);
});

test('a verification report belongs to its expected product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $report = CellVerificationReport::factory()->create(['expected_product_id' => $product->id]);

    expect($report->expectedProduct->id)->toBe($product->id);
    expect($report->expectedProduct->id)->not->toBe($otherProduct->id);
});

test('a verification report has no expected product when none was expected', function () {
    $report = CellVerificationReport::factory()->create(['expected_product_id' => null]);

    expect($report->expectedProduct)->toBeNull();
});

test('a verification report belongs to its reported product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $report = CellVerificationReport::factory()->create(['reported_product_id' => $product->id]);

    expect($report->reportedProduct->id)->toBe($product->id);
    expect($report->reportedProduct->id)->not->toBe($otherProduct->id);
});

test('a verification report has no reported product when none was reported', function () {
    $report = CellVerificationReport::factory()->create(['reported_product_id' => null]);

    expect($report->reportedProduct)->toBeNull();
});

test('the is_correct attribute is cast to a boolean', function () {
    $report = CellVerificationReport::factory()->create(['is_correct' => 1]);

    expect($report->fresh()->is_correct)->toBeTrue();
});

test('the expected_cell_state and reported_cell_state attributes are cast to CellState enums', function () {
    $report = CellVerificationReport::factory()->create([
        'expected_cell_state' => CellState::Full,
        'reported_cell_state' => CellState::Empty,
    ]);

    $fresh = $report->fresh();

    expect($fresh->expected_cell_state)->toBe(CellState::Full);
    expect($fresh->reported_cell_state)->toBe(CellState::Empty);
});

test('reported_cell_state is null when nothing was reported', function () {
    $report = CellVerificationReport::factory()->create(['reported_cell_state' => null]);

    expect($report->fresh()->reported_cell_state)->toBeNull();
});

test('the expected_expiration_date and reported_expiration_date attributes are cast to dates', function () {
    $report = CellVerificationReport::factory()->create([
        'expected_expiration_date' => '2026-08-01',
        'reported_expiration_date' => '2026-09-01',
    ]);

    $fresh = $report->fresh();

    expect($fresh->expected_expiration_date)->toBeInstanceOf(CarbonImmutable::class);
    expect($fresh->reported_expiration_date)->toBeInstanceOf(CarbonImmutable::class);
});

test('the filtered scope can filter by cell_id, excluding another cells report', function () {
    $cell = Cell::factory()->create();
    $matching = CellVerificationReport::factory()->create(['cell_id' => $cell->id]);
    CellVerificationReport::factory()->create(); // noise: another cell

    $request = Request::create('/', 'GET', ['cell_id' => $cell->id]);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope can filter by is_correct true, excluding incorrect reports', function () {
    $correct = CellVerificationReport::factory()->create(['is_correct' => true]);
    CellVerificationReport::factory()->incorrect()->create(); // noise

    $request = Request::create('/', 'GET', ['is_correct' => '1']);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($correct->id);
});

test('the filtered scope can filter by is_correct false, excluding correct reports', function () {
    $incorrect = CellVerificationReport::factory()->incorrect()->create();
    CellVerificationReport::factory()->create(['is_correct' => true]); // noise

    $request = Request::create('/', 'GET', ['is_correct' => '0']);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($incorrect->id);
});

test('the filtered scope can filter by product_id, matching either the expected or reported product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $matchesExpected = CellVerificationReport::factory()->create(['expected_product_id' => $product->id, 'reported_product_id' => null]);
    $matchesReported = CellVerificationReport::factory()->create(['expected_product_id' => null, 'reported_product_id' => $product->id]);
    CellVerificationReport::factory()->create(['expected_product_id' => $otherProduct->id, 'reported_product_id' => $otherProduct->id]); // noise

    $request = Request::create('/', 'GET', ['product_id' => [$product->id]]);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results->pluck('id')->sort()->values()->all())->toEqual(
        collect([$matchesExpected->id, $matchesReported->id])->sort()->values()->all()
    );
});

test('the filtered scope can filter by date range, excluding reports outside it', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $matching = backdate(CellVerificationReport::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellVerificationReport::factory()->create(), '2026-07-01 00:00:00');

    $request = Request::create('/', 'GET', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);

    Carbon::setTestNow();
});

test('the filtered scope can filter by created_within_days, excluding reports older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $withinWindow = backdate(CellVerificationReport::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellVerificationReport::factory()->create(), '2026-08-01 00:00:00');

    $request = Request::create('/', 'GET', ['created_within_days' => 7]);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($withinWindow->id);

    Carbon::setTestNow();
});

test('the filtered scope can filter by row and column, excluding reports on another cell', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $matchingCell = $row->cells()->where('cell_number', 1)->first();

    $matching = CellVerificationReport::factory()->create(['cell_id' => $matchingCell->id]);
    CellVerificationReport::factory()->create(['cell_id' => $row->cells()->where('cell_number', 2)->first()->id]); // noise: same row, other column
    CellVerificationReport::factory()->create(['cell_id' => $otherRow->cells()->first()->id]); // noise: other row

    $request = Request::create('/', 'GET', ['row_id' => [$row->id], 'column_number' => 1]);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope can filter by multiple rows, excluding reports on a remaining row', function () {
    $rowA = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $matchingA = CellVerificationReport::factory()->create(['cell_id' => $rowA->cells()->first()->id]);
    $matchingB = CellVerificationReport::factory()->create(['cell_id' => $rowB->cells()->first()->id]);
    CellVerificationReport::factory()->create(['cell_id' => $otherRow->cells()->first()->id]); // noise: other row

    $request = Request::create('/', 'GET', ['row_id' => [$rowA->id, $rowB->id]]);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results->pluck('id')->sort()->values()->all())->toEqual(
        collect([$matchingA->id, $matchingB->id])->sort()->values()->all()
    );
});

test('the filtered scope combines multiple filters with AND, not OR', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();

    $matching = CellVerificationReport::factory()->create(['cell_id' => $cell->id, 'is_correct' => true]);
    CellVerificationReport::factory()->create(['cell_id' => $cell->id, 'is_correct' => false]);
    CellVerificationReport::factory()->create(['cell_id' => $otherCell->id, 'is_correct' => true]);

    $request = Request::create('/', 'GET', ['cell_id' => $cell->id, 'is_correct' => '1']);

    $results = CellVerificationReport::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope ignores filters that are absent from the request', function () {
    CellVerificationReport::factory()->count(3)->create();

    $results = CellVerificationReport::query()->filtered(Request::create('/', 'GET'))->get();

    expect($results)->toHaveCount(3);
});

test('the sorted scope defaults to created_at descending when no sort params are given', function () {
    // Created in the reverse order of their timestamps (lower id gets the later
    // timestamp), so the assertion only passes if the scope truly orders by
    // created_at and not by insertion/id order.
    $newer = backdate(CellVerificationReport::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellVerificationReport::factory()->create(), '2026-08-01 10:00:00');

    $results = CellVerificationReport::query()->sorted(Request::create('/', 'GET'))->get();

    expect($results->pluck('id')->all())->toEqual([$newer->id, $older->id]);
});

test('the sorted scope can sort by created_at ascending', function () {
    $newer = backdate(CellVerificationReport::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellVerificationReport::factory()->create(), '2026-08-01 10:00:00');

    $request = Request::create('/', 'GET', ['sort_direction' => 'asc']);

    $results = CellVerificationReport::query()->sorted($request)->get();

    expect($results->pluck('id')->all())->toEqual([$older->id, $newer->id]);
});

test('the sorted scope breaks ties on id, in the requested direction, when created_at values are equal', function () {
    $first = backdate(CellVerificationReport::factory()->create(), '2026-08-01 10:00:00');
    $second = backdate(CellVerificationReport::factory()->create(), '2026-08-01 10:00:00');

    $desc = CellVerificationReport::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'desc']))->get();
    expect($desc->pluck('id')->all())->toEqual([$second->id, $first->id]);

    $asc = CellVerificationReport::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'asc']))->get();
    expect($asc->pluck('id')->all())->toEqual([$first->id, $second->id]);
});

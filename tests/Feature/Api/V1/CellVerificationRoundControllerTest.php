<?php

use App\Enums\CellState;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

test('an authenticated worker can start a verification round over the rows they name', function () {
    $user = actingAsMobileUser();

    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]); // noise: not requested

    $response = $this->postJson('/api/v1/cell-verification-rounds', [
        'row_ids' => [$rowB->id, $rowA->id],
    ]);

    $response->assertCreated();

    $round = CellVerificationRound::query()->sole();

    expect($round->user_id)->toBe($user->id);
    expect($round->completed_at)->toBeNull();

    // reports_count is omitted (not just null) since store() doesn't withCount() —
    // see CellVerificationRoundResource::reports_count / RowResource.has_pallets for the pattern.
    // `rows` comes back ordered by letter whatever order they were requested in.
    expect($response->json())->toEqual([
        'id' => $round->id,
        'started_at' => $round->created_at->toIso8601String(),
        'completed_at' => null,
        'rows' => [
            ['id' => $rowA->id, 'letter' => 'A'],
            ['id' => $rowB->id, 'letter' => 'B'],
        ],
    ]);
});

test('a round started without row_ids covers every row in the warehouse', function () {
    actingAsMobileUser();

    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $rowC = Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->postJson('/api/v1/cell-verification-rounds');

    $response->assertCreated();
    expect($response->json('rows'))->toEqual([
        ['id' => $rowA->id, 'letter' => 'A'],
        ['id' => $rowB->id, 'letter' => 'B'],
        ['id' => $rowC->id, 'letter' => 'C'],
    ]);
});

test('a warehouse-wide round claims every row, leaving none for a second round', function () {
    actingAsMobileUser();

    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    $this->postJson('/api/v1/cell-verification-rounds')->assertCreated();

    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [$rowB->id]])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'rows_already_in_active_round');

    $this->assertDatabaseCount('cell_verification_rounds', 1);
});

test('a warehouse-wide round is refused while any single row is already claimed', function () {
    actingAsMobileUser();

    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $claimed = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    CellVerificationRound::factory()->covering($claimed)->create();

    $response = $this->postJson('/api/v1/cell-verification-rounds');

    $response->assertStatus(409);
    expect($response->json('message'))->toContain('B');
    $this->assertDatabaseCount('cell_verification_rounds', 1);
});

test('starting a round rejects an empty row_ids array and a row that does not exist', function () {
    actingAsMobileUser();

    // An empty array is rejected rather than read as "the whole warehouse",
    // which omitting the field entirely means instead.
    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => []])
        ->assertJsonValidationErrors('row_ids');

    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [999999]])
        ->assertJsonValidationErrors('row_ids.0');

    $this->assertDatabaseCount('cell_verification_rounds', 0);
});

test('starting a round is refused when an unfinished round already covers one of its rows', function () {
    $user = actingAsMobileUser();

    $shared = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $free = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    // Another worker's round entirely: a row claim is global, not per-user.
    $otherWorker = User::factory()->mobileUser()->create();
    CellVerificationRound::factory()->for($otherWorker)->covering($shared)->create();

    $response = $this->postJson('/api/v1/cell-verification-rounds', [
        'row_ids' => [$shared->id, $free->id],
    ]);

    $response->assertStatus(409);
    expect($response->json('error_code'))->toBe('rows_already_in_active_round');
    expect($response->json('message'))->toContain('A');

    // The whole request is refused, not just the conflicting row — no second
    // round exists and the free row was never claimed.
    expect(CellVerificationRound::query()->where('user_id', $user->id)->count())->toBe(0);
    $this->assertDatabaseCount('cell_verification_round_row', 1);
});

test('starting a round is allowed alongside an unfinished round covering different rows', function () {
    actingAsMobileUser();

    $claimed = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $free = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    CellVerificationRound::factory()->covering($claimed)->create();

    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [$free->id]])
        ->assertCreated();

    $this->assertDatabaseCount('cell_verification_rounds', 2);
});

test('a completed round no longer blocks starting a new round over its rows', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    CellVerificationRound::factory()->completed()->covering($row)->create();

    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [$row->id]])
        ->assertCreated();
});

test('an unauthenticated caller cannot start a verification round and nothing changes', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [$row->id]]);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('cell_verification_rounds', 0);
});

test('a worker can list only their own verification rounds, most recent first', function () {
    $user = actingAsMobileUser();

    $mine = CellVerificationRound::factory()->for($user)->create();
    CellVerificationRound::factory()->create(); // noise: another user's round

    $response = $this->getJson('/api/v1/cell-verification-rounds');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($mine->id);
});

test('only_unfinished filters out completed rounds', function () {
    $user = actingAsMobileUser();

    $unfinished = CellVerificationRound::factory()->for($user)->create();
    CellVerificationRound::factory()->for($user)->completed()->create();

    $response = $this->getJson('/api/v1/cell-verification-rounds?only_unfinished=true');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($unfinished->id);
});

test('the round listing paginates beyond one page', function () {
    $user = actingAsMobileUser();

    CellVerificationRound::factory()->for($user)->count(25)->create();

    $response = $this->getJson('/api/v1/cell-verification-rounds');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('an unauthenticated caller cannot list verification rounds', function () {
    CellVerificationRound::factory()->create();

    $response = $this->getJson('/api/v1/cell-verification-rounds');

    $response->assertUnauthorized();
});

test('a worker can view one of their own rounds, including its reports in reported order', function () {
    $user = actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'D', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $round = CellVerificationRound::factory()->for($user)->completed()->covering($row)->create();
    $product = Product::factory()->imageUrl(null)->boxesCount(4)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $reporter = User::factory()->mobileUser()->create(['name' => 'Ada Reporter']);

    $first = backdate(CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'user_id' => $reporter->id,
        'is_correct' => true,
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => null,
        'note' => null,
    ]), '2026-08-01 10:00:00');

    $second = backdate(CellVerificationReport::factory()->incorrect()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'user_id' => $reporter->id,
        'expected_product_id' => $product->id,
        'expected_boxes_count' => 4,
        'expected_expiration_date' => '2026-12-01',
        'note' => 'Wrong product on this pallet.',
    ]), '2026-08-01 11:00:00');

    // Noise: a report on another round entirely. It reuses this row's cell
    // rather than letting the factory chain create a row of its own — this
    // test hardcodes letter 'D' because its assertions read it, and a
    // generated letter can collide with a hardcoded one (see tests.md,
    // "Don't hardcode a unique column's value beside a factory that generates
    // its own"); it did, as an intermittent rows.letter UNIQUE violation in CI.
    CellVerificationReport::factory()->create(['cell_id' => $cell->id]);

    $response = $this->getJson("/api/v1/cell-verification-rounds/{$round->id}");

    $response->assertOk();

    expect($response->json())->toEqual([
        'id' => $round->id,
        'started_at' => $round->created_at->toIso8601String(),
        'completed_at' => $round->completed_at->toIso8601String(),
        'reports_count' => 2,
        'rows' => [
            ['id' => $row->id, 'letter' => 'D'],
        ],
        'reports' => [
            [
                'id' => $first->id,
                'cell_verification_round_id' => $round->id,
                'is_correct' => true,
                'cell' => [
                    'row_letter' => 'D',
                    'cell_number' => 1,
                    'flat_number' => 1,
                ],
                'expected' => [
                    'cell_state' => 'empty',
                    'product' => null,
                    'boxes_count' => null,
                    'expiration_date' => null,
                ],
                'reported' => [
                    'cell_state' => null,
                    'product' => null,
                    'boxes_count' => null,
                    'expiration_date' => null,
                ],
                'note' => null,
                'user' => [
                    'id' => $reporter->id,
                    'name' => 'Ada Reporter',
                ],
                'created_at' => $first->created_at->toIso8601String(),
            ],
            [
                'id' => $second->id,
                'cell_verification_round_id' => $round->id,
                'is_correct' => false,
                'cell' => [
                    'row_letter' => 'D',
                    'cell_number' => 1,
                    'flat_number' => 1,
                ],
                'expected' => [
                    'cell_state' => 'full',
                    'product' => [
                        'id' => $product->id,
                        'name' => 'Widgets',
                        'ar_name' => 'ودجات',
                        'image_url' => null,
                        'boxes_count' => 4,
                        'active' => true,
                    ],
                    'boxes_count' => 4,
                    'expiration_date' => '2026-12-01',
                ],
                'reported' => [
                    'cell_state' => 'empty',
                    'product' => null,
                    'boxes_count' => null,
                    'expiration_date' => null,
                ],
                'note' => 'Wrong product on this pallet.',
                'user' => [
                    'id' => $reporter->id,
                    'name' => 'Ada Reporter',
                ],
                'created_at' => $second->created_at->toIso8601String(),
            ],
        ],
    ]);
});

test('a worker cannot view another user\'s round', function () {
    actingAsMobileUser();
    $otherUser = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->for($otherUser)->create();

    $response = $this->getJson("/api/v1/cell-verification-rounds/{$round->id}");

    $response->assertForbidden();
});

test('viewing a non-existent round returns a 404', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-verification-rounds/999999');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot view a round', function () {
    $round = CellVerificationRound::factory()->create();

    $response = $this->getJson("/api/v1/cell-verification-rounds/{$round->id}");

    $response->assertUnauthorized();
});

test('a worker can complete their own round', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->create();

    $response = $this->postJson("/api/v1/cell-verification-rounds/{$round->id}/complete");

    $response->assertOk();
    expect($round->refresh()->completed_at)->not->toBeNull();
});

test('completing an already-completed round is idempotent', function () {
    $user = actingAsMobileUser();
    $round = CellVerificationRound::factory()->for($user)->completed()->create();
    $originalCompletedAt = $round->completed_at;

    $response = $this->postJson("/api/v1/cell-verification-rounds/{$round->id}/complete");

    $response->assertOk();
    expect($round->refresh()->completed_at->eq($originalCompletedAt))->toBeTrue();
});

test('a worker cannot complete another user\'s round', function () {
    actingAsMobileUser();
    $otherUser = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->for($otherUser)->create();

    $response = $this->postJson("/api/v1/cell-verification-rounds/{$round->id}/complete");

    $response->assertForbidden();
    expect($round->refresh()->completed_at)->toBeNull();
});

test('an unauthenticated caller cannot complete a round and nothing changes', function () {
    $round = CellVerificationRound::factory()->create();

    $response = $this->postJson("/api/v1/cell-verification-rounds/{$round->id}/complete");

    $response->assertUnauthorized();
    expect($round->refresh()->completed_at)->toBeNull();
});

test('completing a non-existent round returns a 404', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/cell-verification-rounds/999999/complete');

    $response->assertNotFound();
});

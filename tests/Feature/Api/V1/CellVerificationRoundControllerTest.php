<?php

use App\Enums\CellState;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

test('an authenticated worker can start a verification round', function () {
    $user = actingAsMobileUser();

    $response = $this->postJson('/api/v1/cell-verification-rounds');

    $response->assertCreated();

    $round = CellVerificationRound::query()->sole();

    expect($round->user_id)->toBe($user->id);
    expect($round->completed_at)->toBeNull();

    // reports_count is omitted (not just null) since store() doesn't withCount() —
    // see CellVerificationRoundResource::reports_count / RowResource.has_pallets for the pattern.
    expect($response->json())->toEqual([
        'id' => $round->id,
        'started_at' => $round->created_at->toIso8601String(),
        'completed_at' => null,
    ]);
});

test('an unauthenticated caller cannot start a verification round and nothing changes', function () {
    $response = $this->postJson('/api/v1/cell-verification-rounds');

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
    $round = CellVerificationRound::factory()->for($user)->completed()->create();

    $row = Row::factory()->create(['letter' => 'D', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
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

    CellVerificationReport::factory()->create(); // noise: another round entirely

    $response = $this->getJson("/api/v1/cell-verification-rounds/{$round->id}");

    $response->assertOk();

    expect($response->json())->toEqual([
        'id' => $round->id,
        'started_at' => $round->created_at->toIso8601String(),
        'completed_at' => $round->completed_at->toIso8601String(),
        'reports_count' => 2,
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

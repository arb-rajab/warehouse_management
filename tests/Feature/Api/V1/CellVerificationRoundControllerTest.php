<?php

use App\Models\CellVerificationRound;
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

test('completing a non-existent round returns a 404', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/cell-verification-rounds/999999/complete');

    $response->assertNotFound();
});

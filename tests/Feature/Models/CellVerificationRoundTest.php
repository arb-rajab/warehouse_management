<?php

use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\User;
use Carbon\CarbonImmutable;

test('a verification round belongs to its user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $round = CellVerificationRound::factory()->create(['user_id' => $user->id]);

    expect($round->user->id)->toBe($user->id);
    expect($round->user->id)->not->toBe($otherUser->id);
});

test('a verification round has many reports, excluding another rounds reports', function () {
    $round = CellVerificationRound::factory()->create();
    $report = CellVerificationReport::factory()->create(['cell_verification_round_id' => $round->id]);

    $otherRound = CellVerificationRound::factory()->create();
    CellVerificationReport::factory()->create(['cell_verification_round_id' => $otherRound->id]);

    expect($round->reports)->toHaveCount(1);
    expect($round->reports->first()->id)->toBe($report->id);
});

test('the completed_at attribute is cast to a datetime', function () {
    $round = CellVerificationRound::factory()->create(['completed_at' => '2026-08-01 10:00:00']);

    expect($round->fresh()->completed_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('isCompleted is false when completed_at is null', function () {
    $round = CellVerificationRound::factory()->create(['completed_at' => null]);

    expect($round->isCompleted())->toBeFalse();
});

test('isCompleted is true when completed_at is set', function () {
    $round = CellVerificationRound::factory()->completed()->create();

    expect($round->isCompleted())->toBeTrue();
});

test('the ownedBy scope excludes another users rounds', function () {
    $user = User::factory()->create();
    $mine = CellVerificationRound::factory()->create(['user_id' => $user->id]);
    CellVerificationRound::factory()->create(); // noise: another user's round

    $results = CellVerificationRound::query()->ownedBy($user->id)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($mine->id);
});

test('the unfinished scope excludes completed rounds', function () {
    $unfinished = CellVerificationRound::factory()->create();
    CellVerificationRound::factory()->completed()->create();

    $results = CellVerificationRound::query()->unfinished()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($unfinished->id);
});

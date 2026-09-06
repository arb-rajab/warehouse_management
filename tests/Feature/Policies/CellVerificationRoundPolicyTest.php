<?php

use App\Models\CellVerificationRound;
use App\Models\User;
use App\Policies\CellVerificationRoundPolicy;

test('view is true for the rounds own user', function () {
    $user = User::factory()->create();
    $round = CellVerificationRound::factory()->create(['user_id' => $user->id]);

    expect((new CellVerificationRoundPolicy)->view($user, $round))->toBeTrue();
});

test('view is false for another user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $round = CellVerificationRound::factory()->create(['user_id' => $owner->id]);

    expect((new CellVerificationRoundPolicy)->view($otherUser, $round))->toBeFalse();
});

test('update mirrors view: true for the rounds own user, false for another', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $round = CellVerificationRound::factory()->create(['user_id' => $owner->id]);

    $policy = new CellVerificationRoundPolicy;

    expect($policy->update($owner, $round))->toBeTrue();
    expect($policy->update($otherUser, $round))->toBeFalse();
});

test('an admin gets no special access to another users round', function () {
    $owner = User::factory()->mobileUser()->create();
    $admin = User::factory()->create();
    $round = CellVerificationRound::factory()->create(['user_id' => $owner->id]);

    $policy = new CellVerificationRoundPolicy;

    // Rounds are ownership-scoped on the mobile API only; admins read every
    // round through the role:admin web routes, which never consult this policy.
    expect($policy->view($admin, $round))->toBeFalse();
    expect($policy->update($admin, $round))->toBeFalse();
});

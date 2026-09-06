<?php

use App\Models\CellVerificationRound;
use App\Models\User;
use Illuminate\Database\QueryException;

test('deleting a user referenced by a verification round is restricted', function () {
    $round = CellVerificationRound::factory()->create();
    $user = $round->user;

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

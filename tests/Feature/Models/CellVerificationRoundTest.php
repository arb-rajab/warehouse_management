<?php

use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Row;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

test('a verification round has many rows, excluding another rounds rows', function () {
    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]);

    $round = CellVerificationRound::factory()->covering($rowB, $rowA)->create();
    CellVerificationRound::factory()->covering($otherRow)->create(); // noise

    // Attached B first, but the relation orders by letter for every consumer.
    expect($round->rows->pluck('letter')->all())->toBe(['A', 'B']);
});

test('coversRow is true for a claimed row and false for one claimed by another round', function () {
    $mine = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $theirs = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    $round = CellVerificationRound::factory()->covering($mine)->create();
    CellVerificationRound::factory()->covering($theirs)->create(); // noise

    expect($round->coversRow($mine->id))->toBeTrue();
    expect($round->coversRow($theirs->id))->toBeFalse();
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

test('the filtered scope can filter by multiple user ids at once, excluding the remaining user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $matching = CellVerificationRound::factory()->create(['user_id' => $user->id]);
    CellVerificationRound::factory()->create(['user_id' => $otherUser->id]); // noise

    $request = Request::create('/', 'GET', ['user_id' => [$user->id]]);

    $results = CellVerificationRound::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope can filter by completed true, excluding unfinished rounds', function () {
    $completed = CellVerificationRound::factory()->completed()->create();
    CellVerificationRound::factory()->create(); // noise: unfinished

    $request = Request::create('/', 'GET', ['completed' => '1']);

    $results = CellVerificationRound::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($completed->id);
});

test('the filtered scope can filter by completed false, excluding finished rounds', function () {
    $unfinished = CellVerificationRound::factory()->create();
    CellVerificationRound::factory()->completed()->create(); // noise

    $request = Request::create('/', 'GET', ['completed' => '0']);

    $results = CellVerificationRound::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($unfinished->id);
});

test('the filtered scope can filter by date range, excluding rounds outside it', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $matching = backdate(CellVerificationRound::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellVerificationRound::factory()->create(), '2026-07-01 00:00:00');

    $request = Request::create('/', 'GET', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']);

    $results = CellVerificationRound::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);

    Carbon::setTestNow();
});

test('the filtered scope can filter by created_within_days, excluding rounds older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $withinWindow = backdate(CellVerificationRound::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellVerificationRound::factory()->create(), '2026-08-01 00:00:00');

    $request = Request::create('/', 'GET', ['created_within_days' => 7]);

    $results = CellVerificationRound::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($withinWindow->id);

    Carbon::setTestNow();
});

test('the filtered scope ignores filters that are absent from the request', function () {
    CellVerificationRound::factory()->count(3)->create();

    $results = CellVerificationRound::query()->filtered(Request::create('/', 'GET'))->get();

    expect($results)->toHaveCount(3);
});

test('the sorted scope defaults to created_at descending when no sort params are given', function () {
    // Created in the reverse order of their timestamps (lower id gets the later
    // timestamp), so the assertion only passes if the scope truly orders by
    // created_at and not by insertion/id order.
    $newer = backdate(CellVerificationRound::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellVerificationRound::factory()->create(), '2026-08-01 10:00:00');

    $results = CellVerificationRound::query()->sorted(Request::create('/', 'GET'))->get();

    expect($results->pluck('id')->all())->toEqual([$newer->id, $older->id]);
});

test('the sorted scope can sort by created_at ascending', function () {
    $newer = backdate(CellVerificationRound::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellVerificationRound::factory()->create(), '2026-08-01 10:00:00');

    $request = Request::create('/', 'GET', ['sort_direction' => 'asc']);

    $results = CellVerificationRound::query()->sorted($request)->get();

    expect($results->pluck('id')->all())->toEqual([$older->id, $newer->id]);
});

test('the sorted scope breaks ties on id, in the requested direction, when created_at values are equal', function () {
    $first = backdate(CellVerificationRound::factory()->create(), '2026-08-01 10:00:00');
    $second = backdate(CellVerificationRound::factory()->create(), '2026-08-01 10:00:00');

    $desc = CellVerificationRound::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'desc']))->get();
    expect($desc->pluck('id')->all())->toEqual([$second->id, $first->id]);

    $asc = CellVerificationRound::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'asc']))->get();
    expect($asc->pluck('id')->all())->toEqual([$first->id, $second->id]);
});

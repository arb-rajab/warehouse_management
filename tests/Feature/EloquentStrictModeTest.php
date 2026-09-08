<?php

use App\Models\Row;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;

/**
 * AppServiceProvider enables three Eloquent guards outside production, so the
 * conventions controllers.md states in prose — eager-load your relations, select
 * only the columns you need — fail the suite instead of only review.
 *
 * The flags are asserted directly as well as behaviourally, so a change to what
 * shouldBeStrict() bundles cannot quietly drop one.
 */
test('each guard is active outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
    expect(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
    expect(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

test('lazy loading a relation throws instead of silently running a query per model', function () {
    Row::factory()->count(2)->create(['cells_count' => 1, 'flats_count' => 1]);

    // Must go through a multi-row result set. Builder::hydrate() only arms the
    // guard when the query returned more than one row, since lazy loading a
    // single model is one extra query rather than an N+1 — find()/first() will
    // never throw no matter how strict the global flag is.
    $rows = Row::query()->get();

    expect(fn () => $rows->first()->cells)->toThrow(LazyLoadingViolationException::class);
});

test('an eager-loaded relation is still readable', function () {
    Row::factory()->count(2)->create(['cells_count' => 1, 'flats_count' => 1]);

    $rows = Row::query()->with('cells')->get();

    expect($rows->first()->cells)->toHaveCount(1);
});

test('reading an attribute a narrow select left out throws instead of returning null', function () {
    $row = Row::factory()->create(['letter' => 'Q']);

    $partial = Row::query()->select(['id'])->findOrFail($row->id);

    expect(fn () => $partial->letter)->toThrow(MissingAttributeException::class);
});

test('filling an attribute the model does not declare fillable throws instead of dropping it', function () {
    expect(fn () => new Row(['letter' => 'Q', 'not_a_real_column' => 1]))
        ->toThrow(MassAssignmentException::class);
});

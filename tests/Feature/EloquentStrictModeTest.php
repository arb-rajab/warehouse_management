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
 * The flags are asserted directly as well as behaviourally: the two must agree,
 * and when they didn't, that was the bug. Model::shouldBeStrict() also turns on
 * automaticallyEagerLoadRelationships(), which resolves an unloaded relation
 * instead of raising the violation, so the lazy-loading guard silently never
 * fired. Hence the explicit per-guard calls in AppServiceProvider, and hence
 * this test asserting each flag rather than trusting a bundle.
 */
test('each guard is active outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
    expect(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
    expect(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

test('lazy loading a relation throws instead of silently running a query per model', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    // Re-fetch: Eloquent deliberately allows lazy loading on a model it just
    // created (wasRecentlyCreated), so the factory's own instance won't throw.
    $fetched = Row::query()->findOrFail($row->id);

    expect(fn () => $fetched->cells)->toThrow(LazyLoadingViolationException::class);
});

test('an eager-loaded relation is still readable', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $fetched = Row::query()->with('cells')->findOrFail($row->id);

    expect($fetched->cells)->toHaveCount(1);
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

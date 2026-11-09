<?php

use App\Models\Row;
use App\Models\Warehouse;

test('a warehouse has many rows, and only its own', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $row = Row::factory()->for($warehouse)->create(['cells_count' => 1, 'flats_count' => 1]);
    $noise = Row::factory()->for($other)->create(['cells_count' => 1, 'flats_count' => 1]);

    expect($warehouse->rows->pluck('id')->all())->toBe([$row->id])
        ->and($other->rows->pluck('id')->all())->toBe([$noise->id]);
});

test('hasRows is true only while a row belongs to the warehouse', function () {
    $empty = Warehouse::factory()->create();
    $used = Warehouse::factory()->create();
    Row::factory()->for($used)->create(['cells_count' => 1, 'flats_count' => 1]);

    expect($empty->hasRows())->toBeFalse()
        ->and($used->hasRows())->toBeTrue();
});

test('firstOrCreateDefault returns the existing Default warehouse and creates it when missing', function () {
    $existing = Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->sole();

    expect(Warehouse::firstOrCreateDefault()->id)->toBe($existing->id);

    $existing->delete();
    $created = Warehouse::firstOrCreateDefault();

    expect($created->id)->not->toBe($existing->id)
        ->and($created->name)->toBe(Warehouse::DEFAULT_NAME)
        ->and(Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->count())->toBe(1);
});

test('selectedOptions hydrates only the given warehouses, as id/name pairs ordered by name', function () {
    $zuid = Warehouse::factory()->create(['name' => 'Zuid', 'code' => 'ZD']);
    $noord = Warehouse::factory()->create(['name' => 'Noord']);
    Warehouse::factory()->create(['name' => 'Unselected']);

    expect(Warehouse::selectedOptions([$zuid->id, $noord->id])->all())->toBe([
        ['id' => $noord->id, 'name' => 'Noord'],
        ['id' => $zuid->id, 'name' => 'Zuid'],
    ]);
});

test('selectedOptions is empty without running a query when nothing is selected', function () {
    Warehouse::factory()->create();

    expect(Warehouse::selectedOptions()->all())->toBe([])
        ->and(Warehouse::selectedOptions([])->all())->toBe([]);
});

test('searchByName matches the name or the code and treats a blank term as no filter', function () {
    $byName = Warehouse::factory()->create(['name' => 'Rotterdam Port', 'code' => 'RTM']);
    $byCode = Warehouse::factory()->create(['name' => 'Annex', 'code' => 'PORT-2']);
    $noise = Warehouse::factory()->create(['name' => 'Utrecht', 'code' => 'UTR']);

    expect(Warehouse::query()->searchByName('port')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$byName->id, $byCode->id])->sort()->values()->all());
    expect(Warehouse::query()->searchByName('  ')->pluck('id'))->toContain($noise->id);
});

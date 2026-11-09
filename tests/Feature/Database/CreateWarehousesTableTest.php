<?php

use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('the warehouses table has a name, an optional code and address, and timestamps', function () {
    expect(Schema::hasColumns('warehouses', ['id', 'name', 'code', 'address', 'created_at', 'updated_at']))->toBeTrue();

    $columns = collect(Schema::getColumns('warehouses'))->keyBy('name');

    expect($columns['name']['nullable'])->toBeFalse()
        ->and($columns['code']['nullable'])->toBeTrue()
        ->and($columns['address']['nullable'])->toBeTrue();
});

test('two warehouses cannot share a name', function () {
    Warehouse::factory()->create(['name' => 'North']);

    expect(fn () => Warehouse::factory()->create(['name' => 'North']))->toThrow(QueryException::class);
    expect(Warehouse::query()->where('name', 'North')->count())->toBe(1);
});

test('two warehouses cannot share a code, while any number may have none', function () {
    Warehouse::factory()->create(['code' => 'NTH']);
    Warehouse::factory()->count(2)->create(['code' => null]);

    expect(fn () => Warehouse::factory()->create(['code' => 'NTH']))->toThrow(QueryException::class);
    expect(Warehouse::query()->whereNull('code')->count())->toBeGreaterThanOrEqual(2);
});

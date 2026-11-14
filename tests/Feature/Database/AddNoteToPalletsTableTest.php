<?php

use App\Models\Pallet;
use Illuminate\Support\Facades\Schema;

function loadAddNoteToPalletsTableMigration(): object
{
    return require database_path('migrations/2026_11_12_100001_add_note_to_pallets_table.php');
}

test('pallets.note is a nullable column', function () {
    $column = collect(Schema::getColumns('pallets'))->firstWhere('name', 'note');

    expect($column)->not->toBeNull()->and($column['nullable'])->toBeTrue();
});

test('placed and unplaced pallets keep a 255 character note, and a pallet without one has none', function () {
    $placed = Pallet::factory()->create(['note' => str_repeat('p', 255)]);
    $unplaced = Pallet::factory()->unplaced()->create(['note' => 'Olive oil 5L']);
    $bare = Pallet::factory()->create();

    expect($placed->fresh()->note)->toBe(str_repeat('p', 255))
        ->and($unplaced->fresh()->note)->toBe('Olive oil 5L')
        ->and($bare->fresh()->note)->toBeNull();
});

test('down() drops the column and up() adds it back', function () {
    $migration = loadAddNoteToPalletsTableMigration();

    $migration->down();
    expect(Schema::hasColumn('pallets', 'note'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('pallets', 'note'))->toBeTrue();
});

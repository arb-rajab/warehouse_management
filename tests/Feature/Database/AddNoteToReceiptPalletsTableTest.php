<?php

use App\Models\ReceiptPallet;
use Illuminate\Support\Facades\Schema;

function loadAddNoteToReceiptPalletsTableMigration(): object
{
    return require database_path('migrations/2026_11_12_100000_add_note_to_receipt_pallets_table.php');
}

test('receipt_pallets.note is a nullable column', function () {
    $column = collect(Schema::getColumns('receipt_pallets'))->firstWhere('name', 'note');

    expect($column)->not->toBeNull()->and($column['nullable'])->toBeTrue();
});

test('an entry keeps a 255 character note, and an entry without one has none', function () {
    $noted = ReceiptPallet::factory()->create(['note' => str_repeat('a', 255)]);
    $bare = ReceiptPallet::factory()->create();

    expect($noted->fresh()->note)->toBe(str_repeat('a', 255))
        ->and($bare->fresh()->note)->toBeNull();
});

test('down() drops the column and up() adds it back', function () {
    $migration = loadAddNoteToReceiptPalletsTableMigration();

    $migration->down();
    expect(Schema::hasColumn('receipt_pallets', 'note'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('receipt_pallets', 'note'))->toBeTrue();
});

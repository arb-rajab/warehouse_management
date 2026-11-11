<?php

use App\Enums\CellState;
use App\Enums\ReceiptStatus;
use App\Models\Pallet;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\PalletSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ReceiptSeeder;
use Database\Seeders\RowSeeder;
use Database\Seeders\WarehouseSeeder;

function seedReceiptDependencies(): void
{
    User::factory()->create();
    (new ProductSeeder)->run();
    (new WarehouseSeeder)->run();
    (new RowSeeder)->run();
    (new PalletSeeder)->run();
}

test('early-returns without products, warehouses or a user', function () {
    (new ReceiptSeeder)->run();

    expect(Receipt::count())->toBe(0);

    (new ProductSeeder)->run();
    (new ReceiptSeeder)->run();

    expect(Receipt::count())->toBe(0);
});

test('seeds draft and finalized receipts with placed and unplaced pallets', function () {
    seedReceiptDependencies();

    (new ReceiptSeeder)->run();

    expect(Receipt::where('status', ReceiptStatus::Draft)->exists())->toBeTrue()
        ->and(Receipt::where('status', ReceiptStatus::Finalized)->exists())->toBeTrue()
        ->and(Pallet::query()->whereNotNull('receipt_id')->placed()->exists())->toBeTrue()
        ->and(Pallet::query()->whereNotNull('receipt_id')->unplaced()->exists())->toBeTrue();

    Receipt::query()->with(['files', 'pallets'])->get()->each(function (Receipt $receipt) {
        expect($receipt->pallets)->not->toBeEmpty();

        if ($receipt->status === ReceiptStatus::Finalized) {
            expect($receipt->files)->not->toBeEmpty()
                ->and($receipt->finalized_by)->not->toBeNull()
                ->and($receipt->finalized_at)->not->toBeNull()
                ->and($receipt->pallets->every(fn ($entry) => $entry->pallet_id !== null))->toBeTrue();
        } else {
            expect($receipt->finalized_at)->toBeNull()
                ->and($receipt->pallets->every(fn ($entry) => $entry->pallet_id === null))->toBeTrue();
        }
    });
});

test('placed receipt pallets sit in a non-empty cell of their receipt warehouse', function () {
    seedReceiptDependencies();

    (new ReceiptSeeder)->run();

    Pallet::query()->whereNotNull('receipt_id')->placed()->with(['cell.row', 'receipt'])->get()->each(function (Pallet $pallet) {
        expect($pallet->cell->state)->not->toBe(CellState::Empty)
            ->and($pallet->cell->row->warehouse_id)->toBe($pallet->receipt->warehouse_id);
    });
});

test('does not duplicate receipts when run twice', function () {
    seedReceiptDependencies();

    (new ReceiptSeeder)->run();
    $receipts = Receipt::count();
    $pallets = Pallet::count();

    (new ReceiptSeeder)->run();

    expect(Receipt::count())->toBe($receipts)
        ->and(Pallet::count())->toBe($pallets);
});

test('refuses production and writes nothing', function () {
    seedReceiptDependencies();
    app()->instance('env', 'production');

    try {
        expect(fn () => (new ReceiptSeeder)->run())->toThrow(RuntimeException::class, 'in production');
    } finally {
        app()->instance('env', 'testing');
    }

    expect(Receipt::count())->toBe(0);
});

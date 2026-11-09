<?php

use App\Enums\ReceiptStatus;
use App\Models\Pallet;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

test('a receipt belongs to its warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create(); // noise
    $receipt = Receipt::factory()->for($warehouse)->create();

    expect($receipt->warehouse->id)->toBe($warehouse->id)
        ->and($receipt->warehouse->id)->not->toBe($other->id);
});

test('a receipt belongs to the user who created it and, once finalized, to the one who finalized it', function () {
    $author = User::factory()->create();
    $finalizer = User::factory()->create();
    $noise = User::factory()->create();
    $receipt = Receipt::factory()->finalized()->create(['created_by' => $author->id, 'finalized_by' => $finalizer->id]);

    expect($receipt->createdBy->id)->toBe($author->id)
        ->and($receipt->finalizedBy->id)->toBe($finalizer->id)
        ->and($receipt->createdBy->id)->not->toBe($noise->id);
});

test('a draft has no finalizing user', function () {
    $receipt = Receipt::factory()->create();

    expect($receipt->finalizedBy)->toBeNull()
        ->and($receipt->finalized_by)->toBeNull()
        ->and($receipt->finalized_at)->toBeNull();
});

test('the author and finalizer of a receipt are still named after the user is removed', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $receipt->createdBy->delete();
    $receipt->finalizedBy->delete();

    $fresh = Receipt::query()->with(['createdBy', 'finalizedBy'])->findOrFail($receipt->id);

    expect($fresh->createdBy)->not->toBeNull()
        ->and($fresh->finalizedBy)->not->toBeNull();
});

test('a receipt has many files, excluding another receipts files', function () {
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();
    ReceiptFile::factory()->create(); // noise

    expect($receipt->files)->toHaveCount(1)
        ->and($receipt->files->first()->id)->toBe($file->id);
});

test('a receipt has many pallet entries, excluding another receipts entries', function () {
    $receipt = Receipt::factory()->create();
    $entry = ReceiptPallet::factory()->for($receipt)->create();
    ReceiptPallet::factory()->create(); // noise

    expect($receipt->pallets)->toHaveCount(1)
        ->and($receipt->pallets->first()->id)->toBe($entry->id);
});

test('the status is cast to the ReceiptStatus enum and finalized_at to an immutable date', function () {
    $draft = Receipt::factory()->create();
    $finalized = Receipt::factory()->finalized()->create(['finalized_at' => '2026-11-09 10:30:00']);

    expect($draft->fresh()->status)->toBe(ReceiptStatus::Draft)
        ->and($finalized->fresh()->status)->toBe(ReceiptStatus::Finalized)
        ->and($finalized->fresh()->finalized_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($finalized->fresh()->finalized_at->toDateTimeString())->toBe('2026-11-09 10:30:00');
});

test('a receipt created without a status is a draft', function () {
    $receipt = Receipt::create([
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($receipt->status)->toBe(ReceiptStatus::Draft)
        ->and($receipt->fresh()->status)->toBe(ReceiptStatus::Draft);
});

test('isFinalized is true only for a finalized receipt', function () {
    expect(Receipt::factory()->create()->isFinalized())->toBeFalse()
        ->and(Receipt::factory()->finalized()->create()->isFinalized())->toBeTrue();
});

test('forWarehouses keeps the receipts of the given warehouses and is a no-op for null', function () {
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $inNorth = Receipt::factory()->for($north)->create();
    $inSouth = Receipt::factory()->for($south)->create();
    $inDefault = Receipt::factory()->create(); // noise

    expect(Receipt::query()->forWarehouses([$north->id])->pluck('id')->all())->toBe([$inNorth->id])
        ->and(Receipt::query()->forWarehouses([$north->id, $south->id])->pluck('id')->sort()->values()->all())->toBe([$inNorth->id, $inSouth->id])
        ->and(Receipt::query()->forWarehouses(null)->pluck('id')->sort()->values()->all())->toBe([$inNorth->id, $inSouth->id, $inDefault->id])
        ->and(Receipt::query()->forWarehouses([])->count())->toBe(0);
});

test('forWarehouses is table-qualified, so it composes with a join that has its own warehouse_id', function () {
    $warehouse = Warehouse::factory()->create();
    $receipt = Receipt::factory()->for($warehouse)->create();
    Receipt::factory()->create(); // noise

    $ids = Receipt::query()
        ->join('warehouses', 'warehouses.id', '=', 'receipts.warehouse_id')
        ->forWarehouses([$warehouse->id])
        ->pluck('receipts.id')
        ->all();

    expect($ids)->toBe([$receipt->id]);
});

test('ofStatus keeps the drafts or the finalized receipts and is a no-op for null', function () {
    $draft = Receipt::factory()->create();
    $finalized = Receipt::factory()->finalized()->create();

    expect(Receipt::query()->ofStatus(ReceiptStatus::Draft)->pluck('id')->all())->toBe([$draft->id])
        ->and(Receipt::query()->ofStatus(ReceiptStatus::Finalized)->pluck('id')->all())->toBe([$finalized->id])
        ->and(Receipt::query()->ofStatus(null)->count())->toBe(2);
});

test('withDetails loads the files without their path and the pallets in typed order without the pallet id', function () {
    $receipt = Receipt::factory()->create();
    $second = ReceiptFile::factory()->for($receipt)->create();
    $first = ReceiptFile::factory()->for($receipt)->create();
    $typedSecond = ReceiptPallet::factory()->for($receipt)->create(['position' => 1]);
    $typedFirst = ReceiptPallet::factory()->for($receipt)->create(['position' => 0]);

    $loaded = Receipt::query()->with(Receipt::withDetails())->findOrFail($receipt->id);

    expect($loaded->files->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($loaded->files->first()->getAttributes())->not->toHaveKey('path')
        ->and($loaded->pallets->pluck('id')->all())->toBe([$typedFirst->id, $typedSecond->id])
        ->and($loaded->pallets->first()->getAttributes())->not->toHaveKey('pallet_id')
        ->and($loaded->pallets->first()->relationLoaded('product'))->toBeTrue()
        ->and($loaded->warehouse)->not->toBeNull()
        ->and($loaded->createdBy)->not->toBeNull();
});

test('a finalized receipt cannot be deleted while its pallets exist', function () {
    $receipt = Receipt::factory()->finalized()->create();
    Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);

    expect(fn () => $receipt->delete())->toThrow(QueryException::class);
});

test('the factory builds a draft in the Default warehouse unless told otherwise', function () {
    $default = Receipt::factory()->create();
    $elsewhere = Warehouse::factory()->create();
    $other = Receipt::factory()->for($elsewhere)->create();

    expect($default->status)->toBe(ReceiptStatus::Draft)
        ->and($default->warehouse_id)->toBe(Warehouse::firstOrCreateDefault()->id)
        ->and($default->finalized_by)->toBeNull()
        ->and($other->warehouse_id)->toBe($elsewhere->id);
});

test('the finalized factory state stamps who finalized it and when', function () {
    $receipt = Receipt::factory()->finalized()->create();

    expect($receipt->status)->toBe(ReceiptStatus::Finalized)
        ->and($receipt->finalized_by)->not->toBeNull()
        ->and($receipt->finalized_at)->not->toBeNull();
});

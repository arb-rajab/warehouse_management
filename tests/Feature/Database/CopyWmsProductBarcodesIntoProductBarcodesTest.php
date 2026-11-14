<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * `copy_wms_product_barcodes_into_product_barcodes`: every WMS barcode whose
 * product exists and whose value is not yet in `product_barcodes` is copied,
 * with its timestamps; nothing existing is changed or deleted, a re-run copies
 * nothing, and down() keeps the rows. The migration only inserts rows, so
 * RefreshDatabase rolls each test back on sqlite and MySQL alike.
 */
function loadCopyWmsBarcodesMigration(): object
{
    return require database_path('migrations/2026_11_08_100001_copy_wms_product_barcodes_into_product_barcodes.php');
}

function copyTestWmsRow(int $productId, string $barcode, ?string $at = '2026-05-01 10:00:00'): int
{
    return DB::table('wms_product_barcodes')->insertGetId(['product_id' => $productId, 'barcode' => $barcode, 'created_at' => $at, 'updated_at' => $at]);
}

function copyTestStoreRow(int $productId, string $barcode): int
{
    return DB::table('product_barcodes')->insertGetId(['product_id' => $productId, 'barcode' => $barcode, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
}

/**
 * A WMS row whose product does not exist. The cascade normally removes such
 * rows, so the foreign key is set aside: sqlite cannot switch it off inside
 * the test's transaction, but it can defer the check to a commit that never
 * comes (RefreshDatabase rolls back).
 */
function copyTestOrphanWmsRow(int $productId, string $barcode): int
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');

        return copyTestWmsRow($productId, $barcode);
    }

    Schema::disableForeignKeyConstraints();
    try {
        return copyTestWmsRow($productId, $barcode);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

/** @return list<array{int, string}> */
function copyTestStoreRows(): array
{
    return DB::table('product_barcodes')->orderBy('id')->get(['product_id', 'barcode'])
        ->map(fn (object $row): array => [(int) $row->product_id, (string) $row->barcode])->all();
}

test('a WMS barcode is copied onto its product with its timestamps', function () {
    $product = Product::factory()->create();
    copyTestWmsRow($product->id, 'WMS-COPY-1', '2026-05-01 10:00:00');

    loadCopyWmsBarcodesMigration()->up();

    $row = DB::table('product_barcodes')->where('barcode', 'WMS-COPY-1')->sole();
    expect((int) $row->product_id)->toBe($product->id)
        ->and((string) $row->created_at)->toStartWith('2026-05-01 10:00:00')
        ->and((string) $row->updated_at)->toStartWith('2026-05-01 10:00:00');
});

test('a WMS row without timestamps is copied with the current time', function () {
    $this->freezeSecond();
    $product = Product::factory()->create();
    copyTestWmsRow($product->id, 'WMS-NO-TIME', null);

    loadCopyWmsBarcodesMigration()->up();

    $row = DB::table('product_barcodes')->where('barcode', 'WMS-NO-TIME')->sole();
    expect((string) $row->created_at)->toStartWith(now()->toDateTimeString())
        ->and((string) $row->updated_at)->toStartWith(now()->toDateTimeString());
});

test('a value already on the same product is not copied twice', function () {
    $product = Product::factory()->create();
    $existing = copyTestStoreRow($product->id, 'SAME-PRODUCT');
    copyTestWmsRow($product->id, 'SAME-PRODUCT');

    loadCopyWmsBarcodesMigration()->up();

    expect(DB::table('product_barcodes')->where('barcode', 'SAME-PRODUCT')->pluck('id')->all())->toBe([$existing]);
});

test('a value another product already holds is skipped and both products keep their rows', function () {
    $holder = Product::factory()->create();
    $wmsProduct = Product::factory()->create();
    copyTestStoreRow($holder->id, 'HELD-ELSEWHERE');
    copyTestStoreRow($wmsProduct->id, 'OWN-STORE');
    $wmsRow = copyTestWmsRow($wmsProduct->id, 'HELD-ELSEWHERE');

    loadCopyWmsBarcodesMigration()->up();

    expect(copyTestStoreRows())->toBe([[$holder->id, 'HELD-ELSEWHERE'], [$wmsProduct->id, 'OWN-STORE']])
        ->and(DB::table('wms_product_barcodes')->where('id', $wmsRow)->value('barcode'))->toBe('HELD-ELSEWHERE');
});

test('a WMS row whose product is gone is not copied', function () {
    $product = Product::factory()->create();
    copyTestOrphanWmsRow(999_999, 'ORPHANED');
    copyTestWmsRow($product->id, 'NOT-ORPHANED');

    loadCopyWmsBarcodesMigration()->up();

    expect(copyTestStoreRows())->toBe([[$product->id, 'NOT-ORPHANED']])
        ->and(DB::table('wms_product_barcodes')->where('barcode', 'ORPHANED')->exists())->toBeTrue();
});

test('running up() again inserts nothing and existing rows are never changed', function () {
    $product = Product::factory()->create();
    $noise = Product::factory()->create();
    $storeRow = copyTestStoreRow($noise->id, 'STORE-NOISE');
    copyTestWmsRow($product->id, 'AGAIN-1');
    copyTestWmsRow($product->id, 'AGAIN-2');
    $migration = loadCopyWmsBarcodesMigration();

    $migration->up();
    $afterFirst = DB::table('product_barcodes')->orderBy('id')->get()->toArray();
    $migration->up();

    expect(DB::table('product_barcodes')->orderBy('id')->get()->toArray())->toEqual($afterFirst)
        ->and(copyTestStoreRows())->toBe([[$noise->id, 'STORE-NOISE'], [$product->id, 'AGAIN-1'], [$product->id, 'AGAIN-2']])
        ->and(DB::table('product_barcodes')->where('id', $storeRow)->value('updated_at'))->toStartWith('2026-01-01 00:00:00')
        ->and(DB::table('wms_product_barcodes')->count())->toBe(2);
});

test('more rows than one chunk are all copied, in id order', function () {
    $product = Product::factory()->create();
    $now = now()->toDateTimeString();
    DB::table('wms_product_barcodes')->insert(array_map(
        fn (int $i): array => ['product_id' => $product->id, 'barcode' => sprintf('CHUNK-%04d', $i), 'created_at' => $now, 'updated_at' => $now],
        range(1, 1203),
    ));

    loadCopyWmsBarcodesMigration()->up();

    $copied = DB::table('product_barcodes')->where('product_id', $product->id)->orderBy('id')->pluck('barcode');
    expect($copied)->toHaveCount(1203)
        ->and($copied->first())->toBe('CHUNK-0001')
        ->and($copied->last())->toBe('CHUNK-1203');
});

test('the counts are logged', function () {
    Log::spy();
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    copyTestStoreRow($other->id, 'LOG-HELD');
    copyTestWmsRow($product->id, 'LOG-NEW');
    copyTestWmsRow($product->id, 'LOG-HELD');
    copyTestOrphanWmsRow(999_998, 'LOG-ORPHAN');

    loadCopyWmsBarcodesMigration()->up();

    Log::shouldHaveReceived('info')->with('Copied WMS barcodes into product_barcodes', ['copied' => 1, 'already_present' => 1, 'product_missing' => 1])->once();
});

test('down() is a no-op that keeps the copied rows', function () {
    $product = Product::factory()->create();
    copyTestWmsRow($product->id, 'KEPT-ON-DOWN');
    $migration = loadCopyWmsBarcodesMigration();
    $migration->up();

    $migration->down();

    expect(copyTestStoreRows())->toBe([[$product->id, 'KEPT-ON-DOWN']])
        ->and(DB::table('wms_product_barcodes')->count())->toBe(1);
});

test('up() is a no-op when either table is missing', function (string $missing) {
    if (DB::connection()->getDriverName() === 'mysql') {
        $this->markTestSkipped('A rename commits on MySQL; the guard does not depend on the driver.');
    }

    $product = Product::factory()->create();
    copyTestWmsRow($product->id, 'MISSING-TABLE');
    Schema::rename($missing, $missing.'_away');

    try {
        loadCopyWmsBarcodesMigration()->up();
    } finally {
        Schema::rename($missing.'_away', $missing);
    }

    expect(DB::table('product_barcodes')->count())->toBe(0);
})->with(['the WMS table' => ['wms_product_barcodes'], 'the store table' => ['product_barcodes']]);

test('on MySQL a value differing only in case from one already present counts as present', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('sqlite compares barcodes exactly; MySQL compares under the column\'s case-insensitive collation.');
    }

    $holder = Product::factory()->create();
    $product = Product::factory()->create();
    copyTestStoreRow($holder->id, 'abc-case');
    copyTestWmsRow($product->id, 'ABC-CASE');

    loadCopyWmsBarcodesMigration()->up();

    expect(copyTestStoreRows())->toBe([[$holder->id, 'abc-case']]);
});

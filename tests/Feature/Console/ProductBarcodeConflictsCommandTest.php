<?php

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * products:barcode-conflicts reports, without writing, the values several
 * products share (a), the WMS barcodes the copy migration skipped because
 * another product holds the value (b), WMS rows whose product is gone (c),
 * values equal to another product's id (d), values that cannot be drawn (e)
 * and values a scan does not match as stored (f). The rows are written
 * straight into the tables, as legacy and imported data holds them.
 */
function conflictRow(int $productId, string $barcode, string $table = 'product_barcodes'): int
{
    return DB::table($table)->insertGetId(['product_id' => $productId, 'barcode' => $barcode, 'created_at' => now(), 'updated_at' => now()]);
}

function conflictOrphanWmsRow(int $productId, string $barcode): int
{
    // The cascade normally removes such rows; sqlite defers the check to a commit that never comes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');

        return conflictRow($productId, $barcode, 'wms_product_barcodes');
    }

    Schema::disableForeignKeyConstraints();
    try {
        return conflictRow($productId, $barcode, 'wms_product_barcodes');
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

/** @return array<string, list<string>> */
function conflictSnapshot(): array
{
    return collect(['product_barcodes', 'wms_product_barcodes', 'products'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => json_encode($row))->all()])->all();
}

/**
 * One example of every category, plus noise rows no category lists: a
 * product's own id stored as its barcode (the import's Mat_ID row), a WMS
 * row already copied, and a value only one product holds.
 *
 * @return array<string, mixed>
 */
function conflictFixture(): array
{
    $first = Product::factory()->create(['name' => 'First oil']);
    $second = Product::factory()->create(['name' => 'Second oil']);
    $third = Product::factory()->create(['name' => 'Third oil']);
    DB::table('products')->update(['barcode' => null]);

    $sharedA = conflictRow($first->id, 'SHARED-1');
    $sharedB = conflictRow($second->id, 'SHARED-1');
    // The same product twice is not a conflict.
    conflictRow($third->id, 'TWICE');
    conflictRow($third->id, 'TWICE');
    // Noise: the import's own-id row, a plain value, and a WMS row already copied.
    conflictRow($first->id, (string) $first->id);
    conflictRow($first->id, 'ONLY-FIRST');
    conflictRow($first->id, 'ONLY-FIRST', 'wms_product_barcodes');
    // (b) a WMS value another product holds.
    $skipped = conflictRow($third->id, 'HELD-BY-SECOND', 'wms_product_barcodes');
    conflictRow($second->id, 'HELD-BY-SECOND');
    // (c) a WMS row whose product is gone.
    $orphan = conflictOrphanWmsRow(999_999, 'ORPHAN-WMS');
    // (d) another product's id.
    $otherId = conflictRow($second->id, (string) $third->id);
    // (e) and (f): Arabic text, Arabic-Indic digits, a legacy column value.
    $arabic = conflictRow($third->id, 'متجر');
    $digits = conflictRow($third->id, '٥٩٠١');
    DB::table('products')->where('id', $second->id)->update(['barcode' => 'LEGACY-ONLY']);
    // Noise: a legacy column value the product's barcodes already hold.
    DB::table('products')->where('id', $first->id)->update(['barcode' => 'ONLY-FIRST']);

    return compact('first', 'second', 'third', 'sharedA', 'sharedB', 'skipped', 'orphan', 'otherId', 'arabic', 'digits');
}

test('the JSON report lists every category with its rows and changes nothing', function () {
    $f = conflictFixture();
    $before = conflictSnapshot();

    Artisan::call('products:barcode-conflicts', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $item = fn (string $barcode, ?int $productId, ?string $name, ?int $rowId, array $others = [], ?string $note = null): array => [
        'barcode' => $barcode, 'product_id' => $productId, 'product_name' => $name, 'row_id' => $rowId, 'other_product_ids' => $others, 'note' => $note,
    ];
    expect($report)->toBe([
        'wms_table' => true,
        'counts' => ['shared_value' => 2, 'wms_skipped' => 1, 'wms_product_missing' => 1, 'other_product_id' => 1, 'not_printable' => 1, 'not_matched_by_scan' => 2],
        'categories' => [
            'shared_value' => [
                $item('SHARED-1', $f['first']->id, 'First oil', $f['sharedA'], [$f['second']->id]),
                $item('SHARED-1', $f['second']->id, 'Second oil', $f['sharedB'], [$f['first']->id]),
            ],
            'wms_skipped' => [$item('HELD-BY-SECOND', $f['third']->id, 'Third oil', $f['skipped'], [$f['second']->id], 'wms_product_barcodes row')],
            'wms_product_missing' => [$item('ORPHAN-WMS', 999_999, null, $f['orphan'], [], 'wms_product_barcodes row')],
            'other_product_id' => [$item((string) $f['third']->id, $f['second']->id, null, $f['otherId'], [$f['third']->id])],
            // Arabic-Indic digits are drawn once converted, so only the Arabic text.
            'not_printable' => [$item('متجر', $f['third']->id, null, $f['arabic'])],
            'not_matched_by_scan' => [
                $item('٥٩٠١', $f['third']->id, null, $f['digits'], [], 'a scan matches "٥٩٠١" only; the label shows "5901"'),
                $item('LEGACY-ONLY', $f['second']->id, null, null, [], 'products.barcode column'),
            ],
        ],
    ])->and(conflictSnapshot())->toBe($before);
});

test('the tables show the counts, list the conflicts and leave the information rows to --all', function () {
    $f = conflictFixture();

    $this->artisan('products:barcode-conflicts')
        ->expectsTable(['category', 'rows'], [
            ['(a) Values on more than one product', '2'],
            ['(b) WMS barcodes not copied because another product holds the value', '1'],
            ['(c) WMS barcodes whose product is gone', '1'],
            ['(d) Values equal to another product\'s id (a scan resolves the id first)', '1'],
            ['(e) Values that cannot be drawn as a Code 128 label', '1'],
            ['(f) Values a scan does not match as stored (Arabic-Indic digits, surrounding spaces, the legacy products.barcode column)', '2'],
        ])
        ->expectsOutputToContain('(a) Values on more than one product')
        ->expectsOutputToContain('Run with --all to list the rows of (e) and (f).')
        ->doesntExpectOutputToContain('LEGACY-ONLY')
        ->assertSuccessful();

    $this->artisan('products:barcode-conflicts', ['--all' => true])
        ->expectsOutputToContain('LEGACY-ONLY')
        ->doesntExpectOutputToContain('Run with --all')
        ->assertSuccessful();
});

test('the CSV has one line per row with its category', function () {
    $f = conflictFixture();

    Artisan::call('products:barcode-conflicts', ['--csv' => true]);
    $lines = array_map(fn (string $line): array => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", Artisan::output()))));

    expect($lines[0])->toBe(['category', 'barcode', 'product_id', 'product_name', 'row_id', 'other_product_ids', 'note'])
        ->and($lines[1])->toBe(['shared_value', 'SHARED-1', (string) $f['first']->id, 'First oil', (string) $f['sharedA'], (string) $f['second']->id, ''])
        ->and(array_count_values(array_column(array_slice($lines, 1), 0)))->toBe([
            'shared_value' => 2, 'wms_skipped' => 1, 'wms_product_missing' => 1, 'other_product_id' => 1, 'not_printable' => 1, 'not_matched_by_scan' => 2,
        ]);
});

test('it exits 0 with conflicts, and 1 with --fail-on-conflicts', function () {
    conflictFixture();

    $this->artisan('products:barcode-conflicts')->assertExitCode(0);
    $this->artisan('products:barcode-conflicts', ['--fail-on-conflicts' => true])->assertExitCode(1);
});

test('--fail-on-conflicts exits 0 when only information rows exist', function () {
    $product = Product::factory()->create();
    conflictRow($product->id, 'متجر');
    conflictRow($product->id, (string) $product->id);

    $this->artisan('products:barcode-conflicts', ['--fail-on-conflicts' => true])->assertExitCode(0);
});

test('an empty database reports nothing', function () {
    Artisan::call('products:barcode-conflicts', ['--json' => true]);

    expect(json_decode(Artisan::output(), true)['counts'])->toBe([
        'shared_value' => 0, 'wms_skipped' => 0, 'wms_product_missing' => 0, 'other_product_id' => 0, 'not_printable' => 0, 'not_matched_by_scan' => 0,
    ]);
});

test('it still runs once the WMS table is dropped, and says (b) and (c) do not apply', function () {
    if (DB::connection()->getDriverName() === 'mysql') {
        $this->markTestSkipped('A rename commits on MySQL; the check does not depend on the driver.');
    }
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    conflictRow($product->id, 'SHARED-2');
    conflictRow($other->id, 'SHARED-2');
    Schema::rename('wms_product_barcodes', 'wms_product_barcodes_away');

    try {
        $this->artisan('products:barcode-conflicts')
            ->expectsOutputToContain('wms_product_barcodes no longer exists: categories (b) and (c) do not apply.')
            ->assertSuccessful();
        Artisan::call('products:barcode-conflicts', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);
    } finally {
        Schema::rename('wms_product_barcodes_away', 'wms_product_barcodes');
    }

    expect($report['wms_table'])->toBeFalse()
        ->and($report['counts']['shared_value'])->toBe(2)
        ->and($report['counts']['wms_skipped'])->toBe(0);
});

test('on MySQL values differing only in case count as one shared value', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('sqlite compares barcodes exactly; MySQL compares under the column\'s case-insensitive collation.');
    }
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    conflictRow($product->id, 'case-shared');
    conflictRow($other->id, 'CASE-SHARED');

    Artisan::call('products:barcode-conflicts', ['--json' => true]);

    expect(json_decode(Artisan::output(), true)['counts']['shared_value'])->toBe(2);
});

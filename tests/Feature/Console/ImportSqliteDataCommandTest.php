<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a standalone legacy-shaped SQLite file, independent of this app's own
 * migrations, so the command is exercised against a real second connection
 * rather than the same in-memory database it is importing into. `rows.legacy_note`
 * deliberately has no counterpart in the current `rows` table, covering a
 * source schema that has drifted from the destination.
 */
function createSqliteImportSourceFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wms_import_source_');

    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE wms_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, password TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
    $pdo->exec("INSERT INTO wms_users (id, name, email, password, created_at, updated_at) VALUES (5, 'Legacy Admin', 'legacy@example.com', 'hashed', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    $pdo->exec("CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, ar_name TEXT NOT NULL DEFAULT '', published INTEGER NOT NULL DEFAULT 1, created_at TEXT, updated_at TEXT)");
    $pdo->exec("INSERT INTO products (id, name, ar_name, published, created_at, updated_at) VALUES (9, 'Legacy Widget', 'ودجة قديمة', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    $pdo->exec('CREATE TABLE rows (id INTEGER PRIMARY KEY AUTOINCREMENT, letter TEXT NOT NULL, cells_count INTEGER NOT NULL, flats_count INTEGER NOT NULL, legacy_note TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec("INSERT INTO rows (id, letter, cells_count, flats_count, legacy_note, created_at, updated_at) VALUES (3, 'A', 10, 2, 'not on the current schema', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    $pdo->exec("CREATE TABLE cells (id INTEGER PRIMARY KEY AUTOINCREMENT, row_id INTEGER NOT NULL, cell_number INTEGER NOT NULL, flat_number INTEGER NOT NULL, state TEXT NOT NULL DEFAULT 'empty', is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT, updated_at TEXT)");
    $pdo->exec("INSERT INTO cells (id, row_id, cell_number, flat_number, state, is_active, created_at, updated_at) VALUES (7, 3, 1, 1, 'occupied', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    $pdo->exec('CREATE TABLE pallets (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, cell_id INTEGER NOT NULL, expiration_date TEXT, remaining_boxes INTEGER NOT NULL, created_at TEXT, updated_at TEXT)');
    $pdo->exec("INSERT INTO pallets (id, product_id, cell_id, expiration_date, remaining_boxes, created_at, updated_at) VALUES (12, 9, 7, '2027-01-01', 4, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    return $path;
}

afterEach(function () {
    if (isset($this->sqliteImportSourcePath) && file_exists($this->sqliteImportSourcePath)) {
        unlink($this->sqliteImportSourcePath);
    }
});

test('the import command copies rows from the legacy sqlite file in foreign-key-safe order', function () {
    $this->sqliteImportSourcePath = createSqliteImportSourceFile();

    $this->artisan('db:import-sqlite', ['path' => $this->sqliteImportSourcePath])->assertSuccessful();

    expect(DB::table('wms_users')->where('id', 5)->first())
        ->name->toBe('Legacy Admin')
        ->email->toBe('legacy@example.com');

    expect(DB::table('products')->where('id', 9)->first())
        ->name->toBe('Legacy Widget')
        ->ar_name->toBe('ودجة قديمة')
        ->published->toBe(1);

    // "legacy_note" exists only in the source file (see
    // createSqliteImportSourceFile() above); the import succeeding at all
    // proves the column was dropped rather than breaking the insert.
    $row = DB::table('rows')->where('id', 3)->first();
    expect($row->letter)->toBe('A');
    expect($row->cells_count)->toBe(10);

    expect(DB::table('cells')->where('id', 7)->first())
        ->row_id->toBe(3)
        ->state->toBe('occupied');

    expect(DB::table('pallets')->where('id', 12)->first())
        ->product_id->toBe(9)
        ->cell_id->toBe(7)
        ->remaining_boxes->toBe(4);
});

test('the import command resequences autoincrement ids so new rows do not collide', function () {
    $this->sqliteImportSourcePath = createSqliteImportSourceFile();

    $this->artisan('db:import-sqlite', ['path' => $this->sqliteImportSourcePath])->assertSuccessful();

    $newProduct = Product::factory()->create();

    expect($newProduct->id)->toBeGreaterThan(9);
});

test('the import command skips a table entirely absent from the source file, without failing', function () {
    $path = tempnam(sys_get_temp_dir(), 'wms_import_source_');
    $this->sqliteImportSourcePath = $path;

    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE wms_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, password TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
    $pdo->exec("INSERT INTO wms_users (id, name, email, password, created_at, updated_at) VALUES (1, 'Only User', 'only@example.com', 'hashed', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

    $this->artisan('db:import-sqlite', ['path' => $path])
        ->expectsOutputToContain("Skipping 'products': not present in the source database.")
        ->assertSuccessful();

    expect(DB::table('wms_users')->where('id', 1)->exists())->toBeTrue();
    expect(Product::count())->toBe(0);
});

test('the import command fails without writing anything when a destination table already has data', function () {
    $this->sqliteImportSourcePath = createSqliteImportSourceFile();

    $untouched = Product::factory()->create(['name' => 'Already Here']);

    $this->artisan('db:import-sqlite', ['path' => $this->sqliteImportSourcePath])->assertFailed();

    expect(Product::count())->toBe(1);
    expect($untouched->fresh()->name)->toBe('Already Here');
    expect(DB::table('wms_users')->where('id', 5)->exists())->toBeFalse();
    expect(DB::table('rows')->count())->toBe(0);
});

test('the import command truncates existing destination data when --truncate is passed', function () {
    $this->sqliteImportSourcePath = createSqliteImportSourceFile();

    Product::factory()->create(['id' => 999, 'name' => 'Stale Product']);

    $this->artisan('db:import-sqlite', ['path' => $this->sqliteImportSourcePath, '--truncate' => true])->assertSuccessful();

    expect(Product::find(999))->toBeNull();
    expect(DB::table('products')->where('id', 9)->exists())->toBeTrue();
});

test('the import command fails when given a path that does not exist', function () {
    $this->artisan('db:import-sqlite', ['path' => '/tmp/definitely-not-a-real-file.sqlite'])->assertFailed();

    expect(DB::table('wms_users')->count())->toBe(0);
});

test('the import command fails when the destination is missing a table the source has', function () {
    $this->sqliteImportSourcePath = createSqliteImportSourceFile();

    // Dropped inside RefreshDatabase's per-test transaction, so it is rolled
    // back automatically once this test finishes. "pallets" is in the source
    // file created above, unlike a table this test never touches.
    Schema::dropIfExists('pallets');

    $this->artisan('db:import-sqlite', ['path' => $this->sqliteImportSourcePath])->assertFailed();

    expect(DB::table('wms_users')->count())->toBe(0);
});

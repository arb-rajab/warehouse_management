<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function fulltextIndexMigrationPath(): string
{
    return database_path('migrations/2026_09_16_000000_add_fulltext_index_to_products_table.php');
}

function loadAddFulltextIndexToProductsTableMigration(): object
{
    return require fulltextIndexMigrationPath();
}

test('the migration is a no-op on sqlite rather than throwing', function () {
    // Laravel's base schema grammar implements compileFullText() as an
    // outright RuntimeException rather than leaving it unimplemented, so an
    // unguarded fullText() does not get skipped on sqlite the way an
    // uncompilable command would — it breaks `migrate` outright, and with it
    // every test run and every sqlite-backed local checkout. The driver guard
    // is the only thing standing between that and this test connection.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    loadAddFulltextIndexToProductsTableMigration()->up();

    expect(Schema::hasIndex('products', 'products_name_ar_name_fulltext'))->toBeFalse();
    expect(Schema::hasTable('products'))->toBeTrue();
});

test('the migration is idempotent and leaves existing rows searchable', function () {
    $migration = loadAddFulltextIndexToProductsTableMigration();
    $migration->up();
    $migration->up();

    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة زرقاء']);
    // Noise: a product the term must not reach, proving the migration left a
    // working WHERE behind rather than a table that matches everything.
    Product::factory()->create(['name' => 'Small Gadget', 'ar_name' => 'أداة صغيرة']);

    expect(Product::query()->searchByName('Widget')->pluck('id')->all())->toBe([$matching->id]);
});

test('rolling the migration back is a no-op on sqlite and keeps the table', function () {
    $migration = loadAddFulltextIndexToProductsTableMigration();
    $migration->up();

    $migration->down();
    $migration->down();

    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumns('products', ['name', 'ar_name']))->toBeTrue();
});

test('the index covers exactly the columns Product matches against', function () {
    // MySQL resolves a MATCH only against a FULLTEXT index covering *exactly*
    // the column list in the clause — a mismatch fails every product search
    // with errno 1191 rather than degrading to a slower plan. sqlite never
    // builds the index, so the migration source is the only place this can be
    // pinned on the test connection (the same reason CreateProductsTableTest
    // asserts the signed int(11) id against source).
    $source = file_get_contents(fulltextIndexMigrationPath());

    expect(Product::SEARCHABLE_NAME_COLUMNS)->toBe(['name', 'ar_name']);
    expect($source)->toContain("\$table->fullText(['name', 'ar_name'], self::INDEX_NAME);");
    expect($source)->toContain("private const string INDEX_NAME = 'products_name_ar_name_fulltext';");
    expect($source)->toContain('$table->dropFullText(self::INDEX_NAME);');
});

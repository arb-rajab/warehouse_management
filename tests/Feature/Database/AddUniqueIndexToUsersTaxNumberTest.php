<?php

use App\Support\CustomerTaxNumber;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `add_unique_index_to_users_tax_number`: blank tax numbers become NULL, values
 * are trimmed, a tax number several accounts share stays on the one ranked
 * first (not banned or archived, then linked to Otajer, then lowest id) and
 * is recorded and released on the others, then the unique index is built.
 *
 * Also run against MySQL in CI, where the index follows the column's
 * case-insensitive collation. MySQL commits DDL implicitly, so every row
 * here is named for this file and removed afterwards, and the index and the
 * release table are put back.
 */
function loadAddUniqueTaxNumberIndexMigration(): object
{
    return require database_path('migrations/2026_11_03_110000_add_unique_index_to_users_tax_number.php');
}

function dropTaxNumberIndex(): void
{
    if (Schema::hasIndex('users', ['tax_number'], 'unique')) {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tax_number']);
        });
    }
}

/**
 * Insert a store account straight into `users`, past the model's normalizing
 * mutator, as legacy and imported rows were written.
 *
 * @param  array<string, mixed>  $attributes
 */
function taxMigrationUser(?string $taxNumber, array $attributes = []): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Tax migration user',
        'user_type' => 'customer',
        'tax_number' => $taxNumber,
        ...$attributes,
    ]);
}

afterEach(function () {
    DB::table('users')->where('name', 'Tax migration user')->delete();

    if (Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE)) {
        DB::table(CustomerTaxNumber::RELEASES_TABLE)->where('tax_number', 'like', 'MIGTEST%')->delete();
    }

    if (! Schema::hasIndex('users', ['tax_number'], 'unique') || ! Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE)) {
        dropTaxNumberIndex();
        loadAddUniqueTaxNumberIndexMigration()->up();
    }
});

test('a second account with the same tax number is refused, while any number may have none', function () {
    taxMigrationUser('MIGTEST-1');
    taxMigrationUser(null);
    taxMigrationUser(null);

    expect(Schema::hasIndex('users', ['tax_number'], 'unique'))->toBeTrue()
        ->and(fn () => taxMigrationUser('MIGTEST-1'))->toThrow(QueryException::class)
        ->and(DB::table('users')->where('tax_number', 'MIGTEST-1')->count())->toBe(1);
});

test('blank values become null and padded values are trimmed', function () {
    dropTaxNumberIndex();
    $empty = taxMigrationUser('');
    $spaces = taxMigrationUser('   ');
    $padded = taxMigrationUser("  MIGTEST-PAD\u{00A0}\t");
    $plain = taxMigrationUser('MIGTEST-PLAIN');

    loadAddUniqueTaxNumberIndexMigration()->up();

    expect(DB::table('users')->whereIn('id', [$empty, $spaces, $padded, $plain])->orderBy('id')->pluck('tax_number')->all())
        ->toBe([null, null, 'MIGTEST-PAD', 'MIGTEST-PLAIN'])
        ->and(DB::table(CustomerTaxNumber::RELEASES_TABLE)->where('tax_number', 'like', 'MIGTEST%')->count())->toBe(0)
        ->and(Schema::hasIndex('users', ['tax_number'], 'unique'))->toBeTrue();
});

test('a shared tax number stays on the best-ranked account and is recorded and released on the others', function () {
    dropTaxNumberIndex();
    // Group A: a banned account, an active one, and an active one linked to Otajer, which keeps it.
    $bannedA = taxMigrationUser('MIGTEST-A', ['banned' => 1, 'AccSysID' => 501]);
    $activeA = taxMigrationUser('MIGTEST-A');
    $linkedA = taxMigrationUser('MIGTEST-A', ['AccSysID' => 502]);
    // Group B: an archived account and an active one, which keeps it although its id is higher.
    $archivedB = taxMigrationUser('MIGTEST-B', ['is_delete' => 1]);
    $activeB = taxMigrationUser('MIGTEST-B');
    // Group C: two equal accounts, one of them padded; the lowest id keeps it.
    $firstC = taxMigrationUser('MIGTEST-C');
    $secondC = taxMigrationUser(' MIGTEST-C ');
    // Noise: a tax number nobody else has.
    $noise = taxMigrationUser('MIGTEST-UNIQUE');

    loadAddUniqueTaxNumberIndexMigration()->up();

    $taxNumbers = DB::table('users')->where('name', 'Tax migration user')->pluck('tax_number', 'id')->all();
    expect($taxNumbers)->toEqual([
        $bannedA => null, $activeA => null, $linkedA => 'MIGTEST-A',
        $archivedB => null, $activeB => 'MIGTEST-B',
        $firstC => 'MIGTEST-C', $secondC => null,
        $noise => 'MIGTEST-UNIQUE',
    ]);

    $releases = DB::table(CustomerTaxNumber::RELEASES_TABLE)->where('tax_number', 'like', 'MIGTEST%')->orderBy('user_id')
        ->get(['user_id', 'kept_user_id', 'tax_number', 'released_at'])
        ->map(fn ($row) => [(int) $row->user_id, (int) $row->kept_user_id, $row->tax_number, $row->released_at !== null])
        ->all();
    expect($releases)->toBe([
        [$bannedA, $linkedA, 'MIGTEST-A', true],
        [$activeA, $linkedA, 'MIGTEST-A', true],
        [$archivedB, $activeB, 'MIGTEST-B', true],
        [$secondC, $firstC, 'MIGTEST-C', true],
    ])->and(Schema::hasIndex('users', ['tax_number'], 'unique'))->toBeTrue();
});

test('on MySQL tax numbers differing only in case are one tax number', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('sqlite compares tax numbers exactly; MySQL\'s utf8mb4_unicode_ci collation ignores case.');
    }

    dropTaxNumberIndex();
    $upper = taxMigrationUser('MIGTEST-CASE');
    $lower = taxMigrationUser('migtest-case');

    loadAddUniqueTaxNumberIndexMigration()->up();

    expect(DB::table('users')->whereIn('id', [$upper, $lower])->orderBy('id')->pluck('tax_number')->all())->toBe(['MIGTEST-CASE', null])
        ->and(DB::table(CustomerTaxNumber::RELEASES_TABLE)->where('user_id', $lower)->value('tax_number'))->toBe('migtest-case')
        ->and(fn () => taxMigrationUser('Migtest-Case'))->toThrow(QueryException::class);
});

test('running up() again leaves the index and the record alone', function () {
    $migration = loadAddUniqueTaxNumberIndexMigration();
    dropTaxNumberIndex();
    taxMigrationUser('MIGTEST-AGAIN');
    taxMigrationUser('MIGTEST-AGAIN');
    $migration->up();

    $migration->up();

    expect(DB::table(CustomerTaxNumber::RELEASES_TABLE)->where('tax_number', 'MIGTEST-AGAIN')->count())->toBe(1)
        ->and(collect(Schema::getIndexes('users'))->filter(fn (array $index) => $index['columns'] === ['tax_number'])->count())->toBe(1);
});

test('down() drops the index and gives each released tax number back to an account that still has none', function () {
    $migration = loadAddUniqueTaxNumberIndexMigration();
    dropTaxNumberIndex();
    $kept = taxMigrationUser('MIGTEST-DOWN');
    $released = taxMigrationUser('MIGTEST-DOWN');
    $replaced = taxMigrationUser('MIGTEST-DOWN');
    $migration->up();
    DB::table('users')->where('id', $replaced)->update(['tax_number' => 'MIGTEST-NEW']);

    $migration->down();

    expect(Schema::hasIndex('users', ['tax_number'], 'unique'))->toBeFalse()
        ->and(Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE))->toBeFalse()
        ->and(DB::table('users')->whereIn('id', [$kept, $released, $replaced])->orderBy('id')->pluck('tax_number')->all())
        ->toBe(['MIGTEST-DOWN', 'MIGTEST-DOWN', 'MIGTEST-NEW']);
});

test('down() keeps the record in production', function () {
    $migration = loadAddUniqueTaxNumberIndexMigration();
    dropTaxNumberIndex();
    taxMigrationUser('MIGTEST-PROD');
    taxMigrationUser('MIGTEST-PROD');
    $migration->up();

    app()->instance('env', 'production');
    $migration->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE))->toBeTrue()
        ->and(Schema::hasIndex('users', ['tax_number'], 'unique'))->toBeFalse()
        ->and(DB::table('users')->where('tax_number', 'MIGTEST-PROD')->count())->toBe(2);
});

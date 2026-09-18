<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MySQL rejects any identifier longer than 64 characters outright (errno 1059),
 * and Laravel derives index and constraint names from the table name plus every
 * column in the key -- so a composite key on an already-long table name
 * overshoots with nothing in the migration hinting at it.
 *
 * Sqlite has no such limit, and sqlite is both the test connection (phpunit.xml)
 * and CI's, so a name that MySQL will refuse passes the whole suite and only
 * surfaces when `migrate --force` runs against production. That is exactly how
 * `cell_verification_round_row_cell_verification_round_id_row_id_unique` (68
 * characters) reached a deploy. These tests read the schema sqlite actually
 * built and hold every generated name to MySQL's limit.
 */
const MYSQL_MAX_IDENTIFIER_LENGTH = 64;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Reads sqlite_master to inventory names; MySQL enforces this limit itself.');
    }
});

/**
 * @return Collection<int, object>
 */
function schemaObjects(): Collection
{
    return collect(DB::select(
        "select type, name, tbl_name from sqlite_master where name not like 'sqlite_%'"
    ));
}

test('the migrated schema is actually present', function () {
    // Without this, every assertion below passes vacuously if migrations stop
    // running -- an empty inventory has no over-long name in it.
    expect(schemaObjects())->not->toBeEmpty();
});

test('no table or index name exceeds the MySQL identifier limit', function () {
    $offenders = schemaObjects()
        ->filter(fn (object $object): bool => strlen($object->name) > MYSQL_MAX_IDENTIFIER_LENGTH)
        ->map(fn (object $object): string => sprintf(
            '%s "%s" (%d chars) on table %s',
            $object->type,
            $object->name,
            strlen($object->name),
            $object->tbl_name,
        ))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('no derived foreign key constraint name exceeds the MySQL identifier limit', function () {
    // Sqlite stores a foreign key's columns but never its name, so reconstruct
    // what Blueprint::createIndexName() hands MySQL: the table, then every
    // constrained column, then the suffix, joined by underscores.
    $offenders = schemaObjects()
        ->where('type', 'table')
        ->flatMap(fn (object $table): array => collect(DB::select('pragma foreign_key_list("'.$table->name.'")'))
            ->groupBy('id')
            ->map(fn ($columns): string => $table->name.'_'.$columns->pluck('from')->implode('_').'_foreign')
            ->all())
        ->filter(fn (string $name): bool => strlen($name) > MYSQL_MAX_IDENTIFIER_LENGTH)
        ->map(fn (string $name): string => sprintf('foreign key "%s" (%d chars)', $name, strlen($name)))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The index backing `Product::applyNameSearch()`. Named explicitly rather
     * than letting the Blueprint derive one, because `down()` and the
     * idempotency guard below both have to name it back.
     */
    private const string INDEX_NAME = 'products_name_ar_name_fulltext';

    /**
     * The drivers whose schema grammar can compile a FULLTEXT index. Laravel's
     * base schema grammar defines `compileFullText()` as an outright
     * `RuntimeException` rather than leaving it unimplemented, so an
     * unguarded `fullText()` *throws* on sqlite instead of being skipped the
     * way an uncompilable command would be — which would break `migrate` for
     * every test run and every sqlite-backed local checkout.
     *
     * Kept here rather than read off `Product::SEARCHABLE_NAME_COLUMNS` and
     * friends so this migration stays self-contained: a migration has to keep
     * describing the schema it wrote even after the model moves on.
     *
     * @var list<string>
     */
    private const array FULL_TEXT_DRIVERS = ['mysql', 'mariadb'];

    /**
     * Run the migrations.
     *
     * Adds the FULLTEXT index that `MATCH (name, ar_name) AGAINST (...)` needs.
     * MySQL resolves a `MATCH` only against an index covering *exactly* the
     * column list in the clause, so this index's columns and
     * `Product::SEARCHABLE_NAME_COLUMNS` have to stay in step — changing one
     * without the other fails every product search with errno 1191 rather than
     * degrading.
     *
     * Sqlite — the test connection (see phpunit.xml) and a common local one —
     * has no FULLTEXT index at all, hence the driver guard described on
     * {@see self::FULL_TEXT_DRIVERS}: this migration is a no-op there. The
     * consequence is that `Product::applyNameSearch()` must keep a `LIKE` path
     * for those connections, which it does.
     *
     * Also guarded on `hasIndex()` rather than on the environment, so
     * re-running it against a database that already carries the index is a
     * no-op.
     */
    public function up(): void
    {
        if (! $this->supportsFullText() || ! Schema::hasTable('products') || Schema::hasIndex('products', self::INDEX_NAME)) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'ar_name'], self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Dropping an index touches no row data, so unlike the stand-in migrations
     * on this table there is nothing here to guard on the environment — the
     * driver and `hasIndex()` checks alone keep it a no-op wherever the index
     * was never created, sqlite included.
     */
    public function down(): void
    {
        if (! $this->supportsFullText() || ! Schema::hasTable('products') || ! Schema::hasIndex('products', self::INDEX_NAME)) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText(self::INDEX_NAME);
        });
    }

    /**
     * Whether this connection's grammar can compile FULLTEXT index DDL.
     */
    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), self::FULL_TEXT_DRIVERS, true);
    }
};

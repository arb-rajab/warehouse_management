<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every row belongs to a warehouse. Existing rows are moved into one
 * "Default" warehouse, created here (and reused when it already exists, so a
 * rollback and re-run backfills the same one). A row's letter is unique per
 * warehouse from now on, not across the whole WMS.
 *
 * The column is added nullable, backfilled, and only then made NOT NULL with
 * its foreign key, so the backfill never meets the constraint. The unique
 * index comes before the foreign key: on MySQL the key's leftmost column
 * (`warehouse_id`) then serves the foreign key, with no extra index.
 */
return new class extends Migration
{
    private const string DEFAULT_WAREHOUSE = 'Default';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultId = DB::table('warehouses')->where('name', self::DEFAULT_WAREHOUSE)->value('id')
            ?? DB::table('warehouses')->insertGetId([
                'name' => self::DEFAULT_WAREHOUSE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        Schema::table('rows', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('id');
        });

        DB::table('rows')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultId]);

        Schema::table('rows', function (Blueprint $table) {
            $table->dropUnique(['letter']);
            $table->unique(['warehouse_id', 'letter']);
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations. Restoring the global unique letter fails while
     * two warehouses share a letter; rename or delete one of them first. The
     * warehouses themselves stay (the create migration's down() drops them).
     */
    public function down(): void
    {
        Schema::table('rows', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'letter']);
            $table->unique('letter');
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};

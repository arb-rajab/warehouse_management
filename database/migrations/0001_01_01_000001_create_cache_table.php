<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `wms_` prefixed because the production database is shared with the
     * store app, which runs the database cache driver too — a shared cache
     * table means either app can read, overwrite or flush the other's
     * entries. See .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        Schema::create('wms_cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('wms_cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * `rename_colliding_tables_to_wms_prefix` runs later, so its `down()`
     * fires before this one during a full rollback and un-prefixes these
     * tables back to their legacy names wherever this app owns them. Dropping
     * only the `wms_` name here would then silently no-op and orphan the
     * legacy-named table, so each table is dropped under whichever name it
     * currently holds.
     */
    public function down(): void
    {
        $this->dropRenamable('wms_cache', 'cache');
        $this->dropRenamable('wms_cache_locks', 'cache_locks');
    }

    /**
     * Drops the `wms_`-prefixed table if it still exists under that name,
     * otherwise falls back to the legacy name a rename migration's `down()`
     * may have already restored it to.
     */
    private function dropRenamable(string $prefixed, string $legacy): void
    {
        if (Schema::hasTable($prefixed)) {
            Schema::drop($prefixed);
        } else {
            Schema::dropIfExists($legacy);
        }
    }
};

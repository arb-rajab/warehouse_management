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
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_cache');
        Schema::dropIfExists('wms_cache_locks');
    }
};

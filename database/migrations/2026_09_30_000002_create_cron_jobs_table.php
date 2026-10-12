<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `cron_jobs` table, created by its own
     * `2023_12_21_105343_create_cron_jobs_table` after the 2023 `shop.sql` dump
     * the store's other tables here reproduce, and never carried over: the
     * product import commands and the admin product fetch record their runs in
     * it.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('cron_jobs')) {
            return;
        }

        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('command')->nullable();
            $table->string('status')->nullable()->default('pending');
            $table->string('output')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('cron_jobs');
    }
};

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
     * store app, which runs the database queue driver too — a shared `jobs`
     * table means this app's workers reserve and fail the store's payloads
     * (whose job classes don't exist here) and vice versa. See
     * .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        Schema::create('wms_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('wms_job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('wms_failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
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
        $this->dropRenamable('wms_jobs', 'jobs');
        $this->dropRenamable('wms_job_batches', 'job_batches');
        $this->dropRenamable('wms_failed_jobs', 'failed_jobs');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CellStatusLogObserver::isRapidActions() counts one user's logs since a
     * timestamp on every log written, and a user's activity history pages
     * through their logs newest first — both filter on user_id and range or
     * sort on created_at, which the single-column user_id and created_at
     * indexes can each serve only half of.
     *
     * The composite leads with user_id, so it also backs the user_id foreign
     * key on its own, which makes `cell_status_logs_user_id_index` a genuine
     * duplicate from here on: it is dropped once the composite exists (and
     * drop_redundant_foreign_key_indexes would drop it on a re-run anyway).
     * Any MySQL-auto-created foreign key index is left alone.
     */
    public function up(): void
    {
        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        if (Schema::hasIndex('cell_status_logs', 'cell_status_logs_user_id_index')) {
            Schema::table('cell_status_logs', function (Blueprint $table) {
                $table->dropIndex('cell_status_logs_user_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * The single-column index goes back first, so the foreign key is never
     * left without an index covering it — MySQL refuses to drop the composite
     * otherwise.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('cell_status_logs', 'cell_status_logs_user_id_index')) {
            Schema::table('cell_status_logs', function (Blueprint $table) {
                $table->index('user_id', 'cell_status_logs_user_id_index');
            });
        }

        Schema::table('cell_status_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};

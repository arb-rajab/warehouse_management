<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users are soft-deleted rather than removed: `cell_status_logs`,
     * `cell_verification_rounds` and `cell_verification_reports` restrict the
     * deletion of a user they point at, so a hard delete could never remove
     * someone with recorded activity. A soft-deleted user keeps their row (and
     * so their history and name) but is skipped by every default `User` query,
     * which is what stops them signing in.
     */
    public function up(): void
    {
        Schema::table('wms_users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wms_users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

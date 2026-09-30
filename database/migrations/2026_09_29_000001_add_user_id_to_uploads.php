<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `Upload::user()` (merged from the legacy store's own Upload model, see
     * .ai/rules/legacy-albaraka-holland.md section 2) belongs-to `users` via
     * `user_id`. `create_uploads_table`'s own docblock deliberately kept the
     * stand-in to only the three columns this app read at the time, "so
     * nothing here can start depending on them" -- this relation is exactly
     * that dependency now existing, so the column joins the stand-in.
     * `users.id` is an `unsignedInteger` (`create_users_table`), so this
     * matches that rather than Laravel's default `bigint unsigned`.
     *
     * The `hasColumn()` guard is what makes this safe on the shared
     * database: in production `uploads` is the store's own table and
     * already carries `user_id`, so `up()` is a no-op there -- this app must
     * never alter a table it only reads.
     */
    public function up(): void
    {
        if (Schema::hasColumn('uploads', 'user_id')) {
            return;
        }

        Schema::table('uploads', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasColumn()`, which cannot
     * tell the store's `uploads` from a local stand-in -- a rollback must
     * drop the development copy's column and never a column of the store's
     * own.
     */
    public function down(): void
    {
        if (app()->isProduction() || ! Schema::hasColumn('uploads', 'user_id')) {
            return;
        }

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};

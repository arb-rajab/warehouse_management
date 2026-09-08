<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only renames a `users` table this app actually owns. Since
     * `create_users_table` now creates `wms_users` directly, this is a no-op
     * on a fresh install and fires only on a database migrated before that
     * change. The `! Schema::hasTable('wms_users')` half is what makes it
     * safe to run against the shared production database, where `users`
     * belongs to the store app and must never be renamed.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasTable('wms_users')) {
            Schema::rename('users', 'wms_users');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('wms_users') && ! Schema::hasTable('users')) {
            Schema::rename('wms_users', 'users');
        }
    }
};

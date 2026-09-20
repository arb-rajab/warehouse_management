<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These three tables are created under the `wms_` prefix rather than
     * Laravel's default names: in production this app shares one MySQL
     * database with the store app, which owns `users`, `sessions` and
     * `password_reset_tokens` of its own. Creating the default names there
     * would fail outright ("table already exists") on the first deploy, and
     * the later rename migration would then rename the store's table out
     * from under it. See .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        Schema::create('wms_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('wms_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('wms_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rolling back `rename_users_table_to_wms_users` and
     * `rename_colliding_tables_to_wms_prefix` first (they run later, so their
     * `down()` fires before this one's during a full rollback) un-prefixes
     * these tables back to their legacy names wherever this app owns them —
     * everywhere except a production database, where the guard on those
     * migrations' `down()` leaves the `wms_` names in place because the
     * legacy names belong to the store app. Dropping only the `wms_` name
     * here would then silently no-op and orphan the legacy-named table, so
     * each table is dropped under whichever name it currently holds.
     */
    public function down(): void
    {
        $this->dropRenamable('wms_users', 'users');
        $this->dropRenamable('wms_password_reset_tokens', 'password_reset_tokens');
        $this->dropRenamable('wms_sessions', 'sessions');
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

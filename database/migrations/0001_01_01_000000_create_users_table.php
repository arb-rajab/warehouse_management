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
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_users');
        Schema::dropIfExists('wms_password_reset_tokens');
        Schema::dropIfExists('wms_sessions');
    }
};

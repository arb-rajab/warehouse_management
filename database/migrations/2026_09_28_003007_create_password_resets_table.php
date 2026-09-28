<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is a second, separate password-reset-token table for the legacy
     * store's own `users` table — intentionally NOT merged into this app's
     * `wms_password_reset_tokens`, which resets `wms_users` via a different
     * guard/provider (see .ai/rules/shared-database.md and
     * .ai/rules/legacy-albaraka-holland.md §3). shop.sql declares no
     * primary key at all for this table, so `id` is deliberately absent.
     */
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email', 191)->index();
            $table->string('token', 191);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};

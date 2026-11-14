<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A small, generic audit log of sensitive store back-office actions
     * (AdminAuditLog): who did what, when and from where, never the values
     * or secrets involved. The SMTP settings page writes its saves
     * (`smtp_settings.updated`, with each changed field and how it changed) and
     * its refused current passwords (`smtp_settings.password_failed`).
     *
     * `user_id` is the store user's id (`users.id`, int unsigned) with no
     * foreign key on purpose: a row must outlive the account it names. The
     * name and email are a snapshot for the same reason. Rows are never
     * updated, so there is no `updated_at`.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('user_name', 255)->nullable();
            $table->string('user_email', 255)->nullable();
            $table->string('action', 100);
            $table->json('changed_fields');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

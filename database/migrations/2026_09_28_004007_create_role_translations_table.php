<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `role_id` refers to a row in the shared `wms_roles` table (Spatie
     * permissions), not a bare `roles` table — this app never creates one,
     * since the permission tables are shared under the `wms_` prefix (see
     * .ai/rules/legacy-albaraka-holland.md). No DB-level FK to `wms_roles`,
     * matching shop.sql exactly.
     */
    public function up(): void
    {
        if (Schema::hasTable('role_translations')) {
            return;
        }

        Schema::create('role_translations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('role_id');
            $table->string('name', 50);
            $table->string('lang', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
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

        Schema::dropIfExists('role_translations');
    }
};

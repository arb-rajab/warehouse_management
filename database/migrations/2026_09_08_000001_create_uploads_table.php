<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `uploads` is the second *shared* table, owned by the store app: it is
     * where `products.thumbnail_img` points, and the only way to turn that id
     * into a usable image URL. Like `products`, this app only ever reads it,
     * and this migration builds a local stand-in for development and testing
     * while no-opping wherever the real table already exists.
     *
     * The stand-in carries only the three columns this app reads, at the
     * store's own types — `id` is a signed `int(11)`, matching `products.id`.
     * The store's table has eleven columns; the rest are deliberately absent
     * so nothing here can start depending on them. See
     * .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('uploads')) {
            return;
        }

        Schema::create('uploads', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('file_name')->nullable();
            $table->string('external_link', 500)->nullable();
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

        Schema::dropIfExists('uploads');
    }
};

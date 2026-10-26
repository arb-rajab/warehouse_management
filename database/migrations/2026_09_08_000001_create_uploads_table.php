<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Initially a stand-in for the store's shared `uploads` table, where
     * `products.thumbnail_img` points. This create remains a no-op wherever
     * the table already exists. The integrated store now writes upload rows;
     * later migrations add user_id and metadata to this initial shape.
     *
     * The initial shape has id, file_name, external_link and timestamps, at
     * the store's types — id is a signed int(11), matching products.id. See
     * .ai/rules/shared-database.md for current consumers and write ownership.
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

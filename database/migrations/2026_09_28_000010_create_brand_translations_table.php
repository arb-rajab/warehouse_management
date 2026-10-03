<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `id`/`brand_id` are signed `bigint` in shop.sql (no UNSIGNED keyword) —
     * kept exact per the legacy dump.
     */
    public function up(): void
    {
        if (Schema::hasTable('brand_translations')) {
            return;
        }

        Schema::create('brand_translations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('brand_id');
            $table->string('name', 50);
            $table->string('lang', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('brand_translations');
    }
};

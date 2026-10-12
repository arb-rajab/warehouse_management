<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `id`/`attribute_id` are signed `bigint` in shop.sql (no UNSIGNED
     * keyword), unlike this table's sibling translation tables which mostly
     * use plain `int` — kept exact per the legacy dump.
     */
    public function up(): void
    {
        if (Schema::hasTable('attribute_translations')) {
            return;
        }

        Schema::create('attribute_translations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('attribute_id');
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

        Schema::dropIfExists('attribute_translations');
    }
};

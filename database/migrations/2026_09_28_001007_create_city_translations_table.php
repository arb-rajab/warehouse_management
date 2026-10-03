<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skipped when the table exists: the store's own database already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('city_translations')) {
            return;
        }

        Schema::create('city_translations', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('city_id');
            $table->string('name');
            $table->string('lang', 10);
            $table->timestamps();
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

        Schema::dropIfExists('city_translations');
    }
};

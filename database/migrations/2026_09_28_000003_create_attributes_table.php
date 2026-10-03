<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attributes')) {
            return;
        }

        Schema::create('attributes', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('attributes');
    }
};

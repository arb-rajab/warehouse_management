<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attribute_values')) {
            return;
        }

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('attribute_id');
            $table->string('value');
            $table->string('color_code', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('attribute_values');
    }
};

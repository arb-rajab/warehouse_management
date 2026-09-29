<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_translations', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('lang', 10)->nullable();
            $table->string('lang_key')->nullable();
            $table->string('lang_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_translations');
    }
};

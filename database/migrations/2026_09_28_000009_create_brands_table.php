<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brands')) {
            return;
        }

        Schema::create('brands', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name', 50);
            $table->string('logo', 100)->nullable();
            $table->integer('top')->default(0);
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('brands');
    }
};

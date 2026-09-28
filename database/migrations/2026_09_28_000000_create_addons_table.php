<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name')->nullable();
            $table->string('unique_identifier')->nullable();
            $table->string('version')->nullable();
            $table->integer('activated')->default(1);
            $table->string('image', 1000)->nullable();
            $table->string('purchase_code')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};

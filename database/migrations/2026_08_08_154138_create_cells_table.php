<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('rows')->cascadeOnDelete();
            $table->unsignedSmallInteger('cell_number');
            $table->unsignedSmallInteger('flat_number');
            $table->string('state')->default('empty');
            $table->timestamps();

            $table->unique(['row_id', 'cell_number', 'flat_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cells');
    }
};

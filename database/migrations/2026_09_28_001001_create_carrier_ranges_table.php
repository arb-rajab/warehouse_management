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
        Schema::create('carrier_ranges', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('carrier_id');
            $table->string('billing_type', 20);
            $table->decimal('delimiter1', 25, 2);
            $table->decimal('delimiter2', 25, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrier_ranges');
    }
};

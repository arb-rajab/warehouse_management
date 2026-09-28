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
        Schema::create('carrier_range_prices', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('carrier_id');
            $table->integer('carrier_range_id');
            $table->integer('zone_id');
            $table->double('price', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrier_range_prices');
    }
};

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
        Schema::create('commission_histories', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('order_id');
            $table->integer('order_detail_id');
            $table->integer('seller_id');
            $table->double('admin_commission', 25, 2);
            $table->double('seller_earning', 25, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_histories');
    }
};

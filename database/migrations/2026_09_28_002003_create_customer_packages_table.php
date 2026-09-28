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
        Schema::create('customer_packages', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name')->nullable();
            $table->double('amount', 20, 2)->nullable();
            $table->integer('product_upload')->nullable();
            $table->string('logo', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_packages');
    }
};

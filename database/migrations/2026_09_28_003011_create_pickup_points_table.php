<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skipped when the table exists: the store's own database already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('pickup_points')) {
            return;
        }

        Schema::create('pickup_points', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('staff_id');
            $table->string('name');
            $table->mediumText('address');
            $table->string('phone', 15);
            $table->integer('pick_up_status')->nullable();
            $table->integer('cash_on_pickup_status')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('pickup_points');
    }
};

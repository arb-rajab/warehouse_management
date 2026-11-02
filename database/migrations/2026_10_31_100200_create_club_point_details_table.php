<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The per-product lines of a club point row, converted into wallet balance by
     * `ClubpointController::convert_into_wallet`.
     *
     * Skipped when the table exists: the store's own database already has it.
     * No earlier migration created it, so a database built from migrations
     * alone lacked it and the code that reads it failed with SQLSTATE[42S02].
     */
    public function up(): void
    {
        if (Schema::hasTable('club_point_details')) {
            return;
        }

        Schema::create('club_point_details', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('club_point_id');
            $table->integer('product_id')->nullable();
            $table->integer('product_qty')->nullable();
            $table->decimal('point', 18, 2)->default('0.00');
            $table->decimal('converted_amount', 10, 2)->default('0.00');
            $table->integer('refunded')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('club_point_id');
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

        Schema::dropIfExists('club_point_details');
    }
};

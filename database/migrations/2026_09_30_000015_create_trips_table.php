<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `trips` table, created by its own
     * `2024_11_26_125533_create_trips_table` (plus `code` and `duration`, from
     * `2024_12_09_124342` and `2024_12_23_142904`) after the 2023 `shop.sql`
     * dump the store's other tables here reproduce, and never carried over: the
     * delivery module's trips.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('trips')) {
            return;
        }

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('active')->default(false);
            $table->enum('status', ['pending', 'started', 'closed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('distance')->nullable();
            $table->integer('duration')->nullable()->comment('in Seconds');
            $table->timestamps();
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

        Schema::dropIfExists('trips');
    }
};

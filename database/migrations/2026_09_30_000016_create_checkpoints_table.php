<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `checkpoints` table, created by its own
     * `2024_11_26_125547_create_checkpoints_table` (as `2024_12_15_165135`,
     * `2024_12_17_120931` and `2024_12_23_142904` left it: a `relationable`
     * morph in place of `order_id`/`customer_id`, a `type` of delivery or
     * other, and `eta` in seconds) after the 2023 `shop.sql` dump the store's
     * other tables here reproduce, and never carried over: a delivery trip's
     * stops.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('checkpoints')) {
            return;
        }

        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->morphs('relationable');
            $table->enum('type', ['delivery', 'other'])->default('delivery');
            $table->enum('status', ['pending', 'delivered', 'has_issue'])->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->json('address')->nullable();
            $table->string('longitudes')->nullable();
            $table->string('latitudes')->nullable();
            $table->integer('sort_order_in_trip');
            $table->integer('eta')->nullable()->comment('for estimated time of arrival at the checkpoint in Seconds');
            $table->text('notes')->nullable();
            $table->string('cmr_file')->nullable();
            $table->string('photos')->nullable();
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

        Schema::dropIfExists('checkpoints');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheckpointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('type', ['pickup','delivery','other'])->default('pickup');
            $table->enum('status', ['pending','delivered', 'has_issue'])->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->json('address')->nullable();
            $table->string('longitudes')->nullable();
            $table->string('latitudes')->nullable();
            $table->integer('sort_order_in_trip');
            $table->timestamp('eta')->nullable()->comment('for estimated time of arrival at the checkpoint.');
            $table->text('notes')->nullable();
            $table->string('cmr_file')->nullable();
            $table->string('photos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('checkpoints');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTripsTableAddDurationCol extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('duration')->after('distance')->nullable()->comment('in Seconds');
        });

        Schema::table('checkpoints', function (Blueprint $table) {
            $table->integer('eta')->change()->nullable()->comment('for estimated time of arrival at the checkpoint in Seconds');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
        Schema::table('checkpoints', function (Blueprint $table) {
            $table->timestamp('eta')->nullable()->comment('for estimated time of arrival at the checkpoint.');
        });
    }
}

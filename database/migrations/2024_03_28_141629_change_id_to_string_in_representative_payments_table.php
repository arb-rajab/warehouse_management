<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeIdToStringInRepresentativePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('representative_payments', function (Blueprint $table) {
            $table->dropPrimary('representative_payments_id_primary');

            $table->string('id')->primary()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('representative_payments', function (Blueprint $table) {
            $table->dropPrimary('id');

            $table->id()->primary()->change();
        });
    }
}

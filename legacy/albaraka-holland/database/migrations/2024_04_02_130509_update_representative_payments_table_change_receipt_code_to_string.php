<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRepresentativePaymentsTableChangeReceiptCodeToString extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('representative_payments', function (Blueprint $table) {
            $table->string('receipt_code')->change();
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
            $table->integer('receipt_code')->change();
        });
    }
}

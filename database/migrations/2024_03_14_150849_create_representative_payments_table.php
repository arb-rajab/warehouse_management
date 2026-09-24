<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepresentativePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('representative_payments', function (Blueprint $table) {
            $table->id();
            $table->double('amount' ,20, 2)->default(0);
            $table->integer('receipt_code');
            $table->integer('invoice_code')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('rep_id');
            $table->integer('admin_id')->nullable();
            $table->timestamp('date')->default(now());
            $table->integer('otajer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->enum('status',['accepted', 'rejected', 'pending'])->default('pending');


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
        Schema::dropIfExists('representative_payments');
    }
}

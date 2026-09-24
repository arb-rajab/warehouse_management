<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartsOfferedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carts_offered', function (Blueprint $table) {
            $table->id();
            $table->integer('owner_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('address_id');
            $table->integer('product_id')->nullable();
            $table->string('product_serial')->nullable();
            $table->text('variation')->nullable();
            $table->longText('variation_qty')->nullable();
            $table->double('price', 20, 2)->nullable();
            $table->double('tax', 20, 2)->nullable();
            $table->double('shipping_cost', 20, 2)->default(0);
            $table->string('shipping_type')->default('home_delivery');
            $table->integer('quantity')->default(0);
            $table->boolean('by_rep')->default(0);
            $table->integer('for_customer')->nullable();
            $table->string('item_notes')->nullable();
            $table->integer('offer_id')->nullable();
            $table->boolean('is_postponed')->default(0);

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
        Schema::dropIfExists('carts_offered');
    }
}

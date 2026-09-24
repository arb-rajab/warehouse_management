<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EditCartsAndOrdersTablesAddByRepAndForCustomerCols extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->boolean('by_rep')->default(0);
            $table->integer('for_customer')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('by_rep')->default(0);
            $table->integer('for_customer')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('by_rep');
            $table->dropColumn('for_customer');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('by_rep');
            $table->dropColumn('for_customer');
        });
    }
}

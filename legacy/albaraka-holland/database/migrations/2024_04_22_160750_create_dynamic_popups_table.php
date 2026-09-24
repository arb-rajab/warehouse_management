<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDynamicPopupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dynamic_popups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('status')->default(0);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('banner')->nullable();
            $table->string('btn_link')->nullable();
            $table->string('btn_text')->nullable();
            $table->string('btn_text_color')->nullable();
            $table->string('btn_background_color')->nullable();
            $table->integer('delay_seconds')->default(10);
            $table->integer('offer_id')->nullable();
            $table->integer('show_page')->default(0)->comment('0 for home page, any number else will represent category id of the desired category');
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
        Schema::dropIfExists('dynamic_popups');
    }
}

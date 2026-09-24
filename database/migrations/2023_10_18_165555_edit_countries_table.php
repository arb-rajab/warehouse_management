<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EditCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->boolean('priority')->default(0);

        });

        DB::table('countries')
            ->whereIn('id', [21, 75, 82, 155])
            ->update(['priority' => 1]);

        DB::table('countries')
            ->where('id', 155)
            ->update(['name' => 'Netherlands']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
}

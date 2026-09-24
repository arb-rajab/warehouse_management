<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('order_details')
            ->where('original_price', 0)
            ->where('is_price_changed', 0)
            ->update(['original_price' => DB::raw('price')]);
    }

    public function down() {
        // lorem ipsum
    }
};

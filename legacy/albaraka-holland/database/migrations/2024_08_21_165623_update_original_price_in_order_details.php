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
            ->where('is_price_changed', 1)
            ->where('quantity', '!=', 1)
            ->update(['original_price' => DB::raw('original_price * quantity')]);
    }

    public function down() {
        // lorem ipsum
    }
};

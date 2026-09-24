<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCheckpointsTypeEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE checkpoints MODIFY COLUMN type ENUM('delivery', 'other') DEFAULT 'delivery'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE checkpoints MODIFY COLUMN type ENUM('pending', 'delivery', 'other') DEFAULT 'pending'");
    }
}

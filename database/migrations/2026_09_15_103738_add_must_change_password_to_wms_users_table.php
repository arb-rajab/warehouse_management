<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Defaults to false so an existing installation's users aren't suddenly
     * forced through the change-password flow on deploy — only a freshly
     * created/reset account (see Admin\UserController) sets it true.
     */
    public function up(): void
    {
        Schema::table('wms_users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wms_users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};

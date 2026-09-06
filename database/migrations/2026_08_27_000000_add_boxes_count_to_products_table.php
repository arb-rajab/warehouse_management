<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('boxes_count')->default(1)->after('image_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping boxes_count discards any live operational data stored
     * in that column.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('boxes_count');
        });
    }
};

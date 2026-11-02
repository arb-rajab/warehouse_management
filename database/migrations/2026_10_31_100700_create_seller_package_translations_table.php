<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Translated names of a seller package (`SellerPackage::getTranslation`).
     *
     * Skipped when the table exists: the store's own database already has it.
     * No earlier migration created it, so a database built from migrations
     * alone lacked it and the code that reads it failed with SQLSTATE[42S02].
     */
    public function up(): void
    {
        if (Schema::hasTable('seller_package_translations')) {
            return;
        }

        Schema::create('seller_package_translations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('seller_package_id');
            $table->string('name', 50);
            $table->string('lang', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('seller_package_translations');
    }
};

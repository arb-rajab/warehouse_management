<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offline payment addon's methods table. It exists on the store's shared
     * database; this creates a stand-in where it is missing (a fresh database)
     * so GET /payment-types and the setup checklist do not hit a missing-table
     * error. Columns follow the Active eCommerce schema.
     */
    public function up(): void
    {
        if (Schema::hasTable('manual_payment_methods')) {
            return;
        }

        Schema::create('manual_payment_methods', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('type', 255)->nullable();
            $table->string('heading', 255)->nullable();
            $table->longText('description')->nullable();
            $table->longText('bank_info')->nullable();
            $table->integer('photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Guarded on the environment, like create_brands_table: in production the
     * table belongs to the store.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('manual_payment_methods');
    }
};

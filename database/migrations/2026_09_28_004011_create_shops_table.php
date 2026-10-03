<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Skipped when the table exists: the store's own database already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('shops')) {
            return;
        }

        Schema::create('shops', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->string('name', 200)->nullable();
            $table->string('logo')->nullable();
            $table->longText('sliders')->nullable();
            $table->string('phone')->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('rating', 3, 2)->default('0.00');
            $table->integer('num_of_reviews')->default('0');
            $table->integer('num_of_sale')->default('0');
            $table->integer('seller_package_id')->nullable();
            $table->integer('product_upload_limit')->default('0');
            $table->date('package_invalid_at')->nullable();
            $table->integer('verification_status')->default('0');
            $table->longText('verification_info')->nullable();
            $table->integer('cash_on_delivery_status')->default('0');
            $table->decimal('admin_to_pay', 20, 2)->default('0.00');
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('google')->nullable();
            $table->string('twitter')->nullable();
            $table->string('youtube')->nullable();
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('pick_up_point_id')->nullable();
            $table->decimal('shipping_cost', 20, 2)->default('0.00');
            $table->decimal('delivery_pickup_latitude', 17, 15)->nullable();
            $table->decimal('delivery_pickup_longitude', 17, 15)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_acc_name', 200)->nullable();
            $table->string('bank_acc_no', 50)->nullable();
            $table->integer('bank_routing_no')->nullable();
            $table->integer('bank_payment_status')->default('0');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
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

        Schema::dropIfExists('shops');
    }
};

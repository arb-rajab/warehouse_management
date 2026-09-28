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
        Schema::create('categories', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('parent_id')->nullable()->default(0);
            $table->integer('level')->default(0);
            $table->string('name', 50);
            $table->integer('order_level')->default(0);
            $table->double('commision_rate', 8, 2)->default(0.00);
            $table->string('banner', 100)->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('featured')->default(0);
            $table->integer('top')->default(0);
            $table->integer('digital')->default(0);
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('created_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('updated_at')->nullable()->useCurrent();

            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

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
        Schema::create('firebase_notifications', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('title')->nullable();
            $table->text('text')->nullable();
            $table->string('item_type');
            $table->integer('item_type_id');
            $table->integer('receiver_id');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firebase_notifications');
    }
};

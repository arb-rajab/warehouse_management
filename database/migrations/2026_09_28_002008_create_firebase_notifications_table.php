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
        if (Schema::hasTable('firebase_notifications')) {
            return;
        }

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
     *
     * Guarded on the environment rather than on `hasTable()`, which cannot
     * tell the store's table from a local stand-in.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::dropIfExists('firebase_notifications');
    }
};

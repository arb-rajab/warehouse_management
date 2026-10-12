<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's `user_page_views` table, created by its own
     * `2024_09_22_155814_create_user_page_views_table` after the 2023
     * `shop.sql` dump the store's other tables here reproduce, and never
     * carried over: the pages a signed-in customer has viewed.
     *
     * `user_id` matches `users.id`, an unsigned `int`.
     *
     * Guarded like `create_offers_catalogs_table`: the store's own database,
     * which already has this table, is where phase 5 of
     * .ai/rules/legacy-albaraka-holland.md puts the merged app.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_page_views')) {
            return;
        }

        Schema::create('user_page_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->morphs('viewable');
            $table->timestamp('viewed_at');
            $table->timestamps();
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

        Schema::dropIfExists('user_page_views');
    }
};

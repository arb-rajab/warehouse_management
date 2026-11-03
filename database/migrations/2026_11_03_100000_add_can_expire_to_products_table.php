<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `products.can_expire` marks a perishable product (Labneh, for example)
     * that can expire soon. It is set by the store admin form only: the Otajer
     * sync never writes it, so it is not in `config/product_field_sources.php`
     * and every row keeps the value the admin gave it. NOT NULL with a default
     * of false, so existing rows and the legacy writers that insert without it
     * stay valid, and sqlite accepts the ALTER. Skipped when the column exists.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'can_expire')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('can_expire')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('products', 'can_expire')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('can_expire');
        });
    }
};

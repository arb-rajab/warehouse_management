<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `order_details.tax` holds the product's VAT percent in every writer (the
     * checkout, the offers, the back office entry), so it cannot also be the
     * money a customer pays. The app API adds VAT on top of the line price and
     * records the amount here; older rows and the other writers leave it 0.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('order_details', 'tax_amount')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->decimal('tax_amount', 20, 2)->default('0.00')->after('tax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_details', 'tax_amount')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('tax_amount');
            });
        }
    }
};

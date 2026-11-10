<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a pallet to the receipt it arrived on, so a pallet without a cell
 * still reaches its warehouse (through receipts.warehouse_id). There is
 * deliberately no `pallets.warehouse_id`: a placed pallet reaches its
 * warehouse through its cell's row, and a stored copy would go stale when a
 * transfer moves the pallet to another warehouse.
 *
 * `restrictOnDelete`, not `nullOnDelete`: deleting the receipt would null the
 * link and leave an unplaced pallet with neither a cell nor a receipt, i.e.
 * in no warehouse at all. Restricting keeps every unplaced
 * pallet resolvable and makes a finalized receipt undeletable while its
 * pallets exist.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->after('cell_id')->constrained('receipts')->restrictOnDelete();
            $table->index('receipt_id');
        });
    }

    /**
     * Reverse the migrations. Lossy: unplaced pallets lose the link to their
     * receipt.
     */
    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropForeign(['receipt_id']);
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropIndex(['receipt_id']);
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('receipt_id');
        });
    }
};

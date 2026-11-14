<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** WMS rows read per window of ids. */
    private const int CHUNK = 500;

    /**
     * Run the migrations.
     *
     * `product_barcodes` becomes the one barcode table (App\Models\ProductBarcode
     * now reads it), so the barcodes admins added in the WMS, held until now in
     * `wms_product_barcodes`, are copied across with their product and
     * timestamps. Only rows are inserted; no existing row of either table is
     * ever changed or deleted, and `wms_product_barcodes` stays in place,
     * unused, for one release.
     *
     * A WMS row is skipped when:
     * - its value already exists in `product_barcodes`, on the same product
     *   (nothing to copy) or on another one (copying it would let one scan
     *   match two products); `products:barcode-conflicts` lists the second
     *   case, recomputed from both tables at any time;
     * - its product no longer exists (the copy joins `products`).
     *
     * The window walks the WMS ids in chunks of 500, so memory and lock time
     * stay small whatever the table's size, and each chunk commits on its own.
     * Re-running it inserts nothing, since every copied value then exists: a
     * run that stopped halfway resumes on the next `migrate`.
     *
     * Collation: on MySQL the two `barcode` columns may carry different
     * collations in production (the inherited store table's is not known
     * here), and comparing one column with the other in SQL would then fail
     * with "Illegal mix of collations". So no column is compared with another:
     * each WMS value is looked up in `product_barcodes` as a bound value, which
     * MySQL compares under `product_barcodes.barcode`'s own collation, exactly
     * as the application's uniqueness check (App\Rules\NewProductBarcode) and
     * the scan lookup do. Under a case-insensitive collation "ABC" therefore
     * counts as already present when "abc" is. Values are checked one at a
     * time, after the previous row of the chunk was inserted, so two WMS
     * values that are equal only under that collation are copied once. Sqlite
     * compares exactly.
     */
    public function up(): void
    {
        // Both create migrations guard on hasTable(): no-op wherever either table is missing.
        if (! Schema::hasTable('wms_product_barcodes') || ! Schema::hasTable('product_barcodes')) {
            return;
        }

        $counts = ['copied' => 0, 'already_present' => 0, 'product_missing' => 0];
        $lastId = 0;

        while (($ids = DB::table('wms_product_barcodes')->where('id', '>', $lastId)->orderBy('id')->limit(self::CHUNK)->pluck('id'))->isNotEmpty()) {
            $rows = DB::table('wms_product_barcodes as w')
                ->join('products as p', 'p.id', '=', 'w.product_id')
                ->whereIn('w.id', $ids->all())
                ->orderBy('w.id')
                ->get(['w.product_id', 'w.barcode', 'w.created_at', 'w.updated_at']);
            $counts['product_missing'] += $ids->count() - $rows->count();

            DB::transaction(function () use ($rows, &$counts): void {
                foreach ($rows as $row) {
                    if (DB::table('product_barcodes')->where('barcode', $row->barcode)->exists()) {
                        $counts['already_present']++;

                        continue;
                    }

                    DB::table('product_barcodes')->insert([
                        'product_id' => $row->product_id,
                        'barcode' => $row->barcode,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? $row->created_at ?? now(),
                    ]);
                    $counts['copied']++;
                }
            });

            $lastId = (int) $ids->last();
        }

        Log::info('Copied WMS barcodes into product_barcodes', $counts);
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op. A copied row cannot be told apart from the same
     * value added again afterwards on either form, so deleting by value could
     * remove a barcode a user added or kept on purpose. Nothing is lost by
     * keeping them: the originals are still in `wms_product_barcodes`, which
     * the follow-up release drops.
     */
    public function down(): void {}
};

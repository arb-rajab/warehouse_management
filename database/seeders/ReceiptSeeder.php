<?php

namespace Database\Seeders;

use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PalletActionService;
use App\Services\ReceiptService;
use Database\Seeders\Concerns\RefusesProduction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ReceiptSeeder extends Seeder
{
    use RefusesProduction;

    /** Draft and finalized receipts seeded per warehouse. */
    private const int DRAFTS_PER_WAREHOUSE = 2;

    private const int FINALIZED_PER_WAREHOUSE = 2;

    /**
     * Seed goods-receiving documents, per warehouse, through the same services
     * the mobile API uses so the data obeys the same invariants: drafts with
     * their pallet entries (and sometimes a bill file), finalized receipts
     * (created, given bill files, then finalized by ReceiptService, which
     * creates the unplaced pallets and freezes them), and, where the warehouse
     * has free cells, all but the last pallet of the first finalized receipt
     * placed by PalletActionService::place() (cell Full or Opened, pallet
     * cell_id set and a `stored` cell log). Every other finalized pallet stays
     * unplaced, so the unplaced pallets list has data.
     *
     * Refuses production (it writes demo data under an existing user). Not
     * idempotent by design: it early-returns when any receipt exists, so a
     * second run adds nothing instead of piling up receipts.
     *
     * Bill files are factory rows only: no bytes are written, so downloading
     * one answers 404 (as for any record whose bytes are gone).
     */
    public function run(): void
    {
        $this->refuseInProduction();

        $products = Product::query()->with('setting')->inRandomOrder()->limit(40)->get();
        $warehouses = Warehouse::query()->orderBy('id')->get();
        $user = User::query()->orderBy('id')->first();

        if ($products->isEmpty() || $warehouses->isEmpty() || $user === null || Receipt::query()->exists()) {
            return;
        }

        $receipts = app(ReceiptService::class);
        $placing = app(PalletActionService::class);

        foreach ($warehouses as $warehouse) {
            $cells = $this->freeCells($warehouse);

            foreach (range(1, self::DRAFTS_PER_WAREHOUSE) as $i) {
                $receipt = $receipts->create(
                    $user->id,
                    $warehouse->id,
                    $i === 1 ? 'Demo: truck still being counted.' : null,
                    $this->entries($products),
                );

                // The first draft already has its bill photographed.
                if ($i === 1) {
                    ReceiptFile::factory()->create(['receipt_id' => $receipt->id]);
                }
            }

            foreach (range(1, self::FINALIZED_PER_WAREHOUSE) as $i) {
                $receipt = $receipts->create(
                    $user->id,
                    $warehouse->id,
                    $i === 1 ? 'Demo: received and partly shelved.' : 'Demo: received, waiting to be shelved.',
                    $this->entries($products),
                );

                ReceiptFile::factory()->create(['receipt_id' => $receipt->id]);
                ReceiptFile::factory()->pdf()->create(['receipt_id' => $receipt->id]);

                $receipts->finalize($receipt, $user->id);

                if ($i === 1) {
                    $this->place($placing, $receipt, $cells, $user);
                }
            }
        }
    }

    /**
     * Two to four pallets as written on a bill, a few of which cannot expire.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array{product_id: int, boxes: int, expiration_date: string|null}>
     */
    private function entries(Collection $products): array
    {
        $entries = [];

        foreach (range(1, fake()->numberBetween(2, 4)) as $unused) {
            $product = $products->random();

            $entries[] = [
                'product_id' => $product->id,
                'boxes' => fake()->numberBetween(1, max(1, (int) $product->boxes_count)),
                'expiration_date' => fake()->boolean(85)
                    ? fake()->dateTimeBetween('+10 days', '+1 year')->format('Y-m-d')
                    : null,
            ];
        }

        return $entries;
    }

    /**
     * Active, empty cells of the warehouse that hold no pallet, in random order.
     *
     * @return Collection<int, Cell>
     */
    private function freeCells(Warehouse $warehouse): Collection
    {
        return Cell::query()
            ->whereHas('row', fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->where('is_active', true)
            ->where('state', CellState::Empty)
            ->doesntHave('pallet')
            ->inRandomOrder()
            ->limit(20)
            ->get();
    }

    /**
     * Place every pallet of the receipt but the last into a free cell of its
     * warehouse. A refused placement (cell under verification, ...) leaves the
     * pallet unplaced, which is a valid state.
     *
     * @param  Collection<int, Cell>  $cells
     */
    private function place(PalletActionService $placing, Receipt $receipt, Collection $cells, User $user): void
    {
        $pallets = Pallet::query()->where('receipt_id', $receipt->id)->orderBy('id')->get();

        foreach ($pallets->slice(0, -1) as $pallet) {
            while (($cell = $cells->shift()) !== null) {
                try {
                    $placing->place($pallet, $cell->id, $user->id, 'Demo: placed by seeder.');

                    break;
                } catch (InvalidSlotStateException) {
                    continue;
                }
            }
        }
    }
}

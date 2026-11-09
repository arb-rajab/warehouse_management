<?php

namespace App\Models;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Observers\PalletObserver;
use Carbon\CarbonImmutable;
use Database\Factories\PalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $cell_id null while the pallet is received but not yet placed in a cell
 * @property int|null $receipt_id the truck receipt the pallet arrived on, if it came from one
 * @property ?Carbon $expiration_date
 * @property int $remaining_boxes
 * @property-read Cell|null $cell
 * @property-read Receipt|null $receipt
 * @property-read Warehouse|null $warehouse
 * @property-read CellState|null $state null while the pallet is unplaced
 * @property-read CellStatusLog|null $cellEnteredLog
 * @property-read CarbonImmutable|null $cell_entered_at
 */
#[Fillable(['product_id', 'cell_id', 'receipt_id', 'expiration_date', 'remaining_boxes'])]
#[ObservedBy(PalletObserver::class)]
class Pallet extends Model
{
    /** @use HasFactory<PalletFactory> */
    use HasFactory;

    /**
     * The eager loads `warehouse` reads for an unplaced pallet (its receipt's
     * warehouse); a placed one reads `cell.row.warehouse`.
     *
     * @var list<string>
     */
    public const array WITH_RECEIPT_WAREHOUSE = [
        'receipt:id,warehouse_id',
        'receipt.warehouse:id,name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
        ];
    }

    /**
     * Identifies the pallet's current stock state for `If-Match` / `ETag`: what
     * a worker acts on (product, location, level, expiry, boxes left). The
     * cell's state is included because opening a pallet changes only that; an
     * unplaced pallet has no cell, so its ETag reads no relation, and placing
     * it changes `cell_id` and therefore the ETag.
     */
    public function etag(): string
    {
        return md5(implode('|', [
            $this->id,
            $this->product_id,
            $this->cell_id,
            $this->expiration_date?->toDateString(),
            $this->remaining_boxes,
            $this->state?->value,
            $this->updated_at?->getTimestamp(),
        ]));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Cell, $this>
     */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    /**
     * The truck receipt the pallet arrived on, set for pallets created by
     * finalizing a receipt (it stays after the pallet is placed).
     *
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * The warehouse the pallet is in. There is no stored column: a placed
     * pallet is in its cell's row's warehouse (a transfer between warehouses
     * therefore moves it with no copy to update), an unplaced one in the
     * warehouse of its receipt. Eager load `cell.row.warehouse` and
     * `receipt.warehouse` to avoid a query per pallet; null only for a
     * pallet that has neither a cell nor a receipt.
     *
     * @return Attribute<Warehouse|null, never>
     */
    protected function warehouse(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Warehouse => $this->cell_id !== null
                ? $this->cell?->row->warehouse
                : $this->receipt?->warehouse,
        );
    }

    /**
     * The pallet's state is read through its current cell — there is no stored
     * column here. Null for an unplaced pallet, without touching the relation.
     *
     * @return Attribute<CellState|null, never>
     */
    protected function state(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CellState => $this->cell_id === null ? null : $this->cell->state,
        );
    }

    /**
     * Whether the relations `warehouse` reads for this pallet are loaded:
     * `cell.row.warehouse` for a placed pallet, `receipt.warehouse` (see
     * WITH_RECEIPT_WAREHOUSE) for an unplaced one. Resources show
     * `warehouse` only then, so they never lazy load it.
     */
    public function warehouseLoaded(): bool
    {
        if ($this->cell_id !== null) {
            return $this->relationLoaded('cell') && $this->cell !== null
                && $this->cell->relationLoaded('row') && $this->cell->row->relationLoaded('warehouse');
        }

        return $this->relationLoaded('receipt')
            && ($this->receipt === null || $this->receipt->relationLoaded('warehouse'));
    }

    /**
     * The `stored`/`transferred_in` log written when this pallet last arrived in
     * whichever cell it currently occupies. A pallet's `cell_id` only ever changes
     * via `PalletActionService::transfer()`, which always writes a fresh
     * `TransferredIn` log at the same moment — and `store()` and `place()` (an
     * unplaced pallet's first cell) always write a `Stored` log — so the newest
     * log of either action for this pallet is always the arrival into its
     * *current* cell, with no need to also match on `cell_id`. Null while unplaced.
     *
     * @return HasOne<CellStatusLog, $this>
     */
    public function cellEnteredLog(): HasOne
    {
        return $this->hasOne(CellStatusLog::class)
            ->ofMany(['created_at' => 'max'], function (Builder $query): void {
                $query->whereIn('action', [CellLogAction::Stored->value, CellLogAction::TransferredIn->value]);
            });
    }

    /**
     * When this pallet last arrived in the cell it currently occupies — unlike
     * `created_at` (original stock-in date, never changes), this moves forward
     * on every transfer. Requires `cellEnteredLog` to be eager-loaded.
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function cellEnteredAt(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CarbonImmutable => $this->cellEnteredLog?->created_at,
        );
    }

    /**
     * Whether this pallet has been stored longer than the given number of days. There is
     * no fixed "stale" threshold — the caller (admin UI or API client) always supplies it.
     */
    public function isStaleAfter(int $days): bool
    {
        return $this->created_at->lte(now()->subDays($days));
    }

    /**
     * Scope a query by the highlight-filter query params (the "matching
     * pallets" list's). It must stay in lockstep with the frontend's
     * `matchesCellHighlight()`/`isCellExpired()`/`isCellExpiringWithin()`/
     * `isCellStale()`. `state`/`is_active` live on the pallet's `Cell`, not on
     * the pallet itself, so those two go through `whereHas('cell', ...)`; the
     * rest are plain columns on this table.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function matchingHighlightFilters(Builder $query, Request $request): void
    {
        $today = today();

        $query
            ->when($request->filled('state'), fn (Builder $q) => $q->whereHas('cell', fn (Builder $c) => $c->where('state', $request->string('state')->value())))
            ->when($request->filled('is_active') && ! $request->boolean('is_active'), fn (Builder $q) => $q->whereHas('cell', fn (Builder $c) => $c->where('is_active', false)))
            ->when($request->boolean('expired'), fn (Builder $q) => $q->where('expiration_date', '<', $today))
            ->when($request->filled('expires_within_days'), fn (Builder $q) => $q->whereBetween('expiration_date', [$today, $today->copy()->addDays($request->integer('expires_within_days'))]))
            ->when($request->filled('stale_after_days'), fn (Builder $q) => $q->where('created_at', '<=', $today->copy()->subDays($request->integer('stale_after_days'))))
            ->when($request->filled('product_id'), fn (Builder $q) => $q->whereIn('product_id', array_map('intval', $request->array('product_id'))));
    }

    /**
     * Scope a query to the pallets in the given warehouses; null matches every
     * pallet. A placed pallet matches through its cell's row, an unplaced one
     * (no cell) through its receipt's warehouse — never both, so a received
     * pallet transferred to another warehouse follows its cell. See
     * {@see Row::forWarehouses()}.
     *
     * @param  Builder<Pallet>  $query
     * @param  list<int>|null  $warehouseIds
     */
    #[Scope]
    protected function forWarehouses(Builder $query, ?array $warehouseIds): void
    {
        if ($warehouseIds !== null) {
            $query->where(fn (Builder $q) => $q
                ->whereIn($query->qualifyColumn('cell_id'), Cell::query()->select('id')->forWarehouses($warehouseIds))
                ->orWhere(fn (Builder $unplaced) => $unplaced
                    ->whereNull($query->qualifyColumn('cell_id'))
                    ->whereIn($query->qualifyColumn('receipt_id'), Receipt::query()->select('id')->forWarehouses($warehouseIds))));
        }
    }

    /**
     * Scope a query to the pallets that sit in a cell, leaving out the
     * received but unplaced ones (`cell_id` null).
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function placed(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('cell_id'));
    }

    /**
     * Scope a query to the received pallets that have no cell yet.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function unplaced(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('cell_id'));
    }

    /**
     * Scope a query by the admin products index's occupancy filters
     * (state/expired/expires_within_days), optionally pinned to one cell
     * state. Shared by `Product::matchingOccupancyFilters()` (which products
     * appear at all) and `Product::withOccupancyCounts()` (the per-column
     * counts, which pass `$cellState` for the Full/Opened columns).
     *
     * Note `expires_within_days` here is `expiration_date <= now + N`, which
     * also matches already-expired pallets — unlike
     * {@see matchingHighlightFilters()}, which only matches today through
     * today + N. That divergence predates this scope and is kept as-is.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function matchingOccupancyFilters(Builder $query, Request $request, ?CellState $cellState = null): void
    {
        $query
            ->when($request->boolean('expired'), fn (Builder $q) => $q->where('expiration_date', '<', today()))
            ->when($request->filled('expires_within_days'), fn (Builder $q) => $q->where('expiration_date', '<=', now()->addDays($request->integer('expires_within_days'))))
            ->whereHas('cell', function (Builder $cellQuery) use ($request, $cellState): void {
                $cellQuery->when($request->filled('state'), fn (Builder $q) => $q->where('state', $request->string('state')->value()));

                if ($cellState !== null) {
                    $cellQuery->where('state', $cellState->value);
                }
            });
    }

    /**
     * The "matching pallets" list's row query: the columns and relations
     * `MatchingPalletResource` reads, in the list's canonical order (see
     * `orderedByExpirationThenAdded()`). Shared by the admin pallets page and
     * the dashboard's selected-product pallet list so both always show the
     * same rows in the same order.
     *
     * Unplaced pallets are left out: every row links to its place on the
     * warehouse map, and the cell-based filters (state, inactive) cannot
     * apply to them. The mobile `GET pallets/unplaced` lists them.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function listedForAdmin(Builder $query): void
    {
        $query
            ->placed()
            ->select(['id', 'product_id', 'cell_id', 'expiration_date', 'remaining_boxes', 'created_at'])
            ->with([
                'product:id,name,ar_name,thumbnail_img,warehouse_image_path,published',
                'product.thumbnailUpload:id,file_name,external_link',
                'product.setting:product_id,boxes_count',
                'cell:id,row_id,cell_number,flat_number,state,is_active',
                'cell.row:id,warehouse_id,letter',
                'cell.row.warehouse:id,name',
            ])
            ->orderedByExpirationThenAdded();
    }

    /**
     * Order a query of pallets by `expiration_date` ascending (a null
     * expiration sorts last, not first — the database default for ASC —
     * since "no expiration date" isn't the most urgent case), then
     * `created_at` ascending as the tiebreaker. Used by the "matching
     * pallets" admin list; the `IS NULL` ordering expression works
     * identically on both MySQL and SQLite.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function orderedByExpirationThenAdded(Builder $query): void
    {
        $query->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('created_at');
    }

    /**
     * The pallet fields shared by every listing that shows a pallet on its own
     * row — not embedded in a cell's map summary (see `toMapSummaryArray()` for
     * that shape). `ProductPalletResource` (a product's own pallet history) and
     * `MatchingPalletResource` (the "matching pallets" list a dashboard tile
     * links to) both build on this, so `state`/`expiration_date`/`added_at`
     * can't be derived one way in one listing and another way in the other.
     *
     * `state` is null for an unplaced pallet (no cell).
     *
     * @return array{id: int, state: string|null, expiration_date: string|null, added_at: string|null, remaining_boxes: int}
     */
    public function toListingSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state?->value,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'added_at' => $this->created_at?->toIso8601String(),
            'remaining_boxes' => $this->remaining_boxes,
        ];
    }

    /**
     * The pallet fields a CellResource entry shows (a row's cells and the
     * warehouse map's rows payload).
     *
     * @return array{product_id: int, product_name: string, product_ar_name: string, product_active: bool, product_image_url: string|null, expiration_date: string|null, added_at: string|null, cell_entered_at: string|null}
     */
    public function toMapSummaryArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product->name,
            'product_ar_name' => $this->product->ar_name,
            'product_active' => $this->product->published,
            'product_image_url' => $this->product->image_url,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'added_at' => $this->created_at?->toIso8601String(),
            'cell_entered_at' => $this->cell_entered_at?->toIso8601String(),
        ];
    }
}

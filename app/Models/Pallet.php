<?php

namespace App\Models;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Observers\PalletObserver;
use Carbon\CarbonImmutable;
use Database\Factories\PalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $cell_id
 * @property Carbon $expiration_date
 * @property int $remaining_boxes
 * @property-read CellState $state
 * @property-read CellStatusLog|null $cellEnteredLog
 * @property-read CarbonImmutable|null $cell_entered_at
 */
#[Fillable(['product_id', 'cell_id', 'expiration_date', 'remaining_boxes'])]
#[ObservedBy(PalletObserver::class)]
class Pallet extends Model
{
    /** @use HasFactory<PalletFactory> */
    use HasFactory;

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
     * The pallet's state is read through its current cell — there is no stored column here.
     *
     * @return Attribute<CellState, never>
     */
    protected function state(): Attribute
    {
        return Attribute::make(
            get: fn (): CellState => $this->cell->state,
        );
    }

    /**
     * The `stored`/`transferred_in` log written when this pallet last arrived in
     * whichever cell it currently occupies. A pallet's `cell_id` only ever changes
     * via `PalletActionService::transfer()`, which always writes a fresh
     * `TransferredIn` log at the same moment — and `store()` always writes a
     * `Stored` log — so the newest log of either action for this pallet is always
     * the arrival into its *current* cell, with no need to also match on `cell_id`.
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
     * The pallet fields shown on the admin cell map — its own CellResource entry and the
     * warehouse-wide cellHighlightSamples() summary both build on this shared shape so a
     * field can't be renamed/dropped in one without the other.
     *
     * @return array{product_id: int, product_name: string, product_ar_name: string, product_active: bool, product_image_url: string|null, expiration_date: string, added_at: string|null, cell_entered_at: string|null}
     */
    public function toMapSummaryArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product->name,
            'product_ar_name' => $this->product->ar_name,
            'product_active' => $this->product->published,
            'product_image_url' => $this->product->image_url,
            'expiration_date' => $this->expiration_date->toDateString(),
            'added_at' => $this->created_at?->toIso8601String(),
            'cell_entered_at' => $this->cell_entered_at?->toIso8601String(),
        ];
    }
}

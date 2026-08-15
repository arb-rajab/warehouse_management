<?php

namespace App\Models;

use App\Enums\CellState;
use Database\Factories\PalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $cell_id
 * @property Carbon $expiration_date
 * @property-read CellState $state
 * @property-read bool $is_stale
 */
#[Fillable(['product_id', 'cell_id', 'expiration_date'])]
class Pallet extends Model
{
    /** @use HasFactory<PalletFactory> */
    use HasFactory;

    /**
     * A pallet is flagged as stale once it's been stored longer than this, regardless
     * of whether its cell is currently full or opened. Used by the admin UI and
     * dashboard; the mobile API uses the caller-supplied {@see self::isStaleAfter()} instead.
     */
    public const int STALE_AFTER_DAYS = 3;

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
     * Whether this pallet has been stored longer than {@see self::STALE_AFTER_DAYS}.
     *
     * @return Attribute<bool, never>
     */
    protected function isStale(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->created_at->lte(now()->subDays(self::STALE_AFTER_DAYS)),
        );
    }

    /**
     * Scope a query to pallets stored longer than {@see self::STALE_AFTER_DAYS}.
     *
     * @param  Builder<Pallet>  $query
     */
    #[Scope]
    protected function stale(Builder $query): void
    {
        $query->where('created_at', '<=', now()->subDays(self::STALE_AFTER_DAYS));
    }

    /**
     * Whether this pallet has been stored longer than the given number of days —
     * used by the mobile API, which has no fixed threshold and always supplies
     * its own day count (unlike the admin UI/dashboard's {@see self::STALE_AFTER_DAYS}).
     */
    public function isStaleAfter(int $days): bool
    {
        return $this->created_at->lte(now()->subDays($days));
    }
}

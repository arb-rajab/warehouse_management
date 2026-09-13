<?php

namespace App\Models;

use App\Observers\RowObserver;
use Database\Factories\RowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $letter
 * @property int $cells_count
 * @property int $flats_count
 */
#[Fillable(['letter', 'cells_count', 'flats_count'])]
#[ObservedBy(RowObserver::class)]
#[RouteKey('letter')]
class Row extends Model
{
    /** @use HasFactory<RowFactory> */
    use HasFactory;

    /**
     * Columns needed by RowResource — shared by every listing (admin and API).
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = ['id', 'letter', 'cells_count', 'flats_count'];

    /**
     * Every row's id/letter/cells_count/flats_count, ordered by letter — the
     * cell-map's row list (flat-tab layout, transfer/pallet-action destination
     * dropdown), shared by CellController::index() and RowController::show()
     * so both render the same set of rows a pallet can be transferred into.
     *
     * @return Collection<int, self>
     */
    public static function mapOptions(): Collection
    {
        return self::query()->select(self::SELECT_COLUMNS)->orderBy('letter')->get();
    }

    /**
     * @return HasMany<Cell, $this>
     */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    /**
     * @return BelongsToMany<CellVerificationRound, $this>
     */
    public function verificationRounds(): BelongsToMany
    {
        return $this->belongsToMany(CellVerificationRound::class);
    }

    /**
     * Scope a query to rows an unfinished verification round currently claims.
     * This is the single definition of "frozen for counting", read both when a
     * new round is proposed (CellVerificationService::startRound() refuses one
     * that overlaps) and on every pallet action
     * (PalletActionService::lockCell() refuses to touch such a cell).
     *
     * `whereNull('completed_at')` is inlined rather than calling
     * `CellVerificationRound::unfinished()`: a model-specific `#[Scope]` called
     * inside a `whereHas()` closure breaks Larastan's generic resolution — see
     * .ai/rules/models.md.
     *
     * @param  Builder<Row>  $query
     */
    #[Scope]
    protected function underActiveVerification(Builder $query): void
    {
        $query->whereHas('verificationRounds', fn (Builder $roundQuery) => $roundQuery->whereNull('completed_at'));
    }

    /**
     * Adds the `has_pallets` exists-subquery RowResource emits, as part of the
     * row query rather than one `hasPallets()` query per row. Every listing
     * that builds a RowResource must use this (or `loadHasPallets()` for an
     * already-bound model) — the resource has no fallback of its own, so that
     * a missing subquery surfaces as an obvious error rather than silently
     * costing a query per row.
     *
     * @param  Builder<Row>  $query
     */
    #[Scope]
    protected function withHasPallets(Builder $query): void
    {
        $query->withExists(['cells as has_pallets' => fn ($cells) => $cells->has('pallet')]);
    }

    /**
     * `withHasPallets()` for a model that's already been resolved — route-model
     * binding hands the controller a Row it didn't query for itself.
     */
    public function loadHasPallets(): static
    {
        $this->loadExists(['cells as has_pallets' => fn ($cells) => $cells->has('pallet')]);

        return $this;
    }

    /**
     * Whether any of this row's cells currently hold a pallet.
     */
    public function hasPallets(): bool
    {
        return $this->cells()->whereHas('pallet')->exists();
    }

    /**
     * The rows + max column number shape shared by the admin cell and cell
     * status log filter dropdowns.
     *
     * @return array{rows: Collection<int, Row>, maxColumnNumber: int}
     */
    public static function filterOptions(): array
    {
        return [
            'rows' => self::query()->select(['id', 'letter'])->orderBy('letter')->get(),
            'maxColumnNumber' => (int) (self::query()->max('cells_count') ?? 0),
        ];
    }
}

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
use Illuminate\Database\Eloquent\Relations\Relation;

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
     * The operational ceiling for cells_count/flats_count. The columns are
     * unsignedSmallInteger (max 65535), but that is an upper limit, not a
     * sane UX bound: no real warehouse row needs anywhere near this many
     * cells, and RowObserver::generateCells() builds cells_count * flats_count
     * array elements in memory for a single Cell::insert() — an unbounded
     * value risks PHP memory exhaustion. Enforced by StoreRowRequest/
     * UpdateRowRequest.
     */
    public const int MAX_DIMENSION = 500;

    /**
     * The highest level any row has — the number of level checkboxes the
     * settings page offers. 1 when there are no rows yet, so the default
     * level is always selectable.
     */
    public static function highestFlatsCount(): int
    {
        return max(1, (int) self::query()->max('flats_count'));
    }

    /**
     * @return HasMany<Cell, $this>
     */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    /**
     * A cell is only identified by its row, `cell_number` *and* flat: the mobile
     * API's `rows/{row}/cells/{cell:cell_number}/flats/{flatNumber}` binds
     * `{cell}` by `cell_number`, which alone matches one cell per flat, so the
     * URI's `{flatNumber}` narrows it to the single cell. Route parameters are
     * the only place the flat is available to a child binding.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Relation<Model, $this, *>
     */
    protected function resolveChildRouteBindingQuery($childType, $value, $field)
    {
        $query = parent::resolveChildRouteBindingQuery($childType, $value, $field);

        if ($childType === 'cell' && $field === 'cell_number') {
            $query->select(Cell::SELECT_COLUMNS)->where('flat_number', request()->route('flatNumber'));
        }

        return $query;
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
     * The whole warehouse grid in one query set: each row with its cells
     * (ordered by coordinates), the pallet occupying each occupied cell and
     * the product facts the maps draw from it, including the product's
     * `boxes_count`, so the 3D gauge and the rack dialog need no second request per pallet.
     * `$productPublished` narrows each row's cells to those holding a pallet of a
     * product with that published flag. Shared by `GET /api/v1/rows/full` (the
     * mobile app) and the web panel's warehouse map, which therefore always
     * receive the same payload.
     *
     * @param  Builder<Row>  $query
     */
    #[Scope]
    protected function withFullGrid(Builder $query, ?bool $productPublished = null): void
    {
        $query->select(self::SELECT_COLUMNS)
            ->withHasPallets()
            ->with(['cells' => fn ($cells) => $cells
                ->select(Cell::SELECT_COLUMNS)
                ->with([...Cell::WITH_CONTENTS, 'pallet.product.setting:product_id,boxes_count'])
                ->when($productPublished !== null, fn ($q) => $q->whereHas('pallet.product', fn ($q2) => $q2->where('published', $productPublished)))
                ->orderedByCoordinates()])
            ->orderBy('letter');
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
     * Whether any of this row's cells has ever had a status log or
     * verification report written against it. `cell_status_logs.cell_id`/
     * `related_cell_id` and `cell_verification_reports.cell_id` are all
     * `restrictOnDelete`, so a cell with history can never be deleted (or
     * regenerated by a dimension change) even once `hasPallets()` is false —
     * see RowController::destroy()/update().
     */
    public function hasHistory(): bool
    {
        $cellIds = $this->cells()->pluck('id');

        return CellStatusLog::query()
            ->where(fn (Builder $query) => $query->whereIn('cell_id', $cellIds)->orWhereIn('related_cell_id', $cellIds))
            ->exists()
            || CellVerificationReport::query()->whereIn('cell_id', $cellIds)->exists();
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

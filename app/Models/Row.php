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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property string $letter
 * @property int $cells_count
 * @property int $flats_count
 * @property-read Warehouse $warehouse
 */
#[Fillable(['warehouse_id', 'letter', 'cells_count', 'flats_count'])]
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
    public const array SELECT_COLUMNS = ['id', 'warehouse_id', 'letter', 'cells_count', 'flats_count'];

    /**
     * The eager load behind RowResource's `warehouse` summary.
     */
    public const string WITH_WAREHOUSE = 'warehouse:id,name';

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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<Cell, $this>
     */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    /**
     * A letter is unique per warehouse only, so `{row}` (bound by letter)
     * can match one row in each of several warehouses. The optional
     * `?warehouse_id=` query parameter picks the warehouse; without it a
     * letter that more than one warehouse uses is refused with a
     * `warehouse_id` validation error rather than resolved to an arbitrary
     * one of them. Only the query string is read, so a form body's own
     * `warehouse_id` never changes which row a route addresses.
     * {@see routeParameters()} builds URLs that always resolve.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return self|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $warehouseId = request()->query('warehouse_id');

        /** @var Collection<int, self> $rows */
        $rows = $this->resolveRouteBindingQuery($this->newQuery(), $value, $field)
            ->when(is_scalar($warehouseId) && $warehouseId !== '', fn (Builder $query) => $query->where('warehouse_id', (int) $warehouseId))
            ->limit(2)
            ->get();

        if ($rows->count() > 1) {
            throw ValidationException::withMessages(['warehouse_id' => __('messages.row_letter_in_several_warehouses')]);
        }

        return $rows->first();
    }

    /**
     * The route parameters addressing this row: its letter, plus
     * `warehouse_id` while another warehouse has a row with the same letter
     * (see {@see resolveRouteBinding()}). A letter only one warehouse uses
     * keeps its plain `/rows/{letter}` URL.
     *
     * @return array<string, int|string>
     */
    public function routeParameters(): array
    {
        $shared = self::query()->where('letter', $this->letter)->whereKeyNot($this->getKey())->exists();

        return ['row' => $this->letter, ...($shared ? ['warehouse_id' => $this->warehouse_id] : [])];
    }

    /**
     * Scope a query to the rows of the given warehouses; null (no warehouse
     * filter) matches every row. The cell, pallet, log and round scopes of
     * the same name all narrow through this column.
     *
     * @param  Builder<Row>  $query
     * @param  list<int>|null  $warehouseIds
     */
    #[Scope]
    protected function forWarehouses(Builder $query, ?array $warehouseIds): void
    {
        if ($warehouseIds !== null) {
            $query->whereIn($query->qualifyColumn('warehouse_id'), $warehouseIds);
        }
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
            ->with(self::WITH_WAREHOUSE)
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
        return $this->cellsHaveHistory($this->cells());
    }

    /**
     * Whether any of this row's cells in the given columns (`cell_number`) or
     * levels (`flat_number`) beyond the new bounds hold a pallet — the cells a
     * shrink to `$cellsCount` x `$flatsCount` would delete. Pass the current
     * dimension for the axis that is not shrinking so that axis matches nothing.
     */
    public function hasPalletsBeyond(?int $cellsCount, ?int $flatsCount): bool
    {
        return $this->cellsBeyond($cellsCount, $flatsCount)->whereHas('pallet')->exists();
    }

    /**
     * Whether any of this row's cells beyond the given bounds has history —
     * see hasHistory() and hasPalletsBeyond().
     */
    public function hasHistoryBeyond(?int $cellsCount, ?int $flatsCount): bool
    {
        return $this->cellsHaveHistory($this->cellsBeyond($cellsCount, $flatsCount));
    }

    /**
     * The cells outside a `$cellsCount` x `$flatsCount` grid; a null bound
     * excludes nothing on that axis.
     *
     * @return HasMany<Cell, $this>
     */
    private function cellsBeyond(?int $cellsCount, ?int $flatsCount): HasMany
    {
        return $this->cells()->where(fn ($query) => $query
            ->when($cellsCount !== null, fn ($q) => $q->orWhere('cell_number', '>', $cellsCount))
            ->when($flatsCount !== null, fn ($q) => $q->orWhere('flat_number', '>', $flatsCount))
            ->when($cellsCount === null && $flatsCount === null, fn ($q) => $q->whereRaw('1 = 0')));
    }

    /**
     * @param  HasMany<Cell, $this>  $cells
     */
    private function cellsHaveHistory(HasMany $cells): bool
    {
        $cellIds = $cells->pluck('id');

        return CellStatusLog::query()
            ->where(fn (Builder $query) => $query->whereIn('cell_id', $cellIds)->orWhereIn('related_cell_id', $cellIds))
            ->exists()
            || CellVerificationReport::query()->whereIn('cell_id', $cellIds)->exists();
    }

    /**
     * The rows + max column number shape shared by the admin cell and cell
     * status log filter dropdowns. A row is `{id, letter}`, plus its
     * `warehouse` (`{id, name}`) only when another warehouse has a row with
     * the same letter, so the dropdown can tell the two apart ("A - Annex")
     * while a letter only one warehouse uses keeps its plain label. Ordered by
     * letter, then warehouse.
     *
     * @return array{rows: BaseCollection<int, array{id: int, letter: string, warehouse?: array{id: int, name: string}}>, maxColumnNumber: int}
     */
    public static function filterOptions(): array
    {
        $rows = self::query()->select(['id', 'warehouse_id', 'letter'])->orderBy('letter')->orderBy('warehouse_id')->get();
        $sharedLetters = $rows->countBy('letter')->filter(fn (int $count): bool => $count > 1);
        $warehouseNames = $sharedLetters->isEmpty()
            ? new BaseCollection
            : Warehouse::query()->whereIn('id', $rows->whereIn('letter', $sharedLetters->keys()->all())->pluck('warehouse_id'))->pluck('name', 'id');

        return [
            'rows' => $rows->toBase()->map(fn (Row $row): array => [
                'id' => $row->id,
                'letter' => $row->letter,
                ...($sharedLetters->has($row->letter)
                    ? ['warehouse' => ['id' => $row->warehouse_id, 'name' => (string) $warehouseNames->get($row->warehouse_id)]]
                    : []),
            ]),
            'maxColumnNumber' => (int) (self::query()->max('cells_count') ?? 0),
        ];
    }
}

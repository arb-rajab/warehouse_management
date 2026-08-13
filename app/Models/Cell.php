<?php

namespace App\Models;

use App\Enums\CellState;
use Database\Factories\CellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;

/**
 * @property int $id
 * @property int $row_id
 * @property int $cell_number
 * @property int $flat_number
 * @property CellState $state
 */
#[Fillable(['row_id', 'cell_number', 'flat_number', 'state'])]
class Cell extends Model
{
    /** @use HasFactory<CellFactory> */
    use HasFactory;

    /**
     * Eager loads needed to describe what a cell currently holds.
     *
     * @var list<string>
     */
    public const array WITH_CONTENTS = [
        'pallet:id,cell_id,product_id,expiration_date,created_at',
        'pallet.product:id,name,image_url',
    ];

    /**
     * As WITH_CONTENTS, plus the parent row for cells not already queried through one.
     *
     * @var list<string>
     */
    public const array WITH_ROW_AND_CONTENTS = ['row:id,letter', ...self::WITH_CONTENTS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => CellState::class,
        ];
    }

    /**
     * @return BelongsTo<Row, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    /**
     * @return HasOne<Pallet, $this>
     */
    public function pallet(): HasOne
    {
        return $this->hasOne(Pallet::class);
    }

    /**
     * The row letter + cell number + flat number shape used to describe this
     * cell's location wherever a Resource exposes it.
     *
     * @return array{row_letter: string, cell_number: int, flat_number: int}
     */
    public function toLocationArray(): array
    {
        return [
            'row_letter' => $this->row->letter,
            'cell_number' => $this->cell_number,
            'flat_number' => $this->flat_number,
        ];
    }

    /**
     * Scope a query to the single cell identified by a row's human-readable coordinates.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function atCoordinates(Builder $query, Row $row, int $cellNumber, int $flatNumber): void
    {
        $query->where('row_id', $row->id)
            ->where('cell_number', $cellNumber)
            ->where('flat_number', $flatNumber);
    }

    /**
     * Scope a query to the order cells are laid out in for display.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function orderedByCoordinates(Builder $query): void
    {
        $query->orderBy('cell_number')->orderBy('flat_number');
    }

    /**
     * Scope a query by the state/location/expiration filters for the admin
     * "current cells" listing. This is deliberately separate from
     * CellStatusLog::filtered() — that one filters audit-log rows by
     * product/pallet/user/action/date; this one filters a cell's current
     * state and its current pallet's expiration, so the two don't share a
     * trait despite the similar row_id/column_number filters.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function filtered(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('state'), fn (Builder $q) => $q->where('state', $request->enum('state', CellState::class)?->value))
            ->when($request->filled('row_id'), fn (Builder $q) => $q->where('row_id', $request->integer('row_id')))
            ->when($request->filled('column_number'), fn (Builder $q) => $q->where('cell_number', $request->integer('column_number')))
            ->when($request->filled('expiration_date_from') || $request->filled('expiration_date_to'), fn (Builder $q) => $q->whereHas('pallet', function (Builder $palletQuery) use ($request) {
                $palletQuery
                    ->when($request->filled('expiration_date_from'), fn (Builder $q) => $q->whereDate('expiration_date', '>=', $request->date('expiration_date_from')))
                    ->when($request->filled('expiration_date_to'), fn (Builder $q) => $q->whereDate('expiration_date', '<=', $request->date('expiration_date_to')));
            }));
    }

    /**
     * Scope a query to sort by the related pallet's expiration_date, per the
     * sort_by/sort_direction request params — the only sortable column for
     * this listing. Falls back to orderedByCoordinates() when no sort_by is
     * given, so the default view reads like the row/cell grid.
     *
     * Sorts through a correlated subquery instead of joining pallets, same
     * technique as CellStatusLog::sorted(), to avoid aliasing every select
     * column against a pallets.id/cells.id collision.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, Request $request): void
    {
        if ($request->string('sort_by')->value() !== 'expiration_date') {
            $query->orderedByCoordinates();

            return;
        }

        $direction = $request->string('sort_direction')->value() === 'asc' ? 'asc' : 'desc';

        $query->orderBy(
            Pallet::query()->select('expiration_date')->whereColumn('pallets.cell_id', 'cells.id'),
            $direction,
        );

        $query->orderBy('id', $direction);
    }
}

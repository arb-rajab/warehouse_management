<?php

namespace App\Models;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use Database\Factories\CellStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * @property int $id
 * @property int $cell_id
 * @property int|null $related_cell_id
 * @property CellLogAction $action
 * @property CellState $from_state
 * @property CellState $to_state
 * @property int|null $product_id
 * @property int|null $pallet_id
 * @property int $user_id
 * @property string|null $note
 */
#[Fillable(['cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'user_id', 'note'])]
class CellStatusLog extends Model
{
    /** @use HasFactory<CellStatusLogFactory> */
    use HasFactory;

    /**
     * Columns needed by CellStatusLogResource — shared by every listing (admin and API).
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = ['id', 'cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'user_id', 'note', 'created_at'];

    /**
     * Eager loads needed by CellStatusLogResource — shared by every listing (admin and API).
     *
     * @var list<string>
     */
    public const array WITH_DETAILS = [
        'cell:id,row_id,cell_number,flat_number',
        'cell.row:id,letter',
        'relatedCell:id,row_id,cell_number,flat_number',
        'relatedCell.row:id,letter',
        'product:id,name,image_url',
        'pallet:id,expiration_date',
        'user:id,name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => CellLogAction::class,
            'from_state' => CellState::class,
            'to_state' => CellState::class,
        ];
    }

    /**
     * @return BelongsTo<Cell, $this>
     */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    /**
     * The other cell involved when this entry is one side of a transfer; null for
     * a plain status update (store/open/empty).
     *
     * @return BelongsTo<Cell, $this>
     */
    public function relatedCell(): BelongsTo
    {
        return $this->belongsTo(Cell::class, 'related_cell_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * There is no DB foreign key on `pallet_id` (see the migration), so this
     * resolves to null once the pallet itself has been emptied and deleted —
     * `pallet_id` still identifies which pallet the row belongs to.
     *
     * @return BelongsTo<Pallet, $this>
     */
    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query by the product/pallet/row/column/user/action/date-range filters
     * shared by the admin and mobile API listings. Reads straight off the request
     * (not `$request->validated()`) so an absent filter is skipped rather than
     * matched against null.
     *
     * @param  Builder<CellStatusLog>  $query
     */
    #[Scope]
    protected function filtered(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('product_id'), fn (Builder $q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('pallet_id'), fn (Builder $q) => $q->where('pallet_id', $request->integer('pallet_id')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn (Builder $q) => $q->where('action', $request->string('action')))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('row_id') || $request->filled('column_number'), fn (Builder $q) => $q->whereHas('cell', function (Builder $cellQuery) use ($request) {
                $cellQuery
                    ->when($request->filled('row_id'), fn (Builder $q) => $q->where('row_id', $request->integer('row_id')))
                    ->when($request->filled('column_number'), fn (Builder $q) => $q->where('cell_number', $request->integer('column_number')));
            }));
    }
}

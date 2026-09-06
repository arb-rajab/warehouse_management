<?php

namespace App\Models;

use App\Enums\CellState;
use Database\Factories\CellVerificationReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A single cell's verification outcome within a CellVerificationRound: the
 * pallet snapshot the system expected to find (captured server-side from the
 * cell's current pallet at submission time) versus what the mobile user
 * actually reported seeing. Append-only, like CellStatusLog — never edited.
 *
 * @property int $id
 * @property int $cell_verification_round_id
 * @property int $cell_id
 * @property int $user_id
 * @property bool $is_correct
 * @property CellState $expected_cell_state
 * @property int|null $expected_product_id
 * @property int|null $expected_boxes_count
 * @property Carbon|null $expected_expiration_date
 * @property CellState|null $reported_cell_state
 * @property int|null $reported_product_id
 * @property int|null $reported_boxes_count
 * @property Carbon|null $reported_expiration_date
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable([
    'cell_verification_round_id',
    'cell_id',
    'user_id',
    'is_correct',
    'expected_cell_state',
    'expected_product_id',
    'expected_boxes_count',
    'expected_expiration_date',
    'reported_cell_state',
    'reported_product_id',
    'reported_boxes_count',
    'reported_expiration_date',
    'note',
])]
class CellVerificationReport extends Model
{
    /** @use HasFactory<CellVerificationReportFactory> */
    use HasFactory;

    /**
     * Eager loads needed by CellVerificationReportResource — shared by every listing.
     *
     * @var list<string>
     */
    public const array WITH_DETAILS = [
        'round:id,user_id,completed_at,created_at',
        'cell:id,row_id,cell_number,flat_number',
        'cell.row:id,letter',
        'user:id,name',
        'expectedProduct:id,name,image_url,boxes_count',
        'reportedProduct:id,name,image_url,boxes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'expected_cell_state' => CellState::class,
            'expected_expiration_date' => 'date',
            'reported_cell_state' => CellState::class,
            'reported_expiration_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CellVerificationRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CellVerificationRound::class, 'cell_verification_round_id');
    }

    /**
     * @return BelongsTo<Cell, $this>
     */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function expectedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'expected_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function reportedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reported_product_id');
    }

    /**
     * Scope a query by the cell/correctness/product/date-range filters
     * shared by the admin listing of one round's reports (the round itself
     * is always scoped by the caller via a plain `where`, not through this
     * filter — every report belongs to exactly one round and one user, so
     * filtering by either here would be redundant). Reads straight off the
     * request (not `$request->validated()`) so an absent filter is skipped
     * rather than matched against null, same convention as
     * CellStatusLog::filtered().
     *
     * @param  Builder<CellVerificationReport>  $query
     */
    #[Scope]
    protected function filtered(Builder $query, Request $request): void
    {
        $productIdsFilter = $this->productIdsFromRequest($request);

        $query
            ->when($request->filled('cell_id'), fn (Builder $q) => $q->where('cell_id', $request->integer('cell_id')))
            ->when($request->has('is_correct'), fn (Builder $q) => $q->where('is_correct', $request->boolean('is_correct')))
            ->when($productIdsFilter !== null, fn (Builder $q) => $q->where(function (Builder $productQuery) use ($productIdsFilter) {
                $productQuery->whereIn('expected_product_id', $productIdsFilter)
                    ->orWhereIn('reported_product_id', $productIdsFilter);
            }))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('created_within_days'), fn (Builder $q) => $q->whereDate('created_at', '>=', now()->subDays($request->integer('created_within_days'))))
            ->when($request->filled('row_id') || $request->filled('column_number'), fn (Builder $q) => $q->whereHas('cell', function (Builder $cellQuery) use ($request) {
                $cellQuery
                    ->when($request->filled('row_id'), fn (Builder $q) => $q->where('row_id', $request->integer('row_id')))
                    ->when($request->filled('column_number'), fn (Builder $q) => $q->where('cell_number', $request->integer('column_number')));
            }));
    }

    /**
     * @return list<int>|null
     */
    private function productIdsFromRequest(Request $request): ?array
    {
        if (! $request->filled('product_id')) {
            return null;
        }

        return array_values(array_map('intval', $request->array('product_id')));
    }

    /**
     * Sort a query of verification reports by `created_at`, per the
     * `sort_direction` request param — always descending by default, tied
     * off `id` so pagination stays stable, same convention as
     * CellStatusLog::sorted().
     *
     * @param  Builder<CellVerificationReport>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, Request $request): void
    {
        $direction = $request->string('sort_direction')->value() === 'asc' ? 'asc' : 'desc';

        $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }
}

<?php

namespace App\Models;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Observers\CellStatusLogObserver;
use Database\Factories\CellStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $cell_id
 * @property int|null $related_cell_id
 * @property CellLogAction $action
 * @property CellState $from_state
 * @property CellState $to_state
 * @property int|null $product_id
 * @property int|null $pallet_id
 * @property int|null $boxes_count
 * @property int $user_id
 * @property string|null $note
 * @property-read Carbon|null $next_log_at
 * @property-read int $duration_seconds
 * @property-read bool $flagged
 */
#[Fillable(['cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'boxes_count', 'user_id', 'note'])]
#[ObservedBy(CellStatusLogObserver::class)]
class CellStatusLog extends Model
{
    /** @use HasFactory<CellStatusLogFactory> */
    use HasFactory;

    /**
     * Columns needed by CellStatusLogResource — shared by every listing (admin and API).
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = ['id', 'cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'boxes_count', 'user_id', 'note', 'created_at'];

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
        'product:id,name,image_url,boxes_count',
        'pallet:id,expiration_date',
        'user:id,name',
        'flags:id,cell_status_log_id,reason,acknowledged_at',
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
     * @return HasMany<CellStatusLogFlag, $this>
     */
    public function flags(): HasMany
    {
        return $this->hasMany(CellStatusLogFlag::class);
    }

    /**
     * Whether this log has any rule-based flags. Prefers a `flags_count` alias
     * (see RowResource.has_pallets for the same withCount-alias pattern) or an
     * already-loaded `flags` relation over running a dedicated exists query.
     *
     * @return Attribute<bool, never>
     */
    protected function flagged(): Attribute
    {
        return Attribute::make(get: function (): bool {
            if (array_key_exists('flags_count', $this->getAttributes())) {
                return $this->flags_count > 0;
            }

            if ($this->relationLoaded('flags')) {
                return $this->flags->isNotEmpty();
            }

            return $this->flags()->exists();
        });
    }

    /**
     * Attach each log's next same-pallet log's timestamp as `next_log_at`,
     * plus `duration_seconds` — the time until that timestamp, or until now
     * when this is the newest entry for its pallet (`next_log_at` is then
     * null). One extra query for the whole collection, not one per row; the
     * duration itself is a single Carbon subtraction on timestamps already
     * in memory, so it adds no meaningful cost on top of that query.
     *
     * A `TransferredOut` entry's immediate next same-pallet log is always the
     * paired `TransferredIn` written in the same transaction (see
     * PalletController::transfer) — that pairing isn't a movement of its own,
     * so it is skipped in favor of whatever happens to the pallet after it
     * lands in the destination cell.
     *
     * @param  Collection<int, CellStatusLog>  $logs
     */
    public static function attachNextLogs(Collection $logs): void
    {
        $palletIds = $logs->pluck('pallet_id')->filter()->unique()->values();

        $siblingsByPallet = static::query()
            ->select(['id', 'pallet_id', 'created_at'])
            ->whereIn('pallet_id', $palletIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('pallet_id');

        $now = now();

        foreach ($logs as $log) {
            $siblings = $siblingsByPallet->get($log->pallet_id)?->values();

            $index = $siblings?->search(fn (CellStatusLog $sibling): bool => $sibling->id === $log->id) ?? false;
            $nextIndex = $index === false ? null : $index + ($log->action === CellLogAction::TransferredOut ? 2 : 1);
            $nextLog = $nextIndex === null ? null : $siblings?->get($nextIndex);

            $log->setAttribute('next_log_at', $nextLog?->created_at);
            $log->setAttribute('duration_seconds', (int) $log->created_at->diffInSeconds($nextLog->created_at ?? $now));
        }
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
            ->when($request->filled('product_id'), fn (Builder $q) => $q->whereIn('product_id', array_map('intval', $request->array('product_id'))))
            ->when($request->filled('pallet_id'), fn (Builder $q) => $q->where('pallet_id', $request->integer('pallet_id')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->whereIn('user_id', array_map('intval', $request->array('user_id'))))
            ->when($request->filled('action'), fn (Builder $q) => $q->whereIn('action', array_map(
                fn (CellLogAction $action): string => $action->value,
                $request->enums('action', CellLogAction::class),
            )))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('created_within_days'), fn (Builder $q) => $q->whereDate('created_at', '>=', now()->subDays($request->integer('created_within_days'))))
            ->when($request->filled('expiration_date_from') || $request->filled('expiration_date_to') || $request->filled('expires_within_days'), fn (Builder $q) => $q->whereHas('pallet', function (Builder $palletQuery) use ($request) {
                $palletQuery
                    ->when($request->filled('expiration_date_from'), fn (Builder $q) => $q->whereDate('expiration_date', '>=', $request->date('expiration_date_from')))
                    ->when($request->filled('expiration_date_to'), fn (Builder $q) => $q->whereDate('expiration_date', '<=', $request->date('expiration_date_to')))
                    ->when($request->filled('expires_within_days'), fn (Builder $q) => $q->whereDate('expiration_date', '<=', now()->addDays($request->integer('expires_within_days'))));
            }))
            ->when($request->filled('row_id') || $request->filled('column_number'), fn (Builder $q) => $q->whereHas('cell', function (Builder $cellQuery) use ($request) {
                $cellQuery
                    ->when($request->filled('row_id'), fn (Builder $q) => $q->where('row_id', $request->integer('row_id')))
                    ->when($request->filled('column_number'), fn (Builder $q) => $q->where('cell_number', $request->integer('column_number')));
            }))
            ->when($request->boolean('flagged'), fn (Builder $q) => $q->whereHas('flags'));
    }

    /**
     * Sort a query of cell status logs by `created_at` (default) or the
     * related pallet's `expiration_date`, per the `sort_by`/`sort_direction`
     * request params shared by the admin and mobile API listings. Falls back
     * to `created_at` descending — the previous, hardcoded `->latest()`
     * behavior — when either param is absent, and always breaks ties on
     * `id` so pagination stays stable.
     *
     * Sorting by `expiration_date` orders by a correlated subquery instead
     * of joining `pallets` — a join would require aliasing every column in
     * `SELECT_COLUMNS`/`WITH_DETAILS` to avoid colliding with `pallets.id`.
     *
     * @param  Builder<CellStatusLog>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, Request $request): void
    {
        $direction = $request->string('sort_direction')->value() === 'asc' ? 'asc' : 'desc';

        if ($request->string('sort_by')->value() === 'expiration_date') {
            $query->orderBy(
                Pallet::query()->select('expiration_date')->whereColumn('pallets.id', 'cell_status_logs.pallet_id'),
                $direction,
            );
        } else {
            $query->orderBy('created_at', $direction);
        }

        $query->orderBy('id', $direction);
    }
}

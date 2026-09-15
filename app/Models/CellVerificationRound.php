<?php

namespace App\Models;

use Database\Factories\CellVerificationRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One walkthrough during which a mobile user verifies a series of cells.
 * `started_at` is this round's `created_at` — there is no separate column
 * for it, since a round starts the moment it's created.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'completed_at'])]
class CellVerificationRound extends Model
{
    /** @use HasFactory<CellVerificationRoundFactory> */
    use HasFactory;

    /**
     * Columns needed by CellVerificationRoundResource — shared by every listing.
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = ['id', 'user_id', 'completed_at', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CellVerificationReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(CellVerificationReport::class);
    }

    /**
     * The rows this round walks. A round covers a subset of the warehouse —
     * never implicitly all of it — and claims those rows exclusively for as
     * long as it stays unfinished: no second round may include any of them,
     * and pallet actions on their cells are refused meanwhile.
     *
     * Ordered by letter on the relation itself so every consumer — the mobile
     * app's round listing, the admin pages, the overlap message — reads the
     * same stable order without repeating an orderBy at each eager load.
     *
     * @return BelongsToMany<Row, $this>
     */
    public function rows(): BelongsToMany
    {
        return $this->belongsToMany(Row::class)->orderBy('letter');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Whether this round claims the given row — what
     * CellVerificationService::report() checks a reported cell against, so a
     * round only ever collects reports from inside its own scope.
     */
    public function coversRow(int $rowId): bool
    {
        return $this->rows()->whereKey($rowId)->exists();
    }

    /**
     * Scope a query to rounds owned by the given user — every mobile listing
     * and mutation must go through this so a user can never read or resume
     * someone else's round.
     *
     * @param  Builder<CellVerificationRound>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only rounds not yet completed, for the mobile app's
     * "resume a round" listing.
     *
     * @param  Builder<CellVerificationRound>  $query
     */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * Scope a query by the user/completion/date-range filters used by the
     * admin rounds listing. Reads straight off the request (not
     * `$request->validated()`) so an absent filter is skipped rather than
     * matched against null, same convention as CellStatusLog::filtered().
     *
     * @param  Builder<CellVerificationRound>  $query
     */
    #[Scope]
    protected function filtered(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('user_id'), fn (Builder $q) => $q->whereIn('user_id', array_map('intval', $request->array('user_id'))))
            ->when($request->has('completed'), fn (Builder $q) => $request->boolean('completed')
                ? $q->whereNotNull('completed_at')
                : $q->whereNull('completed_at'))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('created_within_days'), fn (Builder $q) => $q->whereDate('created_at', '>=', now()->subDays($request->integer('created_within_days'))));
    }

    /**
     * Sort a query of verification rounds by `created_at`, per the
     * `sort_direction` request param — always descending by default, tied
     * off `id` so pagination stays stable, same convention as
     * CellStatusLog::sorted().
     *
     * @param  Builder<CellVerificationRound>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, Request $request): void
    {
        $direction = $request->string('sort_direction')->value() === 'asc' ? 'asc' : 'desc';

        $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }
}

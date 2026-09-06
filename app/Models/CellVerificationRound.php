<?php

namespace App\Models;

use Database\Factories\CellVerificationRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
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
}

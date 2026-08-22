<?php

namespace App\Models;

use App\Enums\CellLogFlagReason;
use Database\Factories\CellStatusLogFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cell_status_log_id
 * @property CellLogFlagReason $reason
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property-read Carbon $created_at
 */
#[Fillable(['cell_status_log_id', 'reason'])]
class CellStatusLogFlag extends Model
{
    /** @use HasFactory<CellStatusLogFlagFactory> */
    use HasFactory;

    /**
     * Flags aren't edited except to acknowledge them, which has its own
     * `acknowledged_at` timestamp — there is no generic `updated_at` column.
     */
    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => CellLogFlagReason::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CellStatusLog, $this>
     */
    public function cellStatusLog(): BelongsTo
    {
        return $this->belongsTo(CellStatusLog::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}

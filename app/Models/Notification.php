<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer push delivery record, separate from Laravel's own notifications.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property string $status
 * @property string|null $response
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read StoreUser|null $user
 */
#[Table('customers_notifications')]
#[Fillable(['user_id', 'title', 'body', 'status', 'response'])]
class Notification extends Model
{
    /**
     * @return BelongsTo<StoreUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }
}

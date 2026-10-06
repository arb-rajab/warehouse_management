<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A store back-office account: a StoreUser of `user_type` staff, and the
 * store role the staff screens gave them (`role_id`, kept beside the spatie
 * role the same screens assign).
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property-read StoreUser|null $user
 * @property-read Role|null $role
 */
class Staff extends Model
{
    /**
     * @return BelongsTo<StoreUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<PickupPoint, $this>
     */
    public function pick_up_point(): HasOne
    {
        return $this->hasOne(PickupPoint::class);
    }

    /**
     * Whether this account holds the store's Super Admin role, which the
     * staff screens neither edit nor delete.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->user?->hasRole(Role::SUPER_ADMIN, 'store');
    }
}

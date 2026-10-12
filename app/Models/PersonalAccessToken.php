<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Points Sanctum at this app's own token table instead of the default
 * `personal_access_tokens`.
 *
 * Sanctum offers no config key for the table name, so overriding `$table` on a
 * subclass registered via `Sanctum::usePersonalAccessTokenModel()` (see
 * AppServiceProvider) is the only way to move it. The move is required rather
 * than cosmetic: production shares one database with the store app, which runs
 * Sanctum too, and `tokenable_type` records the model FQN — `App\Models\User`
 * in both apps, over two different user tables whose ids overlap. Sharing the
 * table would let a store token resolve to the WMS user of the same id. See
 * .ai/rules/shared-database.md.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'wms_personal_access_tokens';

    /**
     * Whether the token is past `sanctum.expiration` or its own `expires_at`.
     *
     * The same test Sanctum's guard applies to a bearer token. The store's
     * "who owns this token" endpoints look a token up themselves with
     * findToken(), which does not apply it, so they ask here.
     */
    public function isExpired(): bool
    {
        $lifetimeMinutes = (int) config('sanctum.expiration');
        $createdAt = $this->getAttribute('created_at');
        $expiresAt = $this->getAttribute('expires_at');

        return ($lifetimeMinutes > 0 && $createdAt !== null && Carbon::parse($createdAt)->lte(now()->subMinutes($lifetimeMinutes)))
            || ($expiresAt !== null && Carbon::parse($expiresAt)->isPast());
    }
}

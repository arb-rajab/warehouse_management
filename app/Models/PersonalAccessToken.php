<?php

namespace App\Models;

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
}

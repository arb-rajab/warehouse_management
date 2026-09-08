<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The WMS-owned tables that used to carry a default Laravel/package name,
     * mapped legacy name => current name. Each one collides with a table of
     * the same name in the store app, which shares this app's production
     * database — see .ai/rules/shared-database.md for why none of them can be
     * shared.
     *
     * @var array<string, string>
     */
    private const array RENAMES = [
        'sessions' => 'wms_sessions',
        'password_reset_tokens' => 'wms_password_reset_tokens',
        'cache' => 'wms_cache',
        'cache_locks' => 'wms_cache_locks',
        'jobs' => 'wms_jobs',
        'job_batches' => 'wms_job_batches',
        'failed_jobs' => 'wms_failed_jobs',
        'personal_access_tokens' => 'wms_personal_access_tokens',
        'roles' => 'wms_roles',
        'permissions' => 'wms_permissions',
        'model_has_permissions' => 'wms_model_has_permissions',
        'model_has_roles' => 'wms_model_has_roles',
        'role_has_permissions' => 'wms_role_has_permissions',
    ];

    /**
     * Run the migrations.
     *
     * The create migrations and config/permission.php now produce the `wms_`
     * names directly, so this is a no-op on a fresh install and only fires on
     * a database migrated before that change.
     *
     * Both halves of the condition matter. Renaming only when the legacy name
     * is present skips tables already migrated; refusing to rename when the
     * target already exists is what keeps this safe against the shared
     * production database, where every legacy name on this list is the store
     * app's table and renaming one would break that app.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $legacy => $current) {
            if (Schema::hasTable($legacy) && ! Schema::hasTable($current)) {
                Schema::rename($legacy, $current);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RENAMES as $legacy => $current) {
            if (Schema::hasTable($current) && ! Schema::hasTable($legacy)) {
                Schema::rename($current, $legacy);
            }
        }
    }
};

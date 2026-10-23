---
paths:
  - 'config/health.php,database/migrations/*health*,app/Providers/HealthServiceProvider.php'
---

# Providers

## spatie/laravel-health: own DB connection, checks registered in HealthServiceProvider, no auto-routes
Like Telescope/Pulse, health results are stored on a dedicated 'health' sqlite connection (config/database.php), set via config/health.php result_stores.connection = env('HEALTH_DB_CONNECTION', 'health'). Keep HEALTH_DB_DATABASE=:memory: in phpunit.xml for the same RefreshDatabase reason as telescope/pulse.

Unlike Telescope/Pulse, spatie/laravel-health does NOT self-register a dashboard route — it's added manually in routes/web.php: `Route::get('health', HealthCheckResultsController::class)->middleware(['can:viewHealth', RestrictToAllowedIps::class.':health.allowed_ips'])`. The `health.allowed_ips` config key (custom, not part of the package) falls back to `TELESCOPE_ALLOWED_IPS` like Pulse does, reusing the shared RestrictToAllowedIps middleware.

Checks (Database, Cache, UsedDiskSpace, Schedule, Queue, Backups, DebugMode/OptimizedApp gated `->if(app()->isProduction())`) are registered via `Health::checks([...])` in app/Providers/HealthServiceProvider::boot(), not in config/health.php (unlike Telescope's watchers, which do live in config). routes/console.php schedules `health:check`, `health:schedule-check-heartbeat`, `health:queue-check-heartbeat`, and `model:prune --model=HealthCheckResultHistoryItem` (needed because the model lives outside app/Models so artisan's default model:prune won't discover it).

Don't write a test that actually GETs /health as an authorized admin — HealthCheckResultsController queries the 'health' connection's history table, which RefreshDatabase never migrates (same reason TelescopeAccessTest/PulseAccessTest never hit their own dashboard routes). Only test the gate/config wiring and the 403 rejection paths (those short-circuit in the `can:` middleware before any DB query).

UsedDiskSpaceCheck shells out to the Unix `df` command — it will show CRASHED on native Windows dev machines; this is an environment limitation, not a bug, and works fine on the Linux production host.

## A migration that writes to another connection must name it with getConnection()
The health table lives on the `health` SQLite connection and is created with `Schema::connection(...)`. `migrate --pretend` resolves the connection to pretend on from the migration's `getConnection()`, not from the `Schema::connection()` calls inside it, so a migration that only calls `Schema::connection('health')` runs for real under `--pretend` (and the real migrate then fails with "table already exists"). `create_health_tables` overrides `getConnection()` like Telescope's migration does, which also puts the real run in a transaction on that connection. `tests/Feature/Database/CreateHealthTablesTest.php` runs `migrate --pretend` then `migrate` against scratch SQLite files. `migrate --pretend` still installs the (empty) `wms_migrations` table itself, in Laravel's `prepareDatabase()`; nothing in this app can prevent that, so never run it before the one-time `migrations` → `wms_migrations` rename on an existing database.

---
paths:
  - 'database/migrations/**'
  - 'config/database.php,config/permission.php,config/session.php,config/cache.php,config/queue.php,config/auth.php'
  - app/Models/PersonalAccessToken.php
  - app/Models/Product.php
  - app/Providers/AppServiceProvider.php
---

# Shared production database (WMS + store app)

In production this app does not have a database of its own. It shares one MySQL
database with an existing store/marketplace Laravel app, which was there first
and owns the `products` table plus roughly sixty other tables. Everything below
follows from that: any table name both apps would pick is a collision, and a
collision is silent — no error, just two apps writing each other's rows.

## Exactly one table is shared: `products`

`products` is the store app's, and this app is **read-only** on it — there is no
create/update/delete of a `Product` anywhere in the codebase, and there must not
be. Only four attributes are consumed:

| Attribute | Read by |
| --- | --- |
| `id` | every `product_id` FK, all filters |
| `name` | `ProductResource`, `ProductSummaryResource`, `ProductOptionResource`, `Product::searchByName()`, `Pallet::toCellPayload()` |
| `image_url` | `ProductResource`, `ProductSummaryResource`, `Pallet::toCellPayload()`, `Cell`/`CellStatusLog`/`CellVerificationReport` eager-load column lists |
| `boxes_count` | `PalletActionService`, `Api\V1\ProductController`, `ProductResource` |

Do not mirror the store's other columns into the local stand-in migration, and
do not widen a `select()` to `Product::all()` — the narrow column lists are what
keep this app insulated from a schema it does not control.

### `products.id` is a signed `int(11)`, not `bigint unsigned`

The store's own migration ends with an `ALTER TABLE products MODIFY id int(11)
AUTO_INCREMENT`, so the live primary key is a **signed 32-bit int**. Laravel's
`foreignId()` emits `bigint unsigned`, which MySQL will not accept as a foreign
key against it. Every column referencing a product is therefore a plain
`integer` with an explicit `foreign()` clause, not `foreignId()->constrained()`:

- `pallets.product_id`
- `cell_status_logs.product_id`
- `cell_verification_reports.expected_product_id`
- `cell_verification_reports.reported_product_id`

sqlite reports both types as `integer`, so the test connection cannot catch a
regression here — `CreateProductsTableTest` asserts it against the migration
source instead. Keep that assertion when adding a new product FK.

### Open: `boxes_count` has no home in production yet

`add_boxes_count_to_products_table` still guards on `app()->isProduction()`, so
in production the column does **not** exist on the shared table — while
`PalletActionService` and `Api\V1\ProductController` both read it. That is an
unresolved production defect, not a working arrangement. The agreed resolution
is to map it onto the store's existing per-carton quantity column, which needs
the store schema dump to identify. Until that lands, do not deploy pallet
placement against the shared database.

## Every other colliding table is WMS-owned, under a `wms_` prefix

| Table | Why not shared |
| --- | --- |
| `wms_users` | separate staff/user population from the store's customers |
| `wms_password_reset_tokens`, `wms_sessions` | follow `wms_users`; a shared `sessions` id space crosses the two apps' logins |
| `wms_personal_access_tokens` | `tokenable_type` is `App\Models\User` in *both* apps, over two user tables with overlapping ids — a shared table lets a store token resolve to the WMS user of the same id |
| `wms_roles`, `wms_permissions`, `wms_model_has_roles`, `wms_model_has_permissions`, `wms_role_has_permissions` | same morph-collision as above: `model_has_roles.model_type` is `App\Models\User` on both sides, so a shared row grants the store's roles to the WMS user of that id |
| `wms_cache`, `wms_cache_locks` | both apps run the database cache store; a shared table means either can read, overwrite or flush the other's entries |
| `wms_jobs`, `wms_job_batches`, `wms_failed_jobs` | both run the database queue; this app's workers would reserve store payloads whose job classes don't exist here |
| `wms_migrations` | the migration repository itself — a shared one makes each app think the other's migrations have already run |

The remaining tables (`rows`, `cells`, `pallets`, `cell_status_logs`,
`cell_status_log_flags`, `cell_verification_rounds`,
`cell_verification_reports`, `mobile_app_version_requirements`) keep bare names:
they are specific enough to this domain that the store app has nothing like
them. `tests/Feature/SharedDatabaseTableNamesTest.php` pins that inventory, so a
new bare-named table fails the suite until it is justified here. Telescope,
Pulse and Health are unaffected — they run on their own sqlite connections (see
config.md), never in the shared database.

Where a table name is config-driven, the prefix lives in config, not in the
migration: `config/permission.php`'s `table_names`, `config/session.php`,
`config/auth.php`, `config/cache.php`, `config/queue.php`,
`config/database.php`'s `migrations.table`. Re-publishing any of those vendor
configs resets them to the colliding defaults —
`SharedDatabaseTableNamesTest` is the guard against that. Sanctum has no such
config key, so `App\Models\PersonalAccessToken` overrides `$table` and
`AppServiceProvider` registers it via `Sanctum::usePersonalAccessTokenModel()`.

## Migration conventions this arrangement requires

**Create the final table name.** A create migration emits the `wms_` name
directly — never the bare name plus a later rename. On a fresh production
migrate the bare name already exists (it is the store's), so `Schema::create()`
would fail outright with "table already exists", and a subsequent rename would
then rename the store's table out from under it.

**A shared table's `up()` guards on `Schema::hasTable()`, its `down()` on the
environment.** `create_products_table` is the worked example. `hasTable()` makes
`up()` a no-op wherever the real table is present — production, or any
environment pointed at the shared database — while still building a local
stand-in for development and testing. `down()` cannot use `hasTable()` (it
cannot tell the store's table from the stand-in), so it guards on
`app()->isProduction()`: a rollback drops the development copy and never the
store's data. Do not use `isProduction()` on `up()` — that was the previous
guard, and it breaks any non-production environment that points at the shared
database.

**A rename migration checks both ends.** `rename_users_table_to_wms_users` and
`rename_colliding_tables_to_wms_prefix` both rename only when the legacy name
exists *and* the target does not. The second half is the safety property: in the
shared database every legacy name on that list is the store app's table, and
renaming one would break that app. Both migrations are no-ops on a fresh install
and exist only for databases migrated before the prefix landed.

**The migration repository cannot rename itself.** Laravel reads
`database.migrations.table` before running anything, so pointing it at
`wms_migrations` makes an existing WMS database look unmigrated and re-run
everything. Production is a fresh install and is unaffected. An existing
local/staging database needs the rename applied out of band before the next
migrate — `RENAME TABLE migrations TO wms_migrations;` — or a `migrate:fresh`.

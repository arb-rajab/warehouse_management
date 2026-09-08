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

Verified against the store's schema dump (107 tables): `products` is the only
name the two apps share that this app also reads. Every WMS domain table —
`rows`, `cells`, `pallets`, `cell_status_logs`, `cell_status_log_flags`,
`cell_verification_rounds`, `cell_verification_reports`,
`mobile_app_version_requirements` — is absent from the store's schema, so those
keep bare names.

`products` is the store app's, and this app is **read-only** on it — there is no
create/update/delete of a `Product` anywhere in the codebase, and there must not
be. The store's table has 72 columns; only four are consumed here:

| WMS attribute | Store column | Notes |
| --- | --- | --- |
| `id` | `id` `int(11)` signed | see below |
| `name` | `name` `varchar(200)` NOT NULL | direct match. The store also carries `ar_name` and a `product_translations` table; this app reads the base `name` only |
| `image_url` | **no counterpart** | unresolved — see below |
| `boxes_count` | **no counterpart** | unresolved — see below |

Do not mirror the store's other 68 columns into the local stand-in migration,
and do not widen a `select()` to `Product::all()` — the narrow column lists are
what keep this app insulated from a schema it does not control.

### `products.id` is a signed `int(11)`, not `bigint unsigned`

The store's schema ends with `ALTER TABLE products MODIFY id int(11) NOT NULL
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

### Open: `image_url` and `boxes_count` do not exist upstream

Both are columns this app invented on a table it does not own, and neither has a
counterpart in the store's schema. In production the local
`add_boxes_count_to_products_table` migration is guarded off, so `boxes_count`
is simply absent while `PalletActionService` and `Api\V1\ProductController`
read it — an unresolved production defect, not a working arrangement. Do not
deploy pallet placement against the shared database until it is settled.

The store's nearest columns, none of them a drop-in rename:

- For an image: `thumbnail_img varchar(100)` and `photos varchar(2000)` hold
  `uploads` row ids (resolved through the `uploads` table to a filename), not
  URLs; `meta_img` is the SEO image. Turning any of them into the URL string the
  mobile app already consumes needs a join plus a base-URL prefix, so this is a
  resolver decision rather than a column rename.
- For a box count: `unit_equal int(11) NOT NULL` sits with the ERP-sync columns
  (`mat_id`, `serial`, `from_api`, `api_unitId`, `api_unit_name`) and reads like
  units-per-carton, but the dump is schema-only and this is inference.
  `min_qty`/`max_qty` are Active-eCommerce cart limits (their 1/1000 defaults
  are the stock ones), and `current_stock` is a stock level — none of them is a
  box count.

Confirm both against the store app's code or its owners before wiring either up.

## Every other colliding table is WMS-owned, under a `wms_` prefix

Eight of these names are **taken in the store's schema today**; the rest are
prefixed pre-emptively, because they are what Laravel's own scaffolding creates
and the store could add any of them at any time. The distinction matters when
weighing a future request to un-prefix one — the eight are not negotiable.

| Table | Taken today? | Why not shared |
| --- | --- | --- |
| `wms_users` | yes | separate staff population from the store's customers. The store's `users.id` is `int(10) unsigned`; this app's stays `bigint unsigned` because nothing joins the two |
| `wms_personal_access_tokens` | yes | `tokenable_type` is `App\Models\User` in *both* apps, over two user tables with overlapping ids — a shared table lets a store token resolve to the WMS user of the same id |
| `wms_roles`, `wms_permissions`, `wms_model_has_roles`, `wms_model_has_permissions`, `wms_role_has_permissions` | yes | same morph-collision: `model_has_roles.model_type` is `App\Models\User` on both sides, so a shared row grants the store's roles to the WMS user of that id |
| `wms_migrations` | yes | the migration repository itself — a shared one makes each app think the other's migrations have already run |
| `wms_password_reset_tokens` | no — the store uses the legacy `password_resets` | follows `wms_users`: a reset token is meaningless against the other app's user table |
| `wms_sessions` | no — the store is not on the database session driver | a shared session id space would cross the two apps' logins |
| `wms_cache`, `wms_cache_locks` | no | this app runs `CACHE_STORE=database`; if the store ever does too, either app could read, overwrite or flush the other's entries |
| `wms_jobs`, `wms_job_batches`, `wms_failed_jobs` | no | this app runs `QUEUE_CONNECTION=database`; if the store ever does too, each app's workers would reserve payloads whose job classes don't exist on their side |

The remaining tables (`rows`, `cells`, `pallets`, `cell_status_logs`,
`cell_status_log_flags`, `cell_verification_rounds`,
`cell_verification_reports`, `mobile_app_version_requirements`) keep bare names:
they are specific enough to this domain that the store app has nothing like
them — verified name-by-name against the store's 107 tables. `tests/Feature/SharedDatabaseTableNamesTest.php` pins that inventory, so a
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

---
paths:
  - 'database/migrations/**'
  - 'config/database.php,config/permission.php,config/session.php,config/cache.php,config/queue.php,config/auth.php'
  - app/Models/PersonalAccessToken.php
  - app/Models/Product.php
  - app/Providers/AppServiceProvider.php
  - resources/js/lib/productName.ts
  - resources/js/components/ProductOptionLabel.vue
  - app/Console/Commands/SyncProductsCommand.php
  - config/store.php
---

# Shared production database (WMS + store app)

In production this app does not have a database of its own. It shares one MySQL
database with an existing store/marketplace Laravel app, which was there first
and owns the `products` table plus roughly sixty other tables. Everything below
follows from that: any table name both apps would pick is a collision, and a
collision is silent — no error, just two apps writing each other's rows.

## Two tables are shared: `products` and `uploads`

Verified against the store's schema dump (107 tables): these are the only names
the two apps share that this app also reads. Every WMS domain table — `rows`,
`cells`, `pallets`, `cell_status_logs`, `cell_status_log_flags`,
`cell_verification_rounds`, `cell_verification_reports`,
`mobile_app_version_requirements` — is absent from the store's schema, so those
keep bare names.

Both belong to the store app. This app is fully read-only on `Upload`, and
read-only on `Product` except for three columns — see "Product sync" below.
There is no per-request create/update/delete of a `Product` or `Upload`
anywhere in the codebase, and there must not be: the one write path is the
scheduled bulk sync, not a controller or job reacting to a WMS action. The
store's `products` has 72 columns and its `uploads` has eleven; only these are
consumed here:

| WMS attribute | Source | Notes |
| --- | --- | --- |
| `Product::$id` | `products.id` `int(11)` signed | see below |
| `Product::$name` | `products.name` `varchar(200)` NOT NULL | direct match; shipped raw, and the label the frontend renders outside Arabic |
| `Product::$ar_name` | `products.ar_name` `varchar(191)` NOT NULL | searched in both locales, shipped raw alongside `name`, rendered in Arabic — see below. The store's `product_translations` table is still not read |
| `Product::$thumbnail_img` | `products.thumbnail_img` `varchar(100)` | holds an `uploads` row id, **not** a URL |
| `Product::$published` | `products.published` `int(11) NOT NULL DEFAULT 1` | the store admin's own active/inactive toggle, cast to `bool` here — see below |
| `Product::$image_url` | derived | `thumbnailUpload->url` — see below |
| `Product::$boxes_count` | `wms_product_settings.boxes_count` | WMS-owned, not a store column at all — see below |
| `Upload::$file_name`, `$external_link` | same columns | the two halves of `Upload::url()` |

Do not mirror the store's other columns into either stand-in migration, and do
not widen a `select()` to `Product::all()` — the narrow column lists are what
keep this app insulated from a schema it does not control.

### `ar_name`: searched in both locales, rendered in Arabic by the frontend

Two separate decisions, deliberately different from each other.

**Search does not follow the locale.** `Product::searchByName()` matches each
word of the term against `name` **OR** `ar_name`; the two consumers —
`Admin\ProductController::search()` (the admin filter dropdown) and
`Api\V1\ProductController::index()` (the mobile catalog) — share that one
scope, so both search both columns whatever the request's locale
(`SetLocaleFromHeader` for the API, the session via `SetLocale` for the web
panel). The term is whatever the warehouse staff actually typed, and they type
whichever of the two names they happen to know for a product. Scoping the
search to the active locale's column would make an identical term return
different results per device, and would return nothing at all for a product
whose `ar_name` the store left empty. Matching both costs nothing: an empty
`ar_name` cannot match a non-empty word. Rendering being locale-dependent is
not a reason to revisit this — don't "align" the two.

Two things the search implementation depends on, both covered by tests:

- **Group per word.** Each word gets its own nested `where(fn ($q) => ...
  ->orWhere(...))`. A flat `orWhere()` chain binds the OR across word
  boundaries and silently turns "matches every word" into "matches any word".
- **An empty `ar_name` matches nothing**, which is what keeps a product the
  store never translated out of an English term's results through that column.

**Rendering follows the locale, and the choice is made in Vue, not PHP.**
Every payload carrying a product name ships **both raw store columns**, and
`resources/js/lib/productName.ts` picks between them:

```ts
productName(name, arName) // arName || name under `ar`, name otherwise
```

`ProductResource`, `ProductOptionResource` and `ProductSummaryResource` all
emit `name` **and** `ar_name`; `Pallet::toMapSummaryArray()` emits
`product_name` **and** `product_ar_name`; `Product::optionLabels()` (behind
`filterOptions()`/`selectedOptions()`) maps to `{id, name, ar_name}`. There is
no `display_name` accessor any more — the model has no locale-aware attribute
at all, and no payload varies by locale.

Why the choice moved out of the backend, having first been made in it:

- **One payload, one contract.** `ProductResource` is dual-audience: the mobile
  app (`Api\V1\ProductController`, and nested via `PalletResource`,
  `CellStatusLogResource`, `CellVerificationReportResource`) *and* the admin
  Inertia panel. Resolving on the backend made `name`'s *content* depend on
  `Accept-Language` while its key and type stayed put — a response two clients
  could not cache or compare. Shipping both columns gives both audiences the
  same bytes.
- **The mobile app reads English until it adopts `ar_name`.** Accepted
  knowingly: `name` is the raw base name for every client now, `ar_name` is
  additive, and the mobile codebase (separate, not in this repo) picks it up on
  its own schedule. It was never shipping Arabic product names in production —
  the backend resolution that would have given it them was merged but not
  released, and staging's `products` rows all carry `ar_name = ''` anyway.
- **The web panel needs no round trip.** The locale is already on the client
  (`i18n.global.locale.value`, set from the shared Inertia `locale` prop in
  `resources/js/app.ts`), so a language switch re-renders labels from props
  already in memory instead of depending on a server render.

Four consequences worth knowing before changing any of this:

- **`?:` on the backend, `||` in TS — never `??`.** `ar_name` is NOT NULL
  upstream, so an untranslated product carries `''` rather than null. `??`
  would render a blank label. `ProductFactory` supplies a value deliberately
  *unlike* the English `name`; pass `'ar_name' => ''` explicitly to exercise
  the fallback, and cover both a populated and an empty `ar_name` in both
  locales when adding a render site.
- **One label per site, except the two product dropdowns.** Every render site
  shows the locale's label alone; `ProductSelect.vue` and
  `FilterProductSelect.vue` show both, via `components/ProductOptionLabel.vue`
  (`productAlternateName()` supplies the second line, or null when there is
  nothing worth showing — an untranslated product, or one whose two columns
  hold the same string). That exception exists because **search is
  locale-independent while rendering is not**: a worker on the English panel
  who types an Arabic term gets a result whose row would otherwise read only
  the English name, with nothing to explain the match. Nowhere else has that
  gap — elsewhere the user is reading a label, not verifying a query — and a
  second name costs real space there: `CellSlot.vue` already truncates at one
  line, table rows would grow on every row, and `alt`/`aria-label` text and the
  3D map's `aria-live` announcement would just read both names aloud each time.
  The dropdown *trigger* button stays single-label for the same reason.
- **Both names in one place means two elements with `dir="auto"`, never one
  interpolated string.** Direction is set once, on `<html>`
  (`resources/views/app.blade.php`), and nothing else in the app sets `dir` per
  element — so a Latin name inside the Arabic panel (or an Arabic one inside
  the English panel) reorders against the surrounding layout unless it isolates
  itself, and any punctuation shared between the two names reorders with it.
- **Resolve once, at the render site — never twice.** `CellMap3D.vue`
  re-projects a `CellMap3DItem` back into a `CellPallet`-shaped object it hands
  to `CellSlot.vue`, which resolves the label itself; that re-projection passes
  `product_ar_name` straight through. Resolving in both places double-applies
  the choice. The one memo that stores a *resolved* label rather than raw
  columns is `useProductSearch`'s `namesById`, which deliberately outlives the
  result page a selection came from — it holds the primary label only, since
  the trigger button it feeds shows one line.
- **Every product `select()`/eager load carries `ar_name`.** Currently
  `Admin\ProductController::index()` and `::search()`,
  `Api\V1\ProductController::index()`, `Product::optionLabels()`,
  `Cell::WITH_CONTENTS`, `CellStatusLog`'s and `CellVerificationReport`'s
  eager-load constants, and
  `Api\V1\PalletController`/`Api\V1\CellVerificationReportController`'s.
  Add it to any new one. Every Resource now reads `ar_name` unconditionally, so
  outside production a query that leaves it out throws a
  `MissingAttributeException` in *both* locales (strict mode's
  `preventAccessingMissingAttributes` — see app-providers.md), and the
  response-shape tests that assert `ar_name` are what catch it. In production,
  where strict mode is off, the attribute reads as null and the frontend
  degrades to the base `name` rather than erroring at a warehouse worker.
- **A missed render site fails silently.** Nothing throws when a component
  renders `product.name` directly; it just shows English to an Arabic user. The
  frontend tests therefore assert the *rendered* label in both locales rather
  than only the props received.

The one place that deliberately still reads the raw `name` alone is
`Admin\CellVerificationRoundController::export()`'s CSV. That file is data
rather than UI — its column headers are untranslated snake_case machine names
(`expected_product`, `cell_number`) and `is_correct` is a literal `yes`/`no` —
so its product column stays on the store's stable base name in both locales and
never emits `ar_name` at all. A test pins that; if the export ever gains
translated headers, revisit it as a whole rather than switching that one column.

`filterOptions()` and `selectedOptions()` are the only product payloads that
never pass through a Resource, so `Product::optionLabels()` maps them to plain
`{id, name, ar_name}` arrays instead of serialising models — that is what keeps
the emitted shape identical to the frontend's `ProductFilterOption`, and what
keeps the other 69 store columns out of the response. Don't revert them to
returning `Collection<int, Product>`.

**Ordering deliberately stays on the base `name` column in both locales**, and
that is a skipped feature rather than an oversight. Sorting an Arabic listing
by `ar_name` would clump every untranslated product (`''`) at the top under an
English label; the correct expression is `ORDER BY CASE WHEN ar_name = '' THEN
name ELSE ar_name END`, which then mixes two scripts in one ordering, and MySQL
utf8mb4 collation and sqlite's byte ordering disagree about the result — CI is
sqlite, so no test could pin the production behaviour. It would also make the
products index's `sort_by=name` column and its page boundaries mean different
things per locale. Moving rendering to the client does not change this: the
server still paginates, so the page a product lands on is decided in SQL. If
Arabic-collated ordering is wanted, it needs a decision about untranslated
products first.

The stand-in declares `ar_name` NOT NULL with an empty-string default. Upstream
it is `varchar(191) NOT NULL`; the default is a stand-in-only convenience so a
row inserted without an Arabic name works, an existing stand-in can take the
column with no backfill, and sqlite — which refuses a NOT NULL column added by
`ALTER` without a default — accepts the migration.

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
- `wms_product_settings.product_id`

`uploads.id` is the same signed `int(11)`. sqlite reports both types as
`integer`, so the test connection cannot catch a regression here —
`CreateProductsTableTest` asserts it against the migration source instead. Keep
that assertion when adding a new product FK.

### `published`: the store admin's active/inactive toggle, not an occupancy signal

`products.published` (`int(11) NOT NULL DEFAULT 1`) is the store admin's own
kill switch for a product, entirely independent of whether it currently
occupies any cell in this warehouse. `Admin\ProductController::index()`'s
`inactive` filter (`?inactive=true`) is `published = 0` — do not redefine
"inactive" as "occupies zero cells" (e.g. `whereDoesntHave('pallets')`): a
product can be `published = 0` while pallets of it still sit in cells (the
store deactivated it after it was already stocked), and a freshly `published
= 1` product legitimately has zero pallets before its first delivery. The two
concepts don't imply each other in either direction.

### `image_url` and `boxes_count` are not columns — both are derived

Neither exists in the store's schema under any name. They were columns this app
had added to a table it does not own, which is why `boxes_count` was guarded off
in production and therefore *absent* there while `PalletActionService` read it.
Both are now attributes on `Product`, so the API and admin payloads keep the
exact keys their clients already consume:

- **`image_url`** resolves `thumbnail_img` through the shared `uploads` table.
  `Upload::url()` returns `external_link` verbatim when set (the store uses it
  for files already on a CDN), otherwise joins `file_name` to
  `config('store.asset_base_url')`. That config is the store app's public base
  URL — this app is served from a different host and cannot derive it. Unset,
  the URL resolves to `null` rather than to a relative path that would 404
  against this app's own domain.
- **`boxes_count`** lives in `wms_product_settings`, keyed by the store's
  product id. A product the store has added but this app has never configured
  has no row, and falls back to `Product::DEFAULT_BOXES_COUNT`. There is no
  store column to backfill this from: `products.unit_equal` reads like a
  units-per-carton value and was the obvious candidate, but it has been
  checked with the store's owners and is **not** the box count — do not wire
  it up. The only write path is `Admin\ProductController::updateBoxCount()`,
  behind the editable box-count column on the admin products screen; a product
  nobody has set resolves to the default rather than to a stored zero.

Both are relation-backed, so anything reading them must eager-load first or
trip the lazy-loading guard in local/testing. `Product::WITH_DERIVED_ATTRIBUTES`
is the pair of eager loads; use it, or spell out whichever half a given payload
actually needs (the admin product listing only renders the image, so it loads
`thumbnailUpload` alone).

In tests, `boxes_count` and `image_url` cannot be passed to
`Product::factory()->create()` — they are not columns. Use the
`boxesCount(int)`, `imageUrl(?string)` and `unconfigured()` factory states.

### Product sync: the one write path into a store-owned table

`products:sync` (`app/Console/Commands/SyncProductsCommand.php`, scheduled
hourly in `routes/console.php`) polls the Otajer store's REST product feed
(URL in `config('store.products_sync_url')`, env `STORE_PRODUCTS_SYNC_URL` —
the store's API key is embedded in that URL's path, so it is never
hardcoded) and upserts exactly three columns — `name`, `ar_name`,
`published` — keyed on `id`. This is a deliberate, narrow reversal of the
"WMS never writes `products`" rule above, made because those three columns
need to stay current without WMS having a live join to the store's own
product-management flow.

Two things worth knowing before touching this:

- **`Mat_ID` is assumed to equal `products.id`.** The feed returns it as a
  numeric string (e.g. `"100027"`); the command casts it to int and uses it
  as the upsert key. If that assumption is ever wrong for some product
  range, the symptom is silent — a sync either creates a phantom row at the
  wrong id or overwrites an unrelated one, since nothing else cross-checks
  the mapping.
- **Two writers, same columns, no locking.** The store app can still edit a
  product's name/Arabic name/published state directly at any time; the next
  hourly sync run will overwrite that edit with whatever the feed currently
  says, and a store-side write made between two sync runs has no
  protection against being clobbered. There is no last-write-wins
  timestamp comparison — the feed's copy always wins on the next run.
- **Everything else in the feed is intentionally dropped.** Price tiers,
  tax, barcodes, unit/class ids, and the image are not mapped to any
  column — the same "narrow column list" discipline as the rest of this
  file. A row with no usable `Mat_ID` or `enName` is skipped rather than
  written with a guessed value.

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
environment.** `create_products_table` and `create_uploads_table` are the worked examples. `hasTable()` makes
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

**Editing an already-run migration needs a companion upgrade migration.**
Rewriting `create_products_table` to build the store's shape changed nothing on
staging or an older local checkout: the migration was already recorded, so it
never re-ran, and those databases kept the pre-alignment columns while the code
moved on. `upgrade_legacy_products_stand_in` is the fix, and the pattern for the
next one — identify the old shape by a column the store's table cannot have
(`products.image_url` was only ever this app's), carry any data worth keeping
into its new home, then drop it.

`add_ar_name_to_products_stand_in` is the same pattern for a pure column
addition, and the simpler case to copy: adding `ar_name` to
`create_products_table` covers fresh installs only, so the companion migration
guards on `Schema::hasColumn('products', 'ar_name')` and no-ops wherever the
column already exists — production (the store's own table) and any fresh
install alike — while bringing an older stand-in across. Always add both halves;
editing the create migration on its own is what left staging stale last time.

That migration deliberately leaves the stand-in's `id` as `bigint unsigned`
rather than retyping it to the store's signed `int(11)`, because altering a
primary key referenced by four foreign keys is risky and sqlite cannot verify
the result. The consequence is worth knowing: on such a database
`wms_product_settings.product_id` (signed `int`) references a `bigint unsigned`
key. sqlite does not type-check foreign keys so it is inert there, but **MySQL
rejects it** — `create_wms_product_settings_table` would fail with errno 3780.
A pre-existing stand-in must therefore never be moved onto MySQL in place;
rebuild it with `migrate:fresh`. Production is a first install against the
store's own `int(11)` table and is unaffected. Note that no test can catch this
class of defect: CI runs sqlite, which ignores foreign key types entirely.

**The migration repository cannot rename itself.** Laravel reads
`database.migrations.table` before running anything, so pointing it at
`wms_migrations` makes an existing WMS database look unmigrated and re-run
everything. Production is a fresh install and is unaffected. An existing
local/staging database needs the rename applied out of band before the next
migrate — `RENAME TABLE migrations TO wms_migrations;` — or a `migrate:fresh`.

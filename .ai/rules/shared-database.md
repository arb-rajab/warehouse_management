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

# Database ownership, and the shared-database history behind it

**This app now runs its own database.** It used to share one MySQL database
with an existing store/marketplace Laravel app, which was there first. That
arrangement is over, but it is not merely trivia: it is the reason almost every
table in this app is named the way it is, and the reason `products` and
`uploads` have the shapes they do. Read the history before changing any of it.

## The `wms_` prefixes stay, whatever the history

Every table this app owns carries a `wms_` prefix (`wms_users`, `wms_migrations`,
`wms_roles`, …). Those prefixes were adopted to avoid collisions in the shared
database. The collision risk is gone; **the names are not negotiable anyway.**

They are the live table names in every existing database, and
`tests/Feature/SharedDatabaseTableNamesTest.php` pins both them and the config
keys that point at them. Un-prefixing one is not a rename in a migration — it is
a data migration against production, coordinated with a deploy, for no
functional gain. Do not treat "the databases are separate now" as licence to
drop the prefix, and do not re-publish a vendor config in a way that resets a
table name to its colliding default. The inventory and the reasoning are in
"Every other table is WMS-owned, under a `wms_` prefix" below.

## `products` is WMS-owned; `uploads` still holds the store's data

This is the one thing that changed, and the two tables are no longer symmetric:

- **`products` belongs to this app.** WMS owns its schema and is its only
  writer. New migrations may alter it like any other WMS table — see "Migration
  conventions" below, which is where the old "never alter this table" rule used
  to live and no longer does.
- **`uploads` is still the store's data, and this app is still fully read-only
  on it.** Nothing here writes an `Upload`, and nothing should: its rows are
  authored by the store's own file management, and `Product::$thumbnail_img`
  merely points at them. `Upload` declares no `$connection`, so it reads the
  default one like every other model.

  **Open question, flagged rather than guessed:** now that the databases are
  separate, nothing in this repo populates or refreshes `uploads` — there is no
  uploads equivalent of `products:sync`, and no second connection pointing at
  the store. So it is not clear how the table stays current, or whether it is
  now a frozen copy taken at the split. Find out before relying on a newly
  uploaded product image appearing here; the symptom of a stale table is a
  `thumbnail_img` pointing at a row that does not exist, which surfaces as a
  null `image_url` rather than an error.

There is still no per-request create/update/delete of a `Product` anywhere in
the codebase, and there must not be: the one write path is the scheduled bulk
sync (see "Product sync" below), not a controller or job reacting to a WMS
action. That is now a design choice about where product data comes from rather
than a constraint imposed by another app's ownership — but it is still the rule.

`products` carries 72 columns inherited from the store's schema and `uploads`
eleven; only these are consumed here:

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

Do not widen a `select()` to `Product::all()` — the narrow column lists are what
keep this app insulated from the other ~65 columns it inherited and never reads.
That discipline outlived the shared database: those columns are still there,
still unread, and still free to change shape.

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

The matching itself combines a MySQL FULLTEXT search, added by
`add_fulltext_index_to_products_table` over `(name, ar_name)` — the one index
`Product::SEARCHABLE_NAME_COLUMNS` names — with the same `LIKE` search sqlite
runs alone. Five things it depends on, all covered by tests:

- **The two strategies are OR'd as whole strategies, not partitioned per
  word.** `Product::applyNameSearch()` builds a `MATCH (name, ar_name) AGAINST
  ('+w1* +w2*' IN BOOLEAN MODE)` expression from the term's *indexable* words
  (see below) and, independently, a `LIKE`-AND-of-every-word group identical to
  the sqlite path — the same words, including the ones excluded from the
  FULLTEXT expression. When an expression exists, the query is `WHERE
  (fulltext_expression) OR (like_and_group)`; a product matches if *either*
  strategy would, so the combined search is a strict superset of `LIKE` alone.
  When the term has no indexable word at all (every word excluded, or the
  driver has no FULLTEXT support), the query reduces to just the LIKE group,
  with no OR wrapper — the same shape sqlite always produces.
- **One `MATCH` over both columns, not one per column.** MySQL treats the two
  indexed columns as a single document, so each `+word` in the expression may
  land in either column (the across-columns OR) and every included `+word`
  must land somewhere (the per-word AND). Boolean mode is required —
  natural-language mode ranks rather than requires, so "every word matches"
  would silently become "any word matches", the same failure a flat
  `orWhere()` chain used to cause. MySQL resolves a `MATCH` only against an
  index covering *exactly* the clause's column list, so the index and that
  constant must change together or every search fails with errno 1191.
- **Three kinds of word are excluded from the FULLTEXT expression** — but,
  unlike the old per-word routing, this costs nothing: every word, excluded or
  not, still goes through the OR'd LIKE group. The exclusions exist only
  because boolean mode would otherwise silently return *nothing at all* for
  the whole expression rather than fewer rows: anything but letters and digits
  (MySQL's parser splits a word on every other character, so `+Wid-get*` could
  never match "Wid-get"), words shorter than `innodb_ft_min_token_size`
  (`Product::FULL_TEXT_MIN_WORD_LENGTH`), and InnoDB's default stopwords. Don't
  "simplify" these exclusions away — they protect the FULLTEXT branch only, the
  LIKE branch never needs them.
- **The exclusion list is also what makes the expression injection-proof.** It
  is built solely from alphanumeric words plus the `+` and `*` the scope adds,
  so a term containing boolean operators (`-`, `"`, `~`, `(`) can never reach
  the parser to invert or break the query — such a term simply has no
  indexable word, so the query falls back to the LIKE-only shape.
- **sqlite keeps a full `LIKE` path, and it is not optional.** Laravel's base
  query grammar throws outright on `whereFullText()`, and its base *schema*
  grammar throws on `compileFullText()` rather than leaving it unimplemented —
  so an unguarded `fullText()` breaks `migrate` itself, not just the search.
  CI and the test connection are sqlite, so both the migration and
  `Product::applyNameSearch()` guard on the driver — on sqlite there is no
  FULLTEXT expression, ever, so the LIKE group is the whole search, same as it
  always was. The MySQL path is pinned by asserting compiled SQL and bindings
  against the `mysql` grammar (Laravel resolves a query grammar without
  touching PDO, so `toSql()` needs no server).
- **An empty `ar_name` matches nothing**, which is what keeps a product the
  store never translated out of an English term's results through that column.

FULLTEXT still contributes one thing `LIKE` alone cannot: an indexable word
matches by **prefix** through the FULLTEXT branch ("Widg" finds "Widgets") in
addition to matching as an **infix** through the OR'd LIKE branch ("idget"
finds "Widget", the gap a purely FULLTEXT search would have had). The combined
search is therefore never narrower than sqlite's `LIKE`-only search, only ever
wider.

`Api\V1\CellController::index()` shares this via
`Product::applyNameSearch($productQuery->getQuery(), ...)` rather than a second
inlined copy: it takes the underlying `Query\Builder` because calling a
`#[Scope]` from inside `whereHas()` on a different model loses its generic type
under Larastan (see models.md). Conditions land identically either way.

**Rendering follows the locale, and the choice is made in Vue, not PHP.**
Every payload carrying a product name ships **both raw columns**, and
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
so its product column stays on the stable base `name` in both locales and
never emits `ar_name` at all. A test pins that; if the export ever gains
translated headers, revisit it as a whole rather than switching that one column.

`filterOptions()` and `selectedOptions()` are the only product payloads that
never pass through a Resource, so `Product::optionLabels()` maps them to plain
`{id, name, ar_name}` arrays instead of serialising models — that is what keeps
the emitted shape identical to the frontend's `ProductFilterOption`, and what
keeps the other 69 columns out of the response. Don't revert them to
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

`ar_name` is `varchar(191)` NOT NULL with an empty-string default. The NOT NULL
mirrors the store's original column; the default was added so a row inserted
without an Arabic name works, an existing database could take the column with no
backfill, and sqlite — which refuses a NOT NULL column added by `ALTER` without
a default — accepts the migration. All three still hold.

### `products.id` is a signed `int(11)`, not `bigint unsigned`

Inherited from the store's schema, which ended with `ALTER TABLE products MODIFY
id int(11) NOT NULL AUTO_INCREMENT`, and **still the live type** — owning the
table now does not retype a primary key that four foreign keys reference.
Laravel's `foreignId()` emits `bigint unsigned`, which MySQL will not accept as
a foreign key against a signed 32-bit int. Every column referencing a product is
therefore a plain `integer` with an explicit `foreign()` clause, not
`foreignId()->constrained()`:

- `pallets.product_id`
- `cell_status_logs.product_id`
- `cell_verification_reports.expected_product_id`
- `cell_verification_reports.reported_product_id`
- `wms_product_settings.product_id`

`uploads.id` is the same signed `int(11)`. sqlite reports both types as
`integer`, so the test connection cannot catch a regression here —
`CreateProductsTableTest` asserts it against the migration source instead. Keep
that assertion when adding a new product FK.

Retyping `products.id` to a bigint is a real option now that this app owns the
table, but it is a schema change against every FK above plus a production data
migration, and no test here can verify it (CI is sqlite, which ignores foreign
key types entirely). Treat it as its own piece of work, not a drive-by.

### `published`: the store admin's active/inactive toggle, not an occupancy signal

`products.published` (`int(11) NOT NULL DEFAULT 1`) is the store admin's
active/inactive kill switch for a product, arriving here through `products:sync`
and entirely independent of whether it currently occupies any cell in this
warehouse. `Admin\ProductController::index()`'s
`inactive` filter (`?inactive=true`) is `published = 0` — do not redefine
"inactive" as "occupies zero cells" (e.g. `whereDoesntHave('pallets')`): a
product can be `published = 0` while pallets of it still sit in cells (the
store deactivated it after it was already stocked), and a freshly `published
= 1` product legitimately has zero pallets before its first delivery. The two
concepts don't imply each other in either direction.

### `image_url` and `boxes_count` are not columns — both are derived

Neither exists in `products` under any name. They were once columns this app had
added to a table it did not then own, which is why `boxes_count` was guarded off
in production and therefore *absent* there while `PalletActionService` read it.
Both are attributes on `Product` instead, so the API and admin payloads keep the
exact keys their clients already consume.

Owning the table does not make re-adding them the right move: `image_url` is
derived from another table's row and would go stale the moment an upload
changed, and `boxes_count` is WMS configuration that has nothing to do with the
store's catalog. Keep them derived.

- **`image_url`** resolves `thumbnail_img` through the shared `uploads` table.
  `Upload::url()` returns `external_link` verbatim when set (the store uses it
  for files already on a CDN), otherwise joins `file_name` to
  `config('store.asset_base_url')`. That config is the store app's public base
  URL — this app is served from a different host and cannot derive it. Unset,
  the URL resolves to `null` rather than to a relative path that would 404
  against this app's own domain.
- **`boxes_count`** lives in `wms_product_settings`, keyed on the product id. A
  product the sync has created but this app has never configured has no row, and
  falls back to `Product::DEFAULT_BOXES_COUNT`. There is no inherited column to
  backfill this from: `products.unit_equal` reads like a units-per-carton value
  and was the obvious candidate, but it has been checked with the store's owners
  and is **not** the box count — do not wire it up. The only write path is `Admin\ProductController::updateBoxCount()`,
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

### Product sync: the only writer of `products`

`products:sync` (`app/Console/Commands/SyncProductsCommand.php`, scheduled
hourly in `routes/console.php`) polls the Otajer store's REST product feed
(URL in `config('store.products_sync_url')`, env `STORE_PRODUCTS_SYNC_URL` —
the store's API key is embedded in that URL's path, so it is never
hardcoded) and upserts exactly three columns — `name`, `ar_name`,
`published` — keyed on `id`.

This app owns `products` and this command is its **only** writer. Nothing else
in the codebase creates, updates or deletes a product row, and the store app no
longer writes these columns either — so the feed is the single source of truth
for a product's name, Arabic name and published state, and a WMS-side edit to
any of the three would simply be overwritten on the next run. If product names
ever need to be editable in the admin panel, that is a real design decision
about which side wins, not a small feature: raise it rather than adding a second
writer.

Three things worth knowing before touching this:

- **`Mat_ID` is assumed to equal `products.id`.** The feed returns it as a
  numeric string (e.g. `"100027"`); the command casts it to int and uses it
  as the upsert key. If that assumption is ever wrong for some product
  range, the symptom is silent — a sync either creates a phantom row at the
  wrong id or overwrites an unrelated one, since nothing else cross-checks
  the mapping.
- **The feed always wins, and it creates rows as well as updating them.** The
  upsert is unconditional: there is no last-write-wins timestamp comparison, so
  whatever the feed currently says replaces what is in the table, and a product
  id the feed reports that this app has never seen is inserted. A product
  deleted from the feed is *not* deleted here — nothing prunes rows.
- **Everything else in the feed is intentionally dropped.** Price tiers,
  tax, barcodes, unit/class ids, and the image are not mapped to any
  column — the same "narrow column list" discipline as the rest of this
  file. A row with no usable `Mat_ID` or `enName` is skipped rather than
  written with a guessed value.

## Every other table is WMS-owned, under a `wms_` prefix

The table below is history now that the databases are separate — no name here
collides with anything today. It is kept because it is the record of *why* each
prefix exists, and because "eight of these were live collisions" is the honest
answer to a future "can we drop the prefix?". The answer is still no, for the
reason in "The `wms_` prefixes stay" above: these are the live names in every
existing database, and changing one is a production data migration.

| Table | Was a live collision? | Why it was never shared |
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
they were specific enough to this domain that the store app had nothing like
them — verified name-by-name against the store's 107 tables at the time.
`tests/Feature/SharedDatabaseTableNamesTest.php` pins that inventory, and still
should: it is what keeps the prefixed and bare sets from drifting by accident.
A new bare-named table fails the suite until it is added there. Telescope,
Pulse and Health are unaffected — they run on their own sqlite connections (see
config.md), never in this app's main database.

Where a table name is config-driven, the prefix lives in config, not in the
migration: `config/permission.php`'s `table_names`, `config/session.php`,
`config/auth.php`, `config/cache.php`, `config/queue.php`,
`config/database.php`'s `migrations.table`. Re-publishing any of those vendor
configs resets them to the colliding defaults —
`SharedDatabaseTableNamesTest` is the guard against that. Sanctum has no such
config key, so `App\Models\PersonalAccessToken` overrides `$table` and
`AppServiceProvider` registers it via `Sanctum::usePersonalAccessTokenModel()`.

## Migration conventions

**Create the final table name.** A create migration emits the `wms_` name
directly — never the bare name plus a later rename. The original reason was that
a fresh production migrate would hit the store's existing bare-named table; the
reason it still holds is that every existing database already carries the
prefixed name, so a create-then-rename pair is two migrations doing what one
should, with a window in between where the name is wrong.

**`products` takes ordinary migrations now — do not copy the old guard onto
one.** This is the rule that changed, and it changed in the direction that
matters: a migration altering `products` should just alter it.
`add_fulltext_index_to_products_table` is the worked example — it guards on the
*driver* (sqlite cannot build a FULLTEXT index) and on `hasIndex()` for
idempotency, and on nothing else.

The trap to know about, because it is subtle and it cost a full investigation:
the old convention wrapped `up()` in `if (Schema::hasTable('products')) {
return; }`. In production that table always exists, so a migration written that
way is a **guaranteed no-op exactly where it matters**, silently. Anything that
needs to reach production `products` — an index, a column, a type change — must
not be written that way. `create_products_table` still carries the guard. Leave it: it is
already recorded in every existing database so it does not re-run there, and on
a fresh install the table does not exist yet so the guard passes and the table
is created. It is effectively vestigial and harmless — but it is also the thing
that will mislead the next person, so read it as "this create is a no-op if the
table is somehow already there", not as a pattern to follow.

**`uploads` is still guarded, because this app still does not own it.**
`create_uploads_table` is the remaining worked example: `hasTable()` on `up()`
makes it a no-op wherever the real table is present, while still building a
local stand-in for development and testing; `down()` cannot use `hasTable()` (it
cannot tell a real table from a stand-in), so it guards on
`app()->isProduction()` — a rollback drops the development copy and never real
data. Do not use `isProduction()` on `up()`: that was an even earlier guard, and
it breaks any non-production environment pointed at real data.

**A rename migration checks both ends.** `rename_users_table_to_wms_users` and
`rename_colliding_tables_to_wms_prefix` both rename only when the legacy name
exists *and* the target does not. The second half was the safety property that
mattered in the shared database, where every legacy name on that list was the
store app's table. Both migrations are no-ops on a fresh install and exist only
for databases migrated before the prefix landed — leave them alone rather than
tidying them away; a database old enough to need them may still be out there.

**Editing an already-run migration needs a companion upgrade migration.** This
one is unchanged by the ownership move and is the rule most often forgotten.
Rewriting `create_products_table` changed nothing on staging or an older local
checkout: the migration was already recorded, so it never re-ran, and those
databases kept the pre-alignment columns while the code moved on.
`upgrade_legacy_products_stand_in` is the fix, and the pattern for the next one —
identify the old shape by a column the current one cannot have
(`products.image_url` was only ever this app's), carry any data worth keeping
into its new home, then drop it.

`add_ar_name_to_products_stand_in` is the same pattern for a pure column
addition, and the simpler case to copy: adding `ar_name` to
`create_products_table` covers fresh installs only, so the companion migration
guards on `Schema::hasColumn('products', 'ar_name')` and no-ops wherever the
column already exists, while bringing an older database across. Always add both
halves; editing the create migration on its own is what left staging stale last
time.

Note the naming: the `_stand_in` suffix on these two is a leftover from when a
local `products` was a *stand-in* for the store's real table. The migrations are
already recorded under those filenames, so they cannot be renamed — read them as
"upgrade legacy products" and "add ar_name to products", and do not use the
suffix on anything new.

That migration deliberately leaves such a database's `id` as `bigint unsigned`
rather than retyping it to `int(11)`, because altering a primary key referenced
by four foreign keys is risky and sqlite cannot verify the result. The
consequence is worth knowing: on those databases
`wms_product_settings.product_id` (signed `int`) references a `bigint unsigned`
key. sqlite does not type-check foreign keys so it is inert there, but **MySQL
rejects it** — `create_wms_product_settings_table` would fail with errno 3780.
Such a database must therefore never be moved onto MySQL in place; rebuild it
with `migrate:fresh`. Note that no test can catch this
class of defect: CI runs sqlite, which ignores foreign key types entirely.

**The migration repository cannot rename itself.** Laravel reads
`database.migrations.table` before running anything, so pointing it at
`wms_migrations` makes an existing WMS database look unmigrated and re-run
everything. This is the concrete reason "just un-prefix the tables" is not a
migration you can write: the repository table is the one that has to be renamed
out of band — `RENAME TABLE migrations TO wms_migrations;` — or the database
rebuilt with `migrate:fresh`.

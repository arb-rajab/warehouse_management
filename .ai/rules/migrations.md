---
paths:
  - 'database/migrations/**'
  - database/migrations/2026_08_08_154136_create_products_table.php
---

# Migrations

## Every migration needs a matching test file in tests/Feature/Database/
When adding, editing, or dropping a migration, add or update a test file in `tests/Feature/Database/` named after that migration (strip the timestamp, StudlyCase the rest — e.g. `2026_08_08_154138_create_cells_table.php` → `CreateCellsTableTest.php`). One file per migration, not one shared file — each migration's constraints live and evolve with their own test file. A pure column addition/rename with no new constraint (e.g. a migration only adding a plain nullable column) doesn't need its own file.

Don't just assert a column/table exists — assert the behavior the migration encodes actually holds under RefreshDatabase + sqlite (`foreign_key_constraints` is on):
- New unique constraint: creating a second row that collides throws `Illuminate\Database\QueryException`.
- `cascadeOnDelete`: deleting the parent deletes the children (plus an unrelated "noise" parent whose children survive).
- `restrictOnDelete`: deleting the referenced row throws `QueryException`, and the referenced row still exists afterward.
- `nullOnDelete`: deleting the referenced row nulls the FK column on `fresh()`.
- No FK constraint by design (e.g. `cell_status_logs.pallet_id`): deleting the referenced row does NOT throw, and the raw column value survives even though the Eloquent relation now resolves null.
- Table/column dropped: `Schema::hasTable()` / `Schema::hasColumn()` is false.

Model relationship tests (`tests/Feature/Models/*Test.php`) cover normal relation resolution — they don't double as constraint tests, so both are needed when a migration adds a relation with a delete rule.

## A new table also has to be registered in SharedDatabaseTableNamesTest
`tests/Feature/SharedDatabaseTableNamesTest.php` asserts the **exact** sorted list of every bare-named (non-`wms_`) table, so creating one breaks that test by design — the failure is the prompt to justify the name, not a bug. Add the name to that list in the same change, in `sort()`'s byte order (`_` sorts before letters, so `cell_verification_round_row` precedes `cell_verification_rounds`), and make sure it earns a bare name per the section below. Pest can't run locally, so missing this costs a full CI round every time; `Schema::getTableListing(schemaQualified: false)` through `php artisan tinker` after migrating prints the expected list directly.

## Two tables are shared, and most default table names are collisions
In production this app shares one MySQL database with a store app that owns
`products` and `uploads` and would collide with this app on `users`, `sessions`, `cache`,
`jobs`, `personal_access_tokens`, the Spatie permission tables and `migrations`
itself. Every table this app owns therefore carries a `wms_` prefix, `products`
and `uploads` are read-only and guarded with `Schema::hasTable()` on `up()` /
`app()->isProduction()` on `down()`, and every `product_id` FK is a signed
`integer` rather than `foreignId()`. Read **.ai/rules/shared-database.md** before adding a migration —
it carries the full table inventory, the guard conventions, and the products
column mapping.

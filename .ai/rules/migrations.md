---
paths:
  - 'database/migrations/**'
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

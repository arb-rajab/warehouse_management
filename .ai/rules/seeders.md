---
paths:
  - 'database/seeders/**'
---

# Seeders

## One seeder per model, called in dependency order from DatabaseSeeder
Each seeder owns exactly one model and is registered in DatabaseSeeder::run()'s `$this->call([...])` list in dependency order (Product → Row → Pallet → CellStatusLog). Seeders that depend on another table's data must early-`return` when that table is empty (see PalletSeeder, CellStatusLogSeeder) rather than assuming seed order. Prefer `Model::create()` with fixed, meaningful values when the exact seed data matters (RowSeeder); use factories when realistic random data is enough.

## A seeder that writes demo or test accounts refuses production itself
`db:seed --class=X --force` skips DatabaseSeeder, so its production guard does not cover X. Any seeder that creates accounts (a known factory password, `store-admin@example.com`, mobile users) uses `Database\Seeders\Concerns\RefusesProduction` and calls `$this->refuseInProduction()` first in `run()`: it throws a `RuntimeException` before anything is written. Today: DatabaseSeeder, StoreUserSeeder, CellStatusLogSeeder, CellVerificationRoundSeeder and StagingRealisticDataSeeder; `tests/Feature/Database/SeedersRefuseProductionTest.php` has a dataset to extend. StoreRoleSeeder is the exception: it creates the Super Admin role in every environment (the first-admin recipe needs it) and the `Clerk` demo role only in `local` and `testing`. Playwright seeds with `APP_ENV=testing`, so e2e is unaffected.

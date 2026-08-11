---
paths:
  - 'database/seeders/**'
---

# Seeders

## One seeder per model, called in dependency order from DatabaseSeeder
Each seeder owns exactly one model and is registered in DatabaseSeeder::run()'s `$this->call([...])` list in dependency order (Product → Row → Pallet → CellStatusLog). Seeders that depend on another table's data must early-`return` when that table is empty (see PalletSeeder, CellStatusLogSeeder) rather than assuming seed order. Prefer `Model::create()` with fixed, meaningful values when the exact seed data matters (RowSeeder); use factories when realistic random data is enough.

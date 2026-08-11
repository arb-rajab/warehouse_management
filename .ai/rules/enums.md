---
paths:
  - 'app/Enums/**'
---

# Enums

## String-backed enums, unwrap with ->value before JSON
Enums are plain `enum X: string` with PascalCase cases and snake_case values matching DB columns — no methods, labels, or interfaces (see CellState, CellLogAction, Locale). Models cast them via `protected function casts(): array` (e.g. Cell::casts(), CellStatusLog::casts()), never a `$casts` property. Resources must unwrap to the raw value (`$this->state->value`) before returning — never leak the enum object itself into JSON.

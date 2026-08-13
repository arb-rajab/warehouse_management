---
paths:
  - 'app/Enums/**'
---

# Enums

## String-backed enums, unwrap with ->value before JSON
Enums are plain `enum X: string` with PascalCase cases and snake_case values matching DB columns — no methods, labels, or interfaces (see CellState, CellLogAction, Locale). Models cast them via `protected function casts(): array` (e.g. Cell::casts(), CellStatusLog::casts()), never a `$casts` property. Resources must unwrap to the raw value (`$this->state->value`) before returning — never leak the enum object itself into JSON.

## Get an enum's raw values with array_column(Cases::cases(), 'value')
To get all values of a string-backed enum as a plain array (e.g. for an Inertia filterOptions list or a supported-locales list), use `array_column(EnumClass::cases(), 'value')` — not `array_map(fn ($c) => $c->value, EnumClass::cases())`. Backed enum cases expose `value` as a public property, so `array_column` works directly and is shorter. See `CellController`, `CellStatusLogController`, `SetLocaleFromHeader`.

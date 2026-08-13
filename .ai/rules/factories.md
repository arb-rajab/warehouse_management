---
paths:
  - 'database/factories/**'
---

# Factories

## Enum fields use the case directly; suppress observers with withoutEvents
Factories set enum fields to the case itself (`'state' => CellState::Empty`), not `->value`. When a factory's parent model has an observer with side effects the factory doesn't want (e.g. Row's cell-grid generation), wrap that parent's creation in `Row::withoutEvents(fn () => ...)` — see CellFactory — with a comment explaining why. Domain-state transitions belong in named factory states / `configure()->afterCreating()` (see PalletFactory::opened()), not ad hoc overrides at the call site.

## PalletFactory::stale() is the concrete example of the domain-state-factory rule
`Pallet::factory()->stale()` backdates `created_at` past `Pallet::STALE_AFTER_DAYS` via `afterCreating()` (same technique as `opened()`: `forceFill()` + `save()`, since `created_at` isn't mass-assignable). Use it instead of `backdate(Pallet::factory()->create(), now()->subDays(Pallet::STALE_AFTER_DAYS)->subMinute()->toDateTimeString())` — that exact call was duplicated across 4 test files before this state was extracted.

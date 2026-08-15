---
paths:
  - 'database/factories/**'
  - database/factories/PalletFactory.php
---

# Factories

## Enum fields use the case directly; suppress observers with withoutEvents
Factories set enum fields to the case itself (`'state' => CellState::Empty`), not `->value`. When a factory's parent model has an observer with side effects the factory doesn't want (e.g. Row's cell-grid generation), wrap that parent's creation in `Row::withoutEvents(fn () => ...)` — see CellFactory — with a comment explaining why. Domain-state transitions belong in named factory states / `configure()->afterCreating()` (see PalletFactory::opened()), not ad hoc overrides at the call site.

## PalletFactory::stale() is the concrete example of the domain-state-factory rule
`Pallet::factory()->stale()` backdates `created_at` via `afterCreating()` (same technique as `opened()`: `forceFill()` + `save()`, since `created_at` isn't mass-assignable) instead of hand-rolling a `backdate()` call at the call site — that exact duplication was the reason this state was extracted in the first place. It backdates by a fixed, generously-old 30 days — comfortably past both the admin/dashboard's fixed `Pallet::STALE_AFTER_DAYS` constant and any reasonable caller-chosen day count passed to the mobile API's `Pallet::isStaleAfter()` (see `.ai/rules/models.md`) — instead of hand-rolling a threshold-specific backdate per call site.

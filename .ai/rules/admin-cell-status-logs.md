---
paths:
  - 'app/Http/Requests/Concerns/FiltersCellStatusLogs.php,app/Models/CellStatusLog.php,resources/js/pages/Admin/CellStatusLogs/Index.vue'
---

# Admin Cell Status Logs

## CellStatusLog created_within_days is a mutually-exclusive alternative to date_from/date_to
`created_within_days` (nullable|integer|min:1) is an alternative way to express the same date range as `date_from`/`date_to`, not a third independent constraint — the two are mutually exclusive by design, not additive.

Backend: enforced via `'created_within_days' => [..., 'prohibits:date_from,date_to']` in `FiltersByDateRange::dateRangeFilterRules()`, composed by `FiltersCellStatusLogs::cellStatusLogFilterRules()` — sending both is a validation error (422/session error), not silently ANDed. `CellStatusLog::filtered()` delegates to `matchingHistoryFilters()`, where `created_within_days` maps to `whereDate('created_at', '>=', now()->subDays($days))`, i.e. exactly what `date_from` would do if set to that computed date — no upper bound, since it's meant to always reach "now".

Frontend: Index.vue uses `createdDateRangeExclusivity()` from `resources/js/lib/filters.ts`. A value in `created_within_days` disables the empty `date_from`/`date_to` fields; a value in either date field disables the empty `created_within_days` field. The active mode stays editable so the user can clear it to switch modes. `DateRangeFilterFields` receives the resulting `dateRangeDisabled`/`createdWithinDaysDisabled` values.

Note: `<input type="number">` bound via plain `v-model` auto-casts to a JS number on user input (Vue's `castToNumber` runtime behavior for `type="number"`), even though the reactive filter starts as a string (`''` or `props.value?.toString()`) — expect `filters.created_within_days` to be `string | number` in practice, and don't assert a fixed type in tests.

## expires_within_days mirrors created_within_days's mutual-exclusion pattern
`expires_within_days` (nullable|integer|min:1) is the expiration-range counterpart to `created_within_days`: it's an alternative way to express `expiration_date_from`/`expiration_date_to`, not a third independent constraint — `'expires_within_days' => [..., 'prohibits:expiration_date_from,expiration_date_to']` in FiltersCellStatusLogs. In `CellStatusLog::filtered()` it's applied inside the same `whereHas('pallet', ...)` closure as the expiration date-range filters, mapping to `where('expiration_date', '<=', now()->addDays($days))`. Frontend: Index.vue uses `exclusivePair()` to disable the expiration date-range fields while `expires_within_days` has a value and vice versa, same as the created_within_days/date_from/date_to pair.

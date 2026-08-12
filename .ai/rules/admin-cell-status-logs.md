---
paths:
  - 'app/Http/Requests/Concerns/FiltersCellStatusLogs.php,app/Models/CellStatusLog.php,resources/js/pages/Admin/CellStatusLogs/Index.vue'
---

# Admin Cell Status Logs

## CellStatusLog created_within_days is a mutually-exclusive alternative to date_from/date_to
`created_within_days` (nullable|integer|min:1) is an alternative way to express the same date range as `date_from`/`date_to`, not a third independent constraint — the two are mutually exclusive by design, not additive.

Backend: enforced via `'created_within_days' => [..., 'prohibits:date_from,date_to']` in `FiltersCellStatusLogs::cellStatusLogFilterRules()` — sending both is a validation error (422/session error), not silently ANDed. In `CellStatusLog::filtered()`, `created_within_days` maps to `whereDate('created_at', '>=', now()->subDays($days))`, i.e. exactly what `date_from` would do if set to that computed date — no upper bound, since it's meant to always reach "now".

Frontend: Index.vue disables each side while the other has a value (`dateRangeDisabled`/`createdWithinDaysDisabled` computed off the other field's emptiness) rather than just letting the last-edited one win — this mirrors the backend's mutual exclusivity in the UI instead of allowing a state the backend would then reject. Only the field(s) currently holding a value are ever disabled; the active field stays editable so the user can clear it to switch modes.

Note: `<input type="number">` bound via plain `v-model` auto-casts to a JS number on user input (Vue's `castToNumber` runtime behavior for `type="number"`), even though the reactive filter starts as a string (`''` or `props.value?.toString()`) — expect `filters.created_within_days` to be `string | number` in practice, and don't assert a fixed type in tests.

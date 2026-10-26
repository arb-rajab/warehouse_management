---
paths:
  - 'resources/js/pages/Admin/Rows/Show.vue'
  - 'resources/js/components/CellSlot.vue'
  - 'resources/js/components/CellHighlightFilters.vue'
  - 'resources/js/lib/cellStatus.ts'
  - 'resources/js/lib/cellHighlight.ts'
---

# Warehouse cell highlights and expiry predicates

## One highlight pipeline

`Rows/Show.vue` renders the cell grid through `CellSlot.vue` and uses
`CellHighlightFilters.vue` plus `cellHighlight.ts` for matching. Date/state
predicates (`isCellExpiringWithin`, `isCellExpired`, `isCellStale`) live in
`cellStatus.ts`; components reach them through `matchesCellHighlight`, rather
than importing predicates directly. Keep `Pallet::matchingHighlightFilters()`
server-side matching in lockstep with these client-side predicates.

The loaded grid is matched client-side; do not add `router.get` to compute the
highlight. The separate matching-pallets list applies the same parameters on the
server. Filter state belongs to the parent's `reactive()` object: the child
mutates nested values and clears with `Object.assign`, without emitting
`update:modelValue`. Pass `:model-value`, not `v-model` or a plain parent ref.

## Filter contracts

State is multi-select (`Cell['state'][]`, empty array means no filter) and uses
`FilterMultiSelect`; empty/count/match helpers use `.length`/`.includes()`.
`isCellExpiringWithin` matches today through N days ahead and excludes overdue
pallets; use `isCellExpired`/the `expired` flag for overdue stock. The dashboard's
expired tile sends `expired: true`, never `expires_within_days=0`. GET booleans
need normalization before Laravel's boolean validation (see [requests](requests.md)).

Matching functions accept structural `MatchableCell` (state plus the required
pallet fields), not full `Cell`, so lighter shapes can reuse the predicates.

## Emphasis requires an applied filter

An explicitly matched cell gets the blue highlight ring. Do not restore the
retired independent expiry-window mechanism (`ExpiringDaysInput`, `expiringDays`,
`expiringSoonUntil`) or automatic expiry borders, badges, colored expiration
text, or a separate staleness badge. A cell matching multiple filters uses the
same ring, avoiding overlapping indicators.

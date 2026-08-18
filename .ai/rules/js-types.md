---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/mapViewport.ts,app/Http/Controllers/Admin/CellController.php,resources/js/types/admin.ts'
---

# Js Types

## Cells map has next/previous-match navigation, superseding the prior removal note
The old "no jump-to-next-expired-pallet" removal (see the search/rotate note in this area) was deliberately reversed: Cells/Index.vue now has next/previous buttons next to the total-match-count that cycle through every cell matching the active highlight filters, warehouse-wide (across all flats), in map-reading order (row order as in `rows`, then cell_number, then flat_number) — see `orderedMatches`/`focusMatchAt`/`jumpToNextMatch`/`jumpToPreviousMatch`.

`CellHighlightSample` (types/admin.ts) and `CellController::cellHighlightSamples()` now also carry `row_letter`/`cell_number` (not just flat_number/state/pallet) so the frontend can order/locate matches, not just count them per flat.

Jumping to a match on the current flat just recenters/pulses locally (`pulseAndCenterLabel`, shared with the existing search-jump watcher). Jumping to a match on a different flat reuses the existing location-search machinery — it does `router.get` with `search: formatSlot(row_letter, cell_number, flat_number)` (which `CellController::resolveLocationSearch` already parses) instead of adding a new endpoint/query param, so the page reloads onto that flat and the existing `jumpToCell` pulse-watcher picks it up. Don't reintroduce a second/parallel jump mechanism — build on this one.

---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/cellHighlight.ts,resources/js/lib/cellStatus.ts,app/Http/Controllers/Admin/CellController.php'
---

# Controllers Admin

## Cell map flat tabs show a per-flat highlight-match count via cellHighlightSamples
Dashboard stat tiles (occupancy + expiring) count warehouse-wide, but Cells/Index.vue only ever renders one flat at a time — clicking a tile used to land you on flat 1 with no indication the other N matches lived on other flats. Fixed by having `CellController::index()` also return `cellHighlightSamples` (ALL cells, not just the current flat — `flat_number`, `state`, and a minimal `pallet` shape via `Pick<CellPallet,'product_id'|'expiration_date'|'added_at'>`), which Cells/Index.vue reduces client-side with the same `matchesCellHighlight()` used for cell ring-highlighting to render a per-flat count badge on each flat tab (only when a highlight filter is active). This keeps the badge total, summed across flats, identical to the dashboard number by construction (same predicate, same helper function) instead of needing a second server-side counting query to stay in sync.

To support the narrower sample shape without duplicating the matching logic, `isCellExpired`/`isCellExpiringWithin`/`isCellStale` (cellStatus.ts) and `matchesCellHighlight` (cellHighlight.ts) take a structural `MatchableCell` type (state + a `Pick`'d pallet) instead of the full `Cell` — a real `Cell` still satisfies it. Keep matching functions accepting `MatchableCell`, not `Cell`, if you add another lightweight cell-like shape later.

Don't scope `cellHighlightSamples` by product/flat query params — it's intentionally unfiltered so every highlight-filter dimension (including deep-linkable ones like expiresWithinDays/staleAfterDays and client-only ones like multi-select state) can be matched purely client-side against it, consistent with the existing "matching computation is never wired into router.get" rule in lib.md. `staleAfterDays` is deep-linkable from the dashboard the same way `expiresWithinDays` is (see lib.md/dashboard.md) — "client-only" here describes where the *matching* runs, not which seed values can arrive via URL.

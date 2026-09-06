---
paths:
  - 'resources/js/pages/Admin/CellStatusLogs/Index.vue,resources/js/pages/Admin/Users/Show.vue,resources/js/lib/cellStatusLogDisplay.ts,resources/js/pages/Admin/CellVerificationRounds/Show.vue,resources/js/lib/cellVerificationReportDisplay.ts,resources/js/components/CellVerificationCorrectnessBadge.vue'
---

# Users Js Lib

## cellStatusLogDisplay.ts is the shared CellStatusLog display logic; row markup stays duplicated per page
Admin/Users/Show.vue (a per-user action-history table, linked from Users/Index.vue's "View" action) is now the second consumer of the transfer-pair-merging and flag-label logic that used to live only in CellStatusLogs/Index.vue. That logic (mergeTransferPairs, transferPair, cellLogActionLabel, flagReasonLabel, the DisplayCellStatusLog type) was extracted into resources/js/lib/cellStatusLogDisplay.ts — both pages import from there instead of redefining it. The from/to state label itself uses `cellStateLabel` from `resources/js/lib/cellStateColor.ts` (the canonical cell-state module, shared with the map components) rather than a duplicate helper in cellStatusLogDisplay.ts.

The `<td>` row *markup* itself is intentionally still duplicated between the two pages (not factored into a shared row component) because this project's Vue components must have a single root element — a component whose template is a flat list of sibling `<td>`s would violate that (no wrapper element is legal directly inside a `<tr>`). Every other admin listing (Rows/Index, Users/Index, Products/Index, CellStatusLogs/Index) already defines its own inline `#row` template for the same reason — there is no shared-row-component precedent in this codebase. If a third page needs the same row shape, don't reach for a component to hold the `<td>`s; keep extracting only the non-template logic.

Users/Show.vue's table intentionally drops the "Done By" column (redundant — the whole page is already scoped to one user). It does include the Acknowledge-flag button, sharing `hasUnacknowledgedFlags`/`acknowledgeFlags` from `cellStatusLogDisplay.ts` with CellStatusLogs/Index.vue — `acknowledgeFlags(log, 'user')` passes the `returnTo` so the backend redirects back to this page instead of the cell log index (see controllers.md's CellStatusLogController::acknowledgeFlags note). Don't reimplement the button/handler locally; extend the shared helper if a third page needs it.

## Users/Show.vue renders two independently paginated tables
Alongside the actions (CellStatusLog) table, Users/Show.vue also lists the same user's CellVerificationReport rows (`UserController::show` queries both, scoped by `user_id`, unfiltered — reports are just sorted newest-first via `CellVerificationReport::sorted()`). Same "drop the redundant scoping column" rule as the actions table: the reports table has no "worker" column since the whole page is already scoped to one user.

Two paginators on one page collide on the `page` query param unless given distinct names — the reports paginator uses `paginate($perPage, ['*'], 'reports_page')` and its own `reports_per_page` filter key (`Controller::resolvePerPage` takes an optional `$key` argument for this). `Users/Show.vue`'s `onPerPageChange`/`onReportsPerPageChange` each spread the *other* table's current filter value into the request so changing one table's page size doesn't reset the other's back to the default — don't replace that spread with a single-key payload like the old one-table version had.

The reports table reuses `resources/js/lib/cellVerificationReportDisplay.ts`'s `snapshotProductLabel()` and `resources/js/components/CellVerificationCorrectnessBadge.vue` — both extracted from `CellVerificationRounds/Show.vue` once this page became a second consumer of the same expected/reported-snapshot and correct/incorrect-badge logic. A third page needing either should import from there, not redefine them.

Each report row links its round (`showCellVerificationRound({ cellVerificationRound: report.cell_verification_round_id })`) and its cell (`showRow({ letter: report.cell.row_letter })`, same as the actions table already does) via `TableLink` — see pages.md's rule on linking every related-resource value to its own admin page.

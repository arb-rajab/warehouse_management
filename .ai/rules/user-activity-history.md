---
paths:
  - 'app/Http/Controllers/Admin/UserController.php'
  - 'app/Http/Requests/Admin/ShowUserRequest.php'
  - 'resources/js/pages/Admin/Users/Show.vue'
  - 'resources/js/pages/Admin/CellVerificationRounds/Show.vue'
  - 'resources/js/lib/cellVerificationReportDisplay.ts'
  - 'resources/js/components/CellVerificationCorrectnessBadge.vue'
---

# User action history and verification-report pagination

## Independently paginated tables

Alongside the actions (CellStatusLog) table, Users/Show.vue also lists the same user's CellVerificationReport rows (`UserController::show` queries both, scoped by `user_id`, unfiltered — reports are just sorted newest-first via `CellVerificationReport::sorted()`). Same "drop the redundant scoping column" rule as the actions table: the reports table has no "worker" column since the whole page is already scoped to one user.

Two paginators on one page collide on the `page` query param unless given distinct names — the reports paginator uses `paginate($perPage, ['*'], 'reports_page')` and its own `reports_per_page` filter key (`Controller::resolvePerPage` takes an optional `$key` argument for this). `Users/Show.vue`'s `onPerPageChange`/`onReportsPerPageChange` each spread the *other* table's current filter value into the request so changing one table's page size doesn't reset the other's back to the default — don't replace that spread with a single-key payload like the old one-table version had.

The reports table reuses `resources/js/lib/cellVerificationReportDisplay.ts`'s `snapshotProductLabel()` and `resources/js/components/CellVerificationCorrectnessBadge.vue` — both extracted from `CellVerificationRounds/Show.vue` once this page became a second consumer of the same expected/reported-snapshot and correct/incorrect-badge logic. A third page needing either should import from there, not redefine them.

Each report row links its round (`showCellVerificationRound({ cellVerificationRound: report.cell_verification_round_id })`) and its cell (`showRow({ letter: report.cell.row_letter })`, same as the actions table already does) via `TableLink` — see pages.md's rule on linking every related-resource value to its own admin page.

Shared action-row rendering and acknowledgement are documented in [cell-log display](cell-log-display.md).

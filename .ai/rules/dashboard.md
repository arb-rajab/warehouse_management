---
paths:
  - resources/js/pages/Admin/Dashboard/Index.vue
---

# Dashboard

## Dashboard's custom-expiring-days field lives behind a FilterDialog, not a live `@change` input
`#dashboard-custom-expiring-days` used to be a bare `type="number"` input wired to `@change`, which fired a `router.get` on every native spinner click — rapid increment/decrement clicks each canceled the in-flight request from the previous click. It's now a `filterTriggerButtonClass` button (`expiringWindow.label`, SlidersHorizontal icon) that opens a `FilterDialog` containing a `FilterNumberField` (label `cellHighlight.expiresWithinDays`, reused rather than adding a near-duplicate string) and a `filterApplyButtonClass` submit button (`expiringWindow.apply`) — same "closed by default, edits don't reload until Apply" pattern as CellStatusLogs' filter dialog (see pages-admin-cell-status-logs.md). `submitCustomExpiringDays()` re-seeds the draft from `props.stats.expiring.custom.days` each time the dialog opens (`openCustomExpiringDaysDialog()`) and only calls `router.get` on form submit, then closes the dialog. Don't reintroduce a live `@change`/`@input` handler on this field — any request must be gated behind the dialog's explicit submit.

---
paths:
  - resources/js/pages/Admin/Products/Index.vue
  - app/Http/Controllers/Admin/ProductController.php
---

# Admin Products

## Mutations from the products page must redirect to `admin.products.index`

Never use `back()` in `ProductController` or any controller action reachable only from the products page. Two reasons apply app-wide:

1. `Referrer-Policy: no-referrer` strips the Referer header, so Laravel's `back()` has no URL to return to.
2. Inertia SPA visits don't update the session's "previous URL" — it stays frozen at whatever the last full browser page load was (typically the dashboard after login). `back()` therefore lands on the dashboard, not the products page.

The fix (already applied to `updateBoxCount`) is to redirect explicitly: `redirect()->route('admin.products.index', $request->query())`. The same pattern is documented in `RedirectsAfterCellAction` (for pallet actions) and `CellStatusLogController::redirectAfterAcknowledge()` (for flag acknowledgements).

## Box count is edited via FilterDialog, not a live input

The "boxes per pallet" column renders the stored count as a trigger `<button>` (styled with `filterTriggerButtonClass`). Clicking it opens a `<FilterDialog>` containing a `<FilterNumberField>` and an explicit submit button. On submit the component calls `router.patch` with `{ boxes_count }`.

Do not reintroduce a live `@change` / `@input` handler on an `<input>` directly in the table row — that sends a round trip on every arrow-key press. The dialog pattern (one explicit submit) is the correct shape.

State is managed with two refs: `boxCountDialogProduct` (`ProductSummary | null`) and `boxCountDraft` (string). A computed writable ref (`boxCountDialogOpen`) bridges the nullable ref to the boolean `v-model:open` that `FilterDialog` requires.

## Products table hides redundant sibling filter icons
Full/Opened/Expired/Expiring-soon share one `occupancy` filter popover, and Activity Today/This week share one `activity` popover — tapping any sibling's icon opens the identical popover. Only the "primary" column in each group (Full, Activity Today) always shows its filter icon (`DataTable.vue`'s `filterIconAlwaysVisible` defaults to `true`); the other siblings (Opened, Expired, Expiring-soon, Activity Week) set `filterIconAlwaysVisible: false` so their icon only appears once that group's shared `filtered` computed is actually true. Product has no sibling, so it stays always-visible. When adding a new column that shares a `filterKey` with an existing one, default the new one to `filterIconAlwaysVisible: false` unless it's meant to be the group's advertised entry point.

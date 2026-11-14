---
paths:
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/components/StoreListFilters.vue'
  - 'resources/js/components/StoreListFilters.test.ts'
---

# Store admin list filters stay compact

## Only search plus one key filter is permanent
On a Store admin list page (`resources/js/pages/Store/Admin/**/Index.vue`) the inline filter bar shows only the search field and at most one key filter (the one staff use most, such as status or seller), plus per-page/sort controls. Every other filter goes into a "Filters" dialog opened from a trigger button with an active-count badge, exactly like the WMS pages (`Admin/Products/Index.vue`: `FilterDialog`, `FilterDialogFooter`, `filterTriggerButtonClass`, `countBadgeClass`). A list with two or fewer filters in total needs no dialog.

Which side a given filter belongs on, and that it is never rendered on both, is decided in [store-list-filter-placement.md](store-list-filter-placement.md).

## Build the split with StoreListFilters
`components/StoreListFilters.vue` is the shared filter bar; do not hand-build the trigger, badge, dialog and footer again on each page. It is one GET Inertia `<Form>` (id `store-list-filters`, one per page) for the list's own route, so every field keeps the query-string name the controller's Filter request already reads.

- Props: `action` (the Wayfinder route object, e.g. `listIndex()`), `chips` (the applied dialog filters as `StoreFilterChip[]`, built once from the page's `filters` prop; the badge counts them and each is a removable chip, see [store-list-filter-placement.md](store-list-filter-placement.md); only the dialog's filters, not search or the key filter). `activeCount` (via `countActive([...])` in `lib/filters.ts`) remains only for a page without chips.
- Default slot `{ errors, processing }`: search, the one key filter and hidden fields such as `per_page`. The component adds the inline Apply button and the "Filters" trigger with the `countBadgeClass` badge.
- `#filters` slot `{ errors }`: every other filter, laid out in the dialog's two-column grid. Buttons in either slot must be `type="button"`.
- Labels reuse `common.filters.title|apply|clear|close` (js.md); only the badge's screen-reader text and the processing label are `store.listFilters.*`.
- The dialog is `FilterDialog` with `keepMounted` (filter-dialogs.md), so a search sent while it is closed still carries the dialog's filters. Its `#footer` is `FilterDialogFooter` bound by `form="store-list-filters"`. Apply submits everything; Clear submits without the dialog's fields (they are disabled for that one synchronous submit), keeping search, the key filter and `per_page`. The dialog closes on a successful visit.
- Fields stay uncontrolled with `:selected`/`:checked`/`:value` from the `filters` prop, as on the current lists, so a reload shows the applied state. Show each field's error with `FieldError`.

## New and changed lists follow the same split
When adding a Store admin list or a new filter, do not append another always-visible select/checkbox. Put it in the `#filters` slot and include it in `activeCount`. Apply/Clear live in the dialog's `#footer` (see filter-dialogs.md).

## Page chrome stays out of the header and the page
`StoreAdminLayout.vue` does not render persistent Notifications or Clear Cache buttons above the page content or in the shared `BackofficeShell` header, which holds only the crumb, the language switcher and "Browse website" (layouts.md); the label is "Clear dashboard cache". Notifications is a sidebar item with the unread badge; Clear Cache lives in the sidebar footer with an (i) `HelpTooltip` and a `FilterDialog` confirm that repeat the same text (`store.nav.clearCacheHelp`) before the POST, because it only forgets the dashboard-graph cache keys (see `DashboardController::clearCache`), not the whole application cache.

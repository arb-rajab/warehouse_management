---
paths:
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/layouts/StoreAdminLayout.vue'
---

# Store admin list filters stay compact

## Only search plus one key filter is permanent
On a Store admin list page (`resources/js/pages/Store/Admin/**/Index.vue`) the inline filter bar shows only the search field and at most one key filter (the one staff use most, such as status or seller), plus per-page/sort controls. Every other filter goes into a "Filters" dialog opened from a trigger button with an active-count badge, exactly like the WMS pages (`Admin/Products/Index.vue`: `FilterDialog`, `FilterDialogFooter`, `filterTriggerButtonClass`, `countBadgeClass`). A list with two or fewer filters in total needs no dialog.

## New and changed lists follow the same split
When adding a Store admin list or a new filter, do not append another always-visible select/checkbox. Put it in the dialog and include it in the active-count badge. Apply/Clear live in the dialog's `#footer` (see filter-dialogs.md).

## Page chrome stays out of the top strip
`StoreAdminLayout.vue` does not render persistent Notifications or Clear Cache buttons above the page content. Notifications is a sidebar item with the unread badge; Clear Cache lives in the sidebar footer with a help tooltip and a confirm dialog, because it only forgets the dashboard-graph cache keys (see `DashboardController::clearCache`), not the whole application cache.

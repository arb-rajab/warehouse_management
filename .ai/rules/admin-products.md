---
paths:
  - resources/js/pages/Admin/Products/Index.vue
---

# Admin Products

## Products table hides redundant sibling filter icons
Full/Opened/Expired/Expiring-soon share one `occupancy` filter popover, and Activity Today/This week share one `activity` popover — tapping any sibling's icon opens the identical popover. Only the "primary" column in each group (Full, Activity Today) always shows its filter icon (`DataTable.vue`'s `filterIconAlwaysVisible` defaults to `true`); the other siblings (Opened, Expired, Expiring-soon, Activity Week) set `filterIconAlwaysVisible: false` so their icon only appears once that group's shared `filtered` computed is actually true. Product has no sibling, so it stays always-visible. When adding a new column that shares a `filterKey` with an existing one, default the new one to `filterIconAlwaysVisible: false` unless it's meant to be the group's advertised entry point.

---
paths:
  - 'resources/js/layouts/**'
---

# Layouts

## App bar nav links: active indicator + icon are required
Every top-level nav link in AdminLayout.vue (and any future app bar) must show which section is active and carry an icon:
- Active state: compare the path portion of `usePage().url` (stripped of any query string) against the link's href (`currentPath === href || currentPath.startsWith(href + '/')`), set `aria-current="page"` when active, and style active vs. inactive differently (currently a bottom border + bold text vs. muted text). Stat-card links from the Dashboard navigate with filter query strings (e.g. `/admin/cells?state=empty`), so matching against the raw `page.url` (path + query) breaks active-state detection.
- Icon: every nav item and the logout action renders a `@lucide/vue` icon before its label, for scanability.
Keep nav items as a small array (`{ label, href, icon }`) driving a `v-for`, not hand-duplicated links, since 3+ items already share this shape.

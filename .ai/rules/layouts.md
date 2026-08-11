---
paths:
  - 'resources/js/layouts/**'
---

# Layouts

## App bar nav links: active indicator + icon are required
Every top-level nav link in AdminLayout.vue (and any future app bar) must show which section is active and carry an icon:
- Active state: compare `usePage().url` against the link's href (`page.url === href || page.url.startsWith(href + '/')`), set `aria-current="page"` when active, and style active vs. inactive differently (currently a bottom border + bold text vs. muted text).
- Icon: every nav item and the logout action renders a `@lucide/vue` icon before its label, for scanability.
Keep nav items as a small array (`{ label, href, icon }`) driving a `v-for`, not hand-duplicated links, since 3+ items already share this shape.

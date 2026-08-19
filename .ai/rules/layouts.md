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

## Logout link asks for confirmation via lib/confirm.ts
AdminLayout.vue's logout Link carries `:on-before="confirmLogout"` (from `lib/confirm.ts`, wording key `nav.confirmLogout`) to guard against an accidental click, mirroring the `confirmDelete()` pattern used by Rows/Users delete actions. Its native-`window.confirm` wiring is covered by a Playwright e2e spec (`tests/e2e/admin-logout-confirmation.spec.ts`), not a Vitest unit test — jsdom's Link stub can't drive a real confirm dialog, same reasoning as `admin-delete-confirmation.spec.ts` (see js.md).

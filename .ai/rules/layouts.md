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

## Logout link asks for confirmation via lib/confirm.ts
AdminLayout.vue's logout Link carries `:on-before="confirmLogout"` (from `lib/confirm.ts`, wording key `nav.confirmLogout`) to guard against an accidental click, mirroring the `confirmDelete()` pattern used by Rows/Users delete actions. Its native-`window.confirm` wiring is covered by a Playwright e2e spec (`tests/e2e/admin-logout-confirmation.spec.ts`), not a Vitest unit test — jsdom's Link stub can't drive a real confirm dialog, same reasoning as `admin-delete-confirmation.spec.ts` (see js.md).

## Desktop app-bar utilities (language/user/logout) live behind AccountMenu.vue, not inline in the bar
The 7 primary nav destinations stay as direct top-level links (see the rule above) — they're the thing people navigate between and shouldn't cost an extra click behind a menu. The language switcher, signed-in user name, and logout action are utilities, not destinations, so on the desktop (`xl:flex`) row they're collapsed into `resources/js/components/AccountMenu.vue`, a single trigger button (user icon + name + chevron) that opens a panel (`#account-menu-panel`, plain `<div>`, no `role`) containing the existing `LanguageSwitcher` component and the logout `Link`. This was done to relieve crowding on 1280–1440px screens without hiding any of the 7 admin sections behind a submenu.
- Do not put `role="menu"`/`role="menuitem"` on this panel or its logout button: the panel has no arrow-key roving-focus behavior (WAI-ARIA menu pattern requires it), and `role="menuitem"` on the logout `<button>`/`Link` overrides its implicit `button` role — `getByRole('button', { name: 'Log out' })` (used by `tests/e2e/admin-logout-confirmation.spec.ts`) then never matches, timing out. Identify the panel by `id`/`aria-controls`, not ARIA menu roles, unless full keyboard menu semantics are added alongside.
- AccountMenu reuses `lib/useDismissibleListbox.ts`'s `open`/`containerRef` pair for click-outside/Escape-to-close — only the listbox option-navigation half of that composable is skipped (call it with `() => 0`), since the panel here isn't a listbox.
- The mobile hamburger menu (`#admin-mobile-menu`) intentionally keeps the language switcher and user/logout row inline (not nested in another dropdown) — it's already a vertical panel with room, and stacking a dropdown inside a dropdown adds a tap for no space benefit there.

## Before adding any top-level app-bar item: classify it first
Every time a new item is proposed for the app bar (`navItems` in `AdminLayout.vue`, or its future equivalents), decide which bucket it belongs to before writing any code:
- **Destination** — a page a user navigates to and spends time on (its own route, its own content). → Add it to `navItems` as a top-level link, with an icon and active-state styling per the rule above.
- **Account/session-scoped chrome** — a utility that acts on the current session or user, not a page of its own content (theme, profile/account settings, language, logout). → Add it inside `AccountMenu.vue`'s panel instead of growing the visible bar.
A page that edits global/shared application state (e.g. `Admin/Settings/Edit.vue`, which edits the single shared `Setting` model — QR code dimensions — not anything scoped to the signed-in user) is a **destination**, not account-scoped chrome, even though its name is "Settings": classify by what the page edits (shared vs. per-user data), not by the word "settings" in its label. When genuinely unsure which bucket an item falls into, ask rather than guessing — this bar's crowding-vs-access-cost tradeoff was a deliberate decision (see above), not an accident to route around silently.

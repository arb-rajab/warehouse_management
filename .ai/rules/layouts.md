---
paths:
  - 'resources/js/layouts/**'
  - 'resources/js/components/BackofficeShell.vue'
  - 'resources/js/components/Sidebar*.vue'
  - 'resources/js/lib/sidebarCollapse.ts'
---

# Back-office shell, sidebar navigation and logout confirmation

## One shell, two layouts
The WMS (`layouts/AdminLayout.vue`) and the store control panel (`layouts/StoreAdminLayout.vue`) both render through `components/BackofficeShell.vue`, so they look the same (Direction A, see views.md "Back office identity"). The layouts stay separate files: each owns its menu, permissions, active-state logic and footer content; the shell owns the chrome. Do not merge the layouts or move a menu into the shell.
- **Sidebar** (`aside#admin-sidebar` / `aside#store-admin-sidebar`, light in light mode, warm dark in dark mode): the logo `/assets/img/33logoqr.png`, the brand name, the area title under it (`nav.brand` "Warehouse Admin" / `store.nav.brand` "Store Admin"), the layout's `#nav` slot and `#footer` slot. The brand name is the shared Inertia prop `brandName` (HandleInertiaRequests, `__('messages.brand_name')`, the storefront header's placeholder), so the owner edits it in one place; never hard-code it or copy it into `resources/js/locales`.
- **Header** (56 px, `print:hidden`): the phone menu button, the `lg` rail toggle, the crumb (area > active menu label) and the layout's `#actions` slot. WMS actions: `LanguageSwitcher` (from `sm`) and the "?" link to Help. Store actions: `LanguageSwitcher` (from `sm`) and "Browse website" (new tab).
- **Content**: `main#main`, padded, a `mx-auto max-w-screen-2xl` column with the one `SuccessBanner` (success-messages.md). `fullBleed` (the warehouse map) gives the page the whole area beside the sidebar and under the header: no column, padding or banner.
- The parts are `SidebarNavLink.vue` (icon, label, count; `aria-current="page"` with the brand-soft fill and an inline-start indicator bar), `SidebarSectionHeading.vue` (group label) and `SidebarUser.vue` (initials avatar, the one saffron accent, name and e-mail, an actions slot). `StoreAdminNavLink` delegates to `SidebarNavLink`.

## Sign-in frame: AuthCard
`pages/Auth/Login.vue` and `ChangePassword.vue` render through `components/AuthCard.vue` (prop `area`, default slot): the `bg-page` page, the logo `/assets/img/33logoqr.png`, the brand name as the `h1` (the shared Inertia prop `brandName`, same source as the sidebar; never a literal or a locale string), the area title under it (`auth.login.brand` / `auth.changePassword.brand`), the `LanguageSwitcher`, and a `bg-surface border-line shadow-card` card for the form. The store control panel has no Vue login of its own (its login is the retained Blade page under `/temp/login`, not restyled). `AuthCard.test.ts`, the two page tests and `backoffice-shell.spec.ts` (logo, brand, contrast in light and dark) cover it.

## Sidebar nav links: grouped array, active indicator, icon
`AdminLayout.vue`'s menu is a `navGroups` array (`{ labelKey?, items: { labelKey, href, icon }[] }`) driving a `v-for`, in this order: Dashboard; Inventory (Warehouses, Rows, Pallets, Warehouse map, Products); Activity (Cell log, Verification rounds); Administration (Users, Settings, Help). Its `<nav>` is named `nav.menuLabel` ("Warehouse menu"); the store's is `store.nav.menuLabel`.
- Active state (WMS): `isActiveNavUrl()` from `lib/navigation.ts` compares paths only (stat links add query strings such as `/admin/pallets?state=empty`) and the longest matching href wins, so `/admin` lights up only on the dashboard. Exactly one link carries `aria-current="page"`. The store's active link comes only from `findActiveStoreMenuLink()` (store-admin-menu-highlighting.md).
- Every link and the logout action render a `@lucide/vue` icon; hrefs are Wayfinder route objects.

## Phone drawer and the icon rail
- Below `lg` the sidebar is an off-canvas drawer opened by the header's "Open menu" button (`aria-controls` = the sidebar id, `aria-expanded`). Opening focuses the drawer's close button; Escape, the backdrop and the close button close it and return focus to the menu button; following a link or any visit (`page.url` changes) closes it.
- From `lg` the header's rail toggle (`nav.collapseSidebar` / `nav.expandSidebar`, `PanelLeftClose`/`PanelLeftOpen` with `rtl:rotate-180`) folds the sidebar to a 64 px icon rail: labels stay for screen readers (`lg:sr-only`) and as `title` tooltips, counts sit over the icon, the store's group icons unfold the sidebar with that group open. Slots receive `collapsed` (and the nav slot `expand`); a layout element that must not show on the rail uses `lg:hidden`/`lg:sr-only`, never a `v-if`, so the phone drawer is unchanged.
- The choice is remembered in localStorage per user, sidebar and scope (`lib/sidebarCollapse.ts`, key `backoffice.sidebar.<sidebar id>.<scope>.<user id>`), every access in try/catch so blocked storage falls back to the default. AdminLayout passes scope `map` with `defaultCollapsed` on the full-bleed warehouse map (it starts folded) and `page` elsewhere. Do not store anything else there.

## Logout asks for confirmation via lib/confirm.ts
`LogoutLink.vue` carries `:on-before="confirmLogout"` (`lib/confirm.ts`, wording key `nav.confirmLogout`) to guard against an accidental click, mirroring `confirmDelete()`. In both layouts it sits in the sidebar footer's user block as an icon button whose label is `sr-only` (`label-class`), so its accessible name stays "Log out". Its native `window.confirm` wiring is covered by `tests/e2e/admin-logout-confirmation.spec.ts`, not Vitest (jsdom's Link stub cannot drive a real dialog). Do not give it `role="menuitem"` (js.md).

## Before adding a sidebar item: classify it first
- **Destination**: a page a user navigates to and spends time on (its own route and content) goes into a nav group as a link with an icon and active state.
- **Account/session chrome**: something acting on the current session or user (language, profile, logout) goes into the footer user block or the header actions, not the menu.
A page that edits global/shared state (e.g. `Admin/Settings/Edit.vue`, the shared `Setting` model) is a destination even though it is called "Settings": classify by what the page edits. When unsure, ask rather than guess.

## Tests
`layouts/AdminLayout.test.ts` and `layouts/StoreAdminLayout.test.ts` cover the menus, active state, footer, header actions, drawer and rail; `components/BackofficeShell.test.ts` and the `Sidebar*.test.ts` files cover the shell. `tests/e2e/backoffice-shell.spec.ts` covers both shells in the browser (Arabic, dark contrast, the map's rail and toolbar fit, phone drawers); `admin-nav-active-indicator.spec.ts` scopes its link lookups to the "Warehouse menu" landmark because "Help" is also in the header and "Dashboard" is also a page heading.

---
paths:
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/lib/storeAdminMenu.ts'
  - 'resources/js/lib/storeAdminActiveMenu.ts'
  - 'resources/js/pages/Store/Admin/**'
---

# Store admin menu highlighting

The Store admin sidebar (`StoreAdminLayout.vue`, menu in `lib/storeAdminMenu.ts`) must light up exactly the entry the current page belongs to, and open and highlight the group holding it. All of that is decided by one helper, `findActiveStoreMenuLink()` in `lib/storeAdminActiveMenu.ts`.

## How a page is matched
- A link is active when the URL path equals its path or continues it after a `/` (segment boundary: `/brands` matches `/brands/edit/3`, never `/brands-wise-product-discount`). The query string, fragment and trailing slash are ignored.
- The longest matching path wins, so exactly one leaf is active. A link whose route ends `/index` also owns its directory (`/trips/index` owns `/trips/{trip}/edit`, `/trips/show/{trip}`); a longer sibling (`/trips/trucks/index`) still beats it.
- The dashboard sets `exact: true`: its path prefixes every page, so it matches itself only. A page matching nothing highlights nothing; it must never fall back to the dashboard.
- A group header is highlighted (`data-active`) and expanded while any descendant link is active; nested headings (Trips → Trucks, Setup → Shipping) are highlighted too. A hand-toggled group resets on navigation.
- Pass the whole `storeAdminMenu`, never the permission- or search-filtered copy, so the highlight does not depend on who is looking or what they typed.

## What every Store admin page change must do
1. **Reachable from the menu or declared.** Every new or changed page under `resources/js/pages/Store/Admin/**` either lives under an existing menu link's URL (create/edit/show pages of a resource usually do) or gets a row in `storeAdminPageParents` (`lib/storeAdminMenu.ts`) naming the menu link it belongs to; a page reached from outside the menu (profile, the Notifications sidebar item) maps to `parent: null`. Routes whose list URL does not prefix their detail URLs (`/customer/{customer}/pages-views` vs `/customers`, `/edit-attribute-value/{id}` vs `/attributes`, `/orders/{order}` vs `/all_orders`) need such a row. Use Wayfinder route functions for `page` and `parent`; never hardcode URL strings (the helper cuts a route before its first `{param}`).
2. **Use the central helper.** Do not compare `page.url` with menu hrefs anywhere else (no `startsWith`, no `isActiveNavUrl`, no per-page `active` flag, no layout-level special case).
3. **Check the highlighting.** Before finishing, load the page's URL against the menu (in the unit test below, or in the browser) and confirm one entry is active and its group is open, including with a query string.
4. **Test it.** Any menu change (a link, group, `exact`, `/index` route or `storeAdminPageParents` row) adds or updates a case in `lib/storeAdminActiveMenu.test.ts`, and a layout-level change updates `layouts/StoreAdminLayout.test.ts`.

## Sidebar utilities outside the menu
The Notifications item (unread badge, popover with recent items and the link to the notifications page) sits between the scrolling `<nav>` and the footer, outside the menu tree: it is never an active menu link and does not touch `findActiveStoreMenuLink()`. Clear dashboard cache is in the footer (permission `file_system_&_cache_configuration`), with a `HelpTooltip` and a `FilterDialog` confirm before the POST. The layout has no top strip above the page content. Changes to them update `layouts/StoreAdminLayout.test.ts`.

## Gotchas
- Two menu links must not share a path; the helper test fails if they do.
- `storeAdminPageParents` parents are resolved by path against the menu's links, so a parent must be a link that exists in `storeAdminMenu`.
- The general admin app bar (`AdminLayout.vue`) keeps its own rule in `layouts.md`; this one is for the Store back office only.

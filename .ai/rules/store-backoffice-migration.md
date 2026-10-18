---
paths:
  - 'resources/views/shop/backend/**'
  - 'resources/js/pages/Store/**'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/lib/storeAdminMenu.ts,resources/js/lib/storeAccess.ts'
  - 'resources/js/components/StoreAdminNavLink.vue,resources/js/components/StoreStaffFormFields.vue,resources/js/components/StoreRoleFormFields.vue'
  - 'resources/js/components/StoreRichTextField.vue,resources/js/components/StoreBlogFormFields.vue'
  - 'resources/js/components/StoreNewsletterFormFields.vue,resources/js/components/StoreNewsletterRecipients.vue,resources/js/components/StoreTicketReplyForm.vue,resources/js/lib/storeSupportTicket.ts,app/Services/Store/**'
  - 'resources/js/components/StoreWebsite*'
  - 'resources/js/components/StoreProduct*,resources/js/lib/storeProductForm.ts'
  - 'resources/js/components/StoreGoogleMapFormFields.vue,resources/js/components/StoreDashboardChart.vue'
  - 'resources/js/components/StoreTripStatusDialog.vue,resources/js/components/StoreTripFormFields.vue,resources/js/components/StoreTripMap.vue,resources/js/components/StoreLeafletMap.vue,resources/js/components/StoreVisitMap.vue,resources/js/lib/storeTrip.ts'
  - 'app/Http/Controllers/Store/**,app/Http/Requests/Store/**,app/Http/Resources/Store/**'
  - 'app/Http/Middleware/HandleInertiaRequests.php,app/Http/Middleware/HandleStoreBladePages.php,app/Http/Middleware/SendSecureHeaders.php'
  - 'routes/shop-admin.php'
  - 'tests/Feature/Store/**,tests/e2e/store-*.spec.ts'
---

# Moving the store back office from Blade to Inertia

Latest follow-up: [admin dashboard](#33-admin-dashboard-2026-10-18).
Map-settings follow-up: [Google Map settings](#32-google-map-settings-2026-10-18).
Visit follow-up: [representative visit details and day maps](#31-representative-visit-details-and-day-maps-2026-10-18).
Trip-map follow-up: [the trip map picker on Leaflet from npm](#30-trip-map-picker-2026-10-17).
Server-status follow-up: [the server status page's deletion](#29-server-status-page-deletion-2026-10-17).
Reviews follow-up: [the legacy reviews screen's retirement](#28-legacy-reviews-screen-retirement-2026-10-17).
Trip-detail follow-up: [the trip detail and its checkpoint actions](#27-trip-detail-and-checkpoints-2026-10-16).
Trip-orders follow-up: [the trip orders list and its order actions](#26-trip-orders-list-2026-10-15).
Trip-form follow-up: [the trip create and edit forms](#25-trip-create-and-edit-forms-2026-10-15).
Trip-list follow-up: [the trip lists and their status, notes and delete actions](#24-trip-lists-and-status-actions-2026-10-14).
Edit-form follow-up: [the physical product edit form and two orphaned Blade files](#23-physical-product-edit-form-2026-10-14).
Create-form follow-up: [the physical product create form](#22-physical-product-create-form-2026-10-13).
Digital-form follow-up: [the digital product create and edit forms](#21-digital-product-create-and-edit-forms-2026-10-13).
Website follow-up: [the website footer, appearance and custom pages, and the generic settings writer's retirement](#20-website-footer-appearance-and-custom-pages-2026-10-13).
Ticket/newsletter follow-up: [the newsletter and the support ticket detail on the shared editor](#19-newsletter-and-support-ticket-detail-on-the-shared-editor-2026-10-13).
Editor follow-up: [the shared Tiptap editor and blog post forms](#18-shared-rich-text-editor-and-blog-post-forms-2026-10-13).
Setup-settings follow-up: [general settings verification and the former Google Map hold](#17-setup-configuration-settings-follow-up-2026-10-12).
Product-list follow-up: [in-house and all-products list verification](#16-in-house-and-all-products-lists-follow-up-2026-10-12).
Digital-product follow-up: [digital product list verification](#15-digital-product-list-follow-up-2026-10-12).
Brand follow-up: [brand migration verification](#14-brand-administration-follow-up-2026-10-12).
Category follow-up: [category migration verification](#13-category-administration-follow-up-2026-10-12).
Customer-package follow-up: [customer package migration verification](#12-customer-package-administration-follow-up-2026-10-12).
Carrier follow-up: [carrier migration verification](#11-carrier-administration-follow-up-2026-10-12).
PR #377 verification: [final all-store browser results](../store-backoffice-reconciliation-20261011.md#final-all-store-browser-verification-2026-10-12)
close the pending browser gate. Earlier dated verification sections remain
historical evidence; §10 records reconciliation results and §§11–33 record
subsequent module verification and limits.

## 1. Decision and scope

The current reconciliation request authorizes integration of independently developed
store back-office modules into Inertia v3 + Vue 3 + TypeScript. Historical
checked-in approval claims are context, not independently verified evidence for
new dependencies, feature usage, removal or changes in business behavior.

- **In scope:** `resources/views/shop/backend/**`, served under `/temp/admin`.
- **Not in scope:** the storefront, the seller area, the store's auth pages,
  its emails and its PDFs. They stay Blade.

The back office moves one module at a time. While it does, converted and
unconverted pages share `/temp` (§2.6).

- The original audit baseline predates the staff/roles pilot. Do not add
  independently developed branch counts or treat their progress snapshots as
  integrated work. §§4–6 below are rebuilt from this integration's files and
  registered routes.
- Staff and roles establish the module pattern (§3).
- Exact source references, deferred paths and focused checks are recorded in
  [the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).

## 2. Foundation decisions

### 2.1 Shared props are guard-aware

`HandleInertiaRequests::share()` treats a request as a store request when its
route carries `UseStoreAuthGuard`, which every `/temp` web group starts with.
It reads the route, not the default guard, because the default only becomes
`store` once that middleware has run.

On a store page it shares:

- `name`;
- `locale`: the store locale that `Language` set;
- `auth.user`: `{id, name, email}` of the `store` guard's user. This is the
  same narrow shape as WMS; middleware.md explains why it stays narrow.
- `store`:
  - `rtl`: from `languages.rtl`;
  - `permissions`: the user's store permission names;
  - `isSuperAdmin`;
  - `isRaizerAdmin`;
  - `settings`: one boolean per `STORE_MENU_SETTINGS` key.

Everything except `name` is a closure, because `Language` runs after this
middleware. WMS pages keep their props exactly as before, with no `store` key.
`app.blade.php` takes `dir` from `store.rtl` when it is there, and otherwise
keeps WMS's `locale === 'ar'`.

- **Permissions:** `isSuperAdmin` is shared as a flag rather than expanding to
  every permission row. That mirrors `Gate::before()`, which grants a Super
  Admin abilities even when no row exists for them.
- **Settings in sync:** `StoreInertiaSharedPropsTest` fails if
  `lib/storeAdminMenu.ts` reads a setting the middleware doesn't share, or
  the other way round.
- **Tests:** tests/Feature/Store/StoreInertiaSharedPropsTest.php.

### 2.2 StoreAdminLayout and the sidebar

`layouts/StoreAdminLayout.vue`:

- a sidebar with collapsible groups;
- the account block at its foot: storefront link, profile, `LanguageSwitcher`,
  and `LogoutLink` posting to the store's `shop.logout`;
- below `lg`, a menu button.

**Gates.** The menu data is `lib/storeAdminMenu.ts`. It is the Blade sidebar
(`inc/admin_sidenav`), with every entry gated as Blade gated it:

- `permission`, which may be any-of;
- `setting`: a business setting that switches the section on. The vendor's
  `raizer_admin` account passes every setting gate;
- `raizerAdmin`: vendor-only entries.

`visibleStoreMenu()` drops a group with no visible entry, which is what
`@canany` did.

**Entries:** each entry is `inertia: true` (an Inertia `<Link>`) or not (a
plain `<a>`, i.e. a full page load). Select link behavior from the actual destination (§6); retained Blade forms
use full page loads. Blog create/edit (§18), the newsletter and the ticket
detail (§19), the website footer, appearance and pages (§20), the digital
product create/edit forms (§21) and the physical product create (§22) and
edit (§23) forms are Inertia pages.

**How it differs from the Blade sidebar, on purpose:**

- **Dropped:**
  - the 12 removed-addon blocks: their routes no longer exist;
  - the stray duplicate top-level Shipping group;
  - System → Server status: the page is deleted, on the owner's decision (§29).
    It was a `Route::view` with no controller whose PHP 7.3/7.4 and MySQL 5.6
    requirements showed a red cross on every working install. Both sidebars
    drop it.
- **Fixed:**
  - Home Banners now links inside `/temp`;
  - Product reviews and Subscribers link to the admin URI. Their route names
    had gone to storefront routes.
- **Gates follow the route's own permission where Blade disagreed**, so a link
  never leads to a 403:
  - customer archive: `delete_customer`;
  - notifications: `add_notification`;
  - brand discount: `view_all_brands`;
  - Home Banners: `edit_website_page`.
- **Integrated shell:** permission-filtered badge counts, menu search, the
  current account’s notifications dropdown and narrowly scoped Clear Cache.
  Profile and notification links use the selected migrated controllers. The
  dashboard uses Inertia with bundled Chart.js since §33; its menu entry is
  an Inertia visit.

**Shared with WMS.** Both layouts genuinely need these:

- `components/SkipToContentLink.vue`;
- `lib/navigation.ts` `isActiveNavUrl()`: the longest-match active link,
  compared on paths;
- `LogoutLink`'s `href` prop;
- `ResourceFormPage`'s `layout` and `wide` props.

`AdminLayout` and `AccountMenu` are otherwise unchanged. The store's sidebar
and WMS's top bar share nothing else.

### 2.3 Translations

**Decision: back-office UI strings move into `resources/js/locales/{en,ar}.json`
under `store.*`.** Values that live in the database are translated on the
server and sent as data.

- **DB-held values:** permission and section names, role names
  (`role_translations`), category names and the like.
- **Example:** `RoleController::permissionGroups()` runs each permission name
  through `translate(Str::headline(...))`, exactly as the Blade form did.

Why not the other two options:

- **A per-page dictionary prop:**
  - every label would depend on a server prop, so Vitest could not assert
    static labels with `t()` (CLAUDE.md asks for every label);
  - each request would call `translate()` once per label, and `translate()`
    also inserts a row for every unseen key at render time.
- **Exporting the `translations` table into vue-i18n at build time:** it
  cannot see edits made in the translation admin after a deploy, and it would
  ship every storefront string to the back office.
- **Why the locale files fit:** back-office copy is developer-owned chrome,
  and WMS already works this way. One mechanism serves both admin UIs.

What it means for the translation admin:

- It (`languages.show`, `app-translations.show`) keeps editing `translations`,
  which still drives the storefront, the seller area, emails, PDFs and the
  mobile API.
- A converted module's strings stop being read from that table. Editing
  "All Staffs" there no longer changes the back office. The rows stay and do
  no harm.
- **Languages:** the back office ships `en` and `ar`.
  - A store language beyond those renders the back office in English, through
    vue-i18n's fallback; the storefront is unaffected.
  - The layout reuses WMS's `LanguageSwitcher`. It writes the same `locale`
    session key that the store's `Language` middleware reads.

### 2.4 Uploads: selected contract and coexistence

`Store\Admin\UploadController` uses the selected `944e2e1e` implementation.
The `UploadResource` response is exactly `id, name, type, size, url`; list,
selected-file previews, upload, info, delete and bulk-delete routes are listed
in §6. Keep action-specific permissions, ownership checks, authorization of
every bulk member before any deletion, confined paths and storage-failure handling.

`StoreUploadField` posts comma-separated IDs under `name`, accepts `initialFiles`,
optional `id` and `required`, and supports single/multiple selection. Required
field validation belongs to the selected field; avoid duplicate pickers.
Truck forms adapt their existing arrays and stored CSV to this contract.
Image references may cross owners only through the canonical library permission
and policy; new permission definitions grant no roles.

Admin uploads explicitly opt into raster normalization through
`MultipartUploadService::store(..., normalizeRaster: true)`: WebP and a
40-megapixel limit. Legacy API/seller/trip consumers retain their existing format
and optimization policy through the default path. Global normalization, opt-out
and SVG policy changes require a decision; existing SVG sanitation remains.
Resolve managed local files consistently and allow only HTTP(S) URLs.
Private offers/import storage is separate.

Keep `/temp/aiz-uploader*`, its legacy Blade responses and existing endpoints
while seller and unconverted Blade consumers remain. Storefront, seller and
authentication pages also continue to need their Blade assets and CSP policy.

### 2.5 Content-Security-Policy

Inertia pages need no inline scripts, so they keep config/secure-headers.php's
strict policy. Unconverted Blade pages need inline scripts.

**How it works:**

- `App\Http\Middleware\SendSecureHeaders` replaces bepsvpt's
  `SecureHeadersMiddleware` as the globally appended middleware. It is
  identical except for one thing: it merges the directives a route middleware
  puts in the `SendSecureHeaders::CSP_OVERRIDE` request attribute.
- `HandleStoreBladePages` sets that attribute to `config('store.blade_csp')`
  for a Blade response only. The Blade policy allows `'self'`,
  `'unsafe-inline'` and `https:` for scripts and styles, and loosens font,
  img, frame and connect the same way.
- A request attribute ends with its request. A `config()` rewrite would leak
  into the next request in the same process, and a test pins this.

**What it changes:**

- Store Inertia pages and WMS pages keep the strict policy.
- Before this change, Blade pages received the strict policy too, which would
  have blocked their scripts once it was enforced.
- CSP stays report-only outside production, as before.
- Review `blade_csp` directives as modules convert, accounting for every
  remaining consumer. It also serves the protected storefront, seller area
  and authentication Blade pages. Keep both middleware and the policy after
  the last back-office Blade module converts; removing them requires a
  separate change that preserves those surfaces' scripts and assets.

**Rich text under the strict policy (§18).** The shared Tiptap editor runs on
Inertia pages with the strict policy unchanged. ProseMirror writes node and
mark styles through the CSSOM (`element.style.cssText`), which CSP does not
govern; Tiptap's injected base stylesheet is a `<style>` element, which the
strict `style-src` already allows. No directive, `blade_csp` entry or WAF
setting changed for it.

**Map tiles under the strict policy (§30).** The trip map picker bundles
Leaflet and its stylesheet through Vite (`script-src`/`style-src 'self'`; the
marker and control images are bundled assets, `img-src 'self'`). Its
OpenStreetMap tiles are `<img>` elements from `https://tile.openstreetmap.org`,
so the strict `img-src` gained exactly that host in
`config/secure-headers.php` (`img-src.allow`), and nothing else: no
`connect-src`, `script-src` or `style-src` change, and `blade_csp` is
untouched. The representative visit maps reuse the same bundled Leaflet
base and tile allowance since §31; none loads code or CSS from unpkg.

**Tests:** tests/Feature/Store/HandleStoreBladePagesTest.php.

### 2.6 Coexistence under /temp

Converted and unconverted pages coexist through four mechanisms:

- **Vue to Blade:** a link from a Vue page to a Blade page is a plain
  `<a href>`, i.e. a full load.
  - `StoreAdminNavLink`'s `inertia` flag makes that choice.
  - Wayfinder's `.url()` builds the href, since `@/actions` covers the legacy
    controllers too.
- **Blade to Vue:** a link from a Blade page to a converted page stays an
  ordinary link. Inertia boots from `app.blade.php` on the full load.
- **Route names:** a converted module keeps its route names, so Blade links
  such as `route('staffs.index')` keep working. Its URIs may change, e.g. to
  resource style with bound models.
- **The safety net:** `HandleStoreBladePages` sits on the store's three web
  groups. When an Inertia GET lands on a Blade page, it answers 409 with
  `X-Inertia-Location`, which is Inertia's own "load this in full".
  - This covers a redirect from an Inertia action to an unconverted page.
    Example: logout → the Blade login page, which
    tests/e2e/store-staff-coexistence.spec.ts drives in a browser.
  - It also covers any link wrongly marked Inertia.
  - A response counts as Blade when it renders a view other than Inertia's
    root view `app`. A `page` variable in a CMS/policy Blade view does not
    make that response Inertia.

### 2.7 Foundation verification (historical read-only pass, 2026-10-07)

This pass verified the existing implementation against §§2–3, the staff and
shell audit at that source commit. Current routes and decisions are in §§5–6. No runtime
foundation defect requiring a migration blocker fix was confirmed. Reuse the
foundation and pilot; their presence does not establish full runtime readiness.

**Verified in current source and focused frontend checks:**

- `HandleInertiaRequests` selects the store branch from route middleware,
  defers store locale/auth resolution, exposes only `{id, name, email}` for
  the signed-in user, and leaves WMS props without `store`. All 42 menu
  setting keys match the middleware list.
- `StoreAdminLayout`, `storeAdminMenu`, `StoreAdminNavLink` and `storeAccess`
  retain permission/setting/vendor gates, discard empty groups, use the
  deliberate admin URLs for reviews, subscribers and Home Banners, and make
  only staff/roles sidebar destinations Inertia visits. Profile and
  storefront links load in full; logout passes the store's POST action.
- The six Vue pages use the existing layout, forms, tables, pagination,
  Wayfinder actions and permission gates. Controllers keep action-level
  backend gates, store-only role/permission references, explicit list
  selections, model binding, transaction boundaries, Super Admin/self-delete
  protections and documented redirects. The obsolete controllers and Staff
  model are absent from both analysis exclusion lists.
- All 159 `store.*` translation keys exist and have values in both locales.
  Database-held names remain server-translated; Vue renders them through
  escaped interpolation. Root `dir` follows `store.rtl`, WMS retains its
  Arabic fallback, and the shell uses logical spacing/border utilities.
- `HandleStoreBladePages` is on all three store web groups. Blade GETs with
  `X-Inertia` take the installed Inertia `409`/`X-Inertia-Location` path;
  ordinary Blade loads get a request-attribute CSP override. Inertia roots
  and WMS retain the strict policy. The override does not mutate config.
  Blade links to the pilot retain their route names and boot Inertia on a
  direct load. The selected profile controller retains the authenticated-user ownership boundary.

**Actual readiness and check results:**

| Check or prerequisite | Observed result |
| --- | --- |
| Runtime and packages | Node 24.19.0/npm 11.9.0 available. Installed metadata: Laravel 13.34.0, inertia-laravel 3.3.1, Wayfinder 0.1.21, Vue 3.5.43, Inertia Vue 3.6.1, Vite 8.2.1, Pest 5.0.4. PHP and Composer executables unavailable; `install_php.sh --check` fails. |
| Local setup | `vendor/`, `node_modules/`, a nonempty local `APP_KEY`, generated actions and a manifest containing all six pilot pages exist. No Claude setup is running. These artifacts alone cannot prove backend or fresh-build readiness. |
| Laravel Boost | No Boost tools exposed. AGENTS.md's fallback used repository files and installed package source. No Claude SessionStart hook or forced Claude remote mode used. |
| Focused Vitest | 17 files, 119 tests passed: store shell/menu/access/nav link, staff/role form fields and all six pages, plus language/logout/resource-form/navigation/i18n utilities. |
| Frontend types | `npm run types:check` passed against the existing generated actions. |
| Production build | `npm run build` failed at Wayfinder's `php artisan wayfinder:generate --skip-routes --with-form`: PHP unavailable. No fresh assets or route generation verified. |
| Focused Pest | Attempted foundation/pilot/guard-isolation/shared-auth files; PHP unavailable, so no backend tests executed. |
| Playwright coexistence | Attempted `store-staff-coexistence.spec.ts`; the configured PHP web server could not start. Neither browser test executed. |
| Completion gate | `python3 .claude/scripts/pre_push_check.py`: ESLint, Prettier, vue-tsc and all 113 Vitest files/1,586 tests passed. Exit 1 at the Pint step: `FileNotFoundError: composer`. Pint, PHPStan and Pest did not execute; the gate is incomplete. |

**Remaining work and prerequisites:**

- Provision PHP 8.5 with the required extensions and Composer in the execution
  environment, preserving lockfiles/platform requirements. Then regenerate
  Wayfinder, build, and run the foundation/pilot tests, `LegacyStoreAuthTest`,
  guard-isolation/shared-auth/security tests, PHP formatting/static analysis,
  and Playwright. Current backend conclusions are source review, with runtime
  verification pending.
- The pilot's browser spec covers native delete confirmation, a sidebar visit
  to Blade and logout's Blade redirect. It does not cover store RTL, language
  switching or dark contrast; add those checks before claiming §3(11) complete.
  `RoleControllerTest` verifies pagination but lacks its own allowed/refused
  `per_page` cases required by §3(9).
- Session guards isolate authentication and authorization, but both existing
  logout controllers call `session()->invalidate()`. Laravel flushes the whole
  shared session, including both guards' login keys if both are present.
  Existing logout tests check the selected guard and redirect only. Preserve
  this existing behavior here; independent logout semantics require an owner
  decision and coverage for both active guards, including remember cookies.
- The integrated route/file inventory is in §§4–6. Product search and dedicated
  settings boundaries are implemented; unconverted handlers retain their needed
  exclusions. Production permission vocabulary and role grants still require
  operational review. The owner decided that sellers and classified products
  are converted, not deleted (later steps), and approved Tiptap (§18), Leaflet
  (in use since §30) and Chart.js as frontend dependencies (§5). The
  server-status deletion was decided by the owner and is done (§29).

## 3. Per-module checklist (the staff pattern)

Do every step for each module, in one change per batch.

1. **Before coding**
   - Read the module's routes in §6 and conflict decisions in §5.
   - Trace remaining consumers before deleting files (§5.1).
   - Check §7.1 of the legacy rule for permission names that don't exist yet.
     A batch with ungated routes adds the names first.
2. **Controller**
   - Create `App\Http\Controllers\Store\Admin\<Name>Controller` with
     `HasMiddleware`, keeping the existing `permission:` names per action.
   - Delete the root-namespace legacy controller.
   - Remove it from both `pint.json` and `phpstan.neon` in the same change
     (`StaticAnalysisExclusionsTest`). A legacy model you clean up comes out
     of both lists too (`Staff`).
3. **Pages and resources**
   - Each page renders `Inertia::render('Store/Admin/<Module>/<Resource>/<Page>')`.
   - **Lists:**
     - take a `Filter<X>Request` using `FiltersPerPage` and
       `resolvePerPage($request, 20)`;
     - query with an explicit `select([...])`, scoped eager loads, `orderBy`
       and `paginate()->withQueryString()`;
     - return `$this->paginated(<X>Resource::collection(...))` plus a
       `filters` prop.
   - **Resources:** in `App\Http\Resources\Store`, documented with
     `@property-read`.
4. **Writes and records**
   - **Validation:** `Store<X>Request`/`Update<X>Request` in
     `App\Http\Requests\Store\Admin`, never inline `validate()`.
     - Rules shared by 2+ requests go in a `Concerns` trait exposing
       `xRules()`.
     - Validate references with `Rule::exists(...)->where('guard_name', 'store')`
       rather than `findOrFail()`, so the response is a 422 with an error, not
       a 404.
   - **Binding:** bind models (`{staff}`) in place of `$id`/`decrypt($id)`.
   - **Record invariants** (Super Admin rows, one's own account): abort 403 in
     the controller. A policy would not work, because `Gate::before()` would
     pass a Super Admin through it.
   - **Redirects:** `redirect()->route('<name>.index')` after a write, and
     `redirectPreservingQuery()` after a delete. No `back()`, no `flash()`.
5. **Routes**
   - Rewrite the module's block of `routes/shop-admin.php` as explicit
     `Route::get/post/put/delete` calls on the new controller.
   - Keep the route names.
   - Drop dead routes: empty `show` methods, missing methods, unused forms.
   - Deletes become `DELETE`. `LegacyStoreStateChangingRoutesTest` accepts
     POST or DELETE.
   - Run `php artisan wayfinder:generate --skip-routes --with-form`.
6. **Vue pages**
   - Put them in `resources/js/pages/Store/Admin/<Module>/<Resource>/`.
   - Wrap them in `StoreAdminLayout`. Forms use `ResourceFormPage` with
     `:layout="StoreAdminLayout"`, plus `wide` for grid forms.
   - **Reuse** `DataTable`, `PageHeader`, `AddResourceLink`, `TableActionLink`,
     `Pagination` (with the per-page selector), `FormField`, `FieldError`,
     `SubmitButton` and `lib/confirm.ts`.
   - Put a module's field group in `components/Store<Resource>FormFields.vue`.
   - Gate buttons with `useStoreAccess().can('<permission>')`, using the same
     names as the middleware.
   - Use Wayfinder actions, with:
     - `index` aliased;
     - model objects as params;
     - `{ mergeQuery: {} }` on deletes.
   - Give every field the native HTML5 constraints that mirror its rules.
7. **Strings and menu**
   - Add `store.<module>.*` keys to both `en.json` and `ar.json`.
   - Flip the module's entries in `lib/storeAdminMenu.ts` to `inertia: true`,
     and update the Inertia list in `storeAdminMenu.test.ts`.
8. **Delete the Blade views** with `git rm`, along with any partial only they
   used.
9. **Pest**
   - Write `tests/Feature/Store/Admin/<Resource>ControllerTest.php`, covering
     per action:
     - a render test;
     - a full-payload Inertia assertion for one record, scoped with closures;
     - validation;
     - 404 for a missing id on edit, update and destroy;
     - the `refused store admin callers` dataset, with a DB-unchanged
       assertion on every write;
     - for lists: more rows than a page, plus an allowed and a refused
       `per_page`.
   - Helpers are in tests/Pest.php: `createStoreStaff(...)`,
     `createStaffRow()`, `actingAsRefusedStoreCaller()`,
     `assertStoreCallerRefused()`.
   - Move the module's existing cases out of the `Legacy*Test` files rather
     than duplicating them.
   - Update the `LegacyAdminPermissionsTest` ratchet lists if a gate changed.
     Staff needed no change: every one of its routes was already gated.
10. **Vitest**
    - Full-mount every page and new component.
    - Assert:
      - the child props (`ResourceFormPage`, the form fields, `TableActionLink`
        hrefs, `Pagination`);
      - every static label;
      - permission-gated buttons, both ways;
      - the `confirmDelete` wiring.
    - Add fixtures to `testing/factories.ts`.
11. **Playwright**
    - Write a `tests/e2e/store-*.spec.ts` for browser-only behaviour: native
      confirms, RTL arrows, dark-mode contrast.
    - `loginAsStoreAdmin()` signs in as the seeded
      `store-admin@example.com` / `password` (`StoreUserSeeder`).
12. **Verify:** run `python3 .claude/scripts/pre_push_check.py`.

**What the staff pilot changed in behaviour.** Keep these deliberate in later
modules too:

- **Routes and URIs**
  - Dead routes are gone: the empty `roles.show` and `staffs.show`,
    `roles.permission` (only a commented-out form used it) and
    `/admin-permissions` (an empty method).
  - URIs are now `/staffs/{staff}/edit`, which was an encrypted id, and
    `DELETE /staffs/{staff}` / `DELETE /roles/{role}`, which were
    `POST …/destroy/{id}`.
- **Super Admin**
  - The Super Admin role can no longer be edited or deleted by URL.
- **Validation**
  - A staff password now needs its confirmation.
  - A wrong role is a 422, not a 404.
  - A role name is limited to 50 characters, the `role_translations.name`
    width, and must be unique among store roles.
  - The `lang` of a role edit must be the default language or a row of
    `languages`.
- **Lists**
  - They page 20 at a time, with a per-page selector; it was 10.
  - Staff rows with no account are left out.
- **Forms**
  - Saving a role returns to the list, not the form.
  - No success flash.
  - The role form never offers the removed addons' permission sections
    (`RoleController::REMOVED_ADDON_SECTIONS`).


## 4. Integrated status (2026-10-18)

This reflects integrated code and the carrier, customer-package, category,
brand, digital-product, in-house/all-products list, general-settings,
blog-editor, newsletter/ticket-detail, website-settings, digital-product
form, physical product create and edit form, trip list, trip form, trip
orders, trip detail, legacy reviews retirement, server status deletion and trip map
picker, representative visit detail, Google Map settings and dashboard
follow-ups in §§11–33. The original
reconciliation was based on `8e3f82b60b2b4dc951b97ccfed985a75e9e6be69` and
merged through PR #377. Carrier administration was then implemented separately
from latest `origin/develop` at `018dd1802c0b2fc99ffbf0e097594fe0f0f3dfef`.
Verification below distinguishes the original reconciliation from this follow-up.

| Module/subset | State | Actual behavior / remaining work |
| --- | --- | --- |
| Staff and roles | Complete pilot | Existing six Inertia pages retained; action gates unchanged |
| Reference/shipping/tax/currency | Complete selected subset | Canonical `35ae3133` controllers/forms/routes; duplicate `e5523338` implementation superseded |
| Carrier administration | Complete | List/create/edit and CRUD/status use Inertia; existing `manage_carriers` gate, tariff contract and public/API consumers preserved (§11) |
| Language/settings | Complete selected subset | Language CRUD and translation-selector validation separated; dedicated settings endpoints with reserved-key protection; general settings use Inertia with a typed writer and a config-managed timezone (§17); the generic `business_settings.update` writer is retired (§20); the Google Map settings GET uses Inertia and the approved Leaflet picker (§32) |
| Reports/notifications | Complete selected subset | Inertia report/admin notification routes; public/customer/seller notification consumers remain |
| Uploads/profile | Complete selected subset | Canonical `944e2e1e` backend/picker; per-action authorization and authenticated-user profile |
| Customers/representatives | Partial | Customers, addresses, representatives/packages/payments, customer package administration (§12) and visit list/detail/day maps (§31) implemented; classified products are to be converted (owner decision, §5), a later step |
| Catalogue | Partial | Attributes/values, colors, brand discount, reviews (the legacy Blade list retired, §28), bulk import/export and offers catalogue implemented; category (§13) and brand (§14) administration complete; digital product list, deletion, download and switches converted (§15); digital product create/edit converted on the shared editor and canonical uploader (§21); in-house and all-products lists with their switches, duplicate, delete and bulk delete converted (§16); physical product create converted with its typed variant/SKU endpoint (§22); physical product edit converted with translation tabs and stored variant rows (§23); the seller product list stays Blade with seller administration |
| Website/support | Complete selected subset | Header/home, footer, appearance, custom pages (§20), conversations, product queries, the ticket list and the ticket detail with its typed reply use Inertia; footer, appearance and page rich text use the shared editor, cleaned on write (§20); the header/footer scripts stay on their compatibility endpoint as plain code fields; script policy, `manage_website_scripts` and rich-text WAF exceptions stay held |
| Marketing/blog | Complete selected subset | Coupons, flash/offer deals, campaign products, popups, subscribers and blog categories/list/actions integrated; blog post create/edit (§18) and the newsletter form (§19) use Inertia with the shared Tiptap editor; the typed newsletter send and bounded recipient search are unchanged |
| Logistics | Partial | Drivers/trucks converted; the all-trips and today's-trips lists with their status dialog, notes, delete and bulk delete converted (§24); the trip create and edit forms converted without their map (§25); the trip orders list with its bulk status, invoice number and delivered actions converted (§26); the trip detail with its checkpoint notes, status and CMR download converted without its map (§27); the trip map picker converted on Leaflet from npm (§30); visit list/detail/day maps use Inertia and bundled Leaflet (§31) |
| Orders/dashboard | Complete selected subset | Orders and entry forms converted; stored tax metadata and line-price-only grand total preserved; shell utilities integrated; dashboard converted to Inertia with bundled Chart.js (§33) |
| Seller administration | To convert | Existing seller Blade screens remain, including the seller product list (`products.seller`, §16); the owner decided they are converted, not deleted (§5), in later steps |
| Public/seller/auth/PDF consumers | Retained | Blade coexistence, PDF layouts, Inertia root and CSP middleware remain |

### 4.1 Seller prerequisite and role grants

`1c005f6efdddee958ba8d739afd35eb0935007bd` records the prerequisite review.
Feature flags and checked-in owner-approval claims did not establish seller or
classified feature-use approval; the owner has since decided (2026-10-17, §5)
that the seller administration and the customer classified products are
converted, not deleted, in later steps. Until each converts, keep the existing
seller area and customer classified-product controllers/routes/views. Customer package administration is converted in §12; its purchase, payment and mobile API consumers remain separate.

Union permission definitions while retaining per-action gates. Idempotent
definition migrations do not assign permissions to existing roles and do not
delete pre-existing grants on rollback. Production role assignments must be
reviewed explicitly; creating a permission does not authorize staff. The seller
commission activation Blade form retains its `{type,value}` contract through a
dedicated allowlisted compatibility endpoint, not a general settings writer.

## 5. Conflict decisions and retained work

- `StoreBooleanField` has one `name`/`value`/`disabled` API. Adapt callers.
- Dedicated settings requests reject foreign/reserved keys before writing,
  including plain/localized forms. The generic settings writer
  (`business_settings.update`) and its four-permission gate are retired
  since §20: its last consumers, the footer and appearance Blade forms, moved
  to typed writers that accept only their own keys, so no settings writer
  takes arbitrary keys any more.
- The timezone is config-managed: `config('app.timezone')` is `'UTC'` in
  `config/app.php`, and no settings screen stores it. The general settings
  writer validates it against PHP's identifiers (plus the configured value)
  and refuses any other value with a field error; nothing writes `.env`.
- Website scripts retain the existing rendering, appearance permission and WAF
  behavior through the narrowly scoped compatibility endpoint, now posted by a
  plain-code form on the Inertia appearance page (§20). New script policy,
  `manage_website_scripts` activation and broad rich-text WAF exceptions are held.
- Profile and notifications use selected controllers in the Inertia shell;
  public/seller/customer handlers remain without restoring deleted admin Blade.
- `ResourceFormPage` has one `after-form` slot inside its width wrapper outside
  the Form. Shared types, casts/relations, resources and fixtures are additive.
- English/Arabic translation leaf keys are merged, retaining selected upload
  namespace shapes rather than replacing whole translation objects.
- Preserve bounded translated catalogue product search while selected reference
  forms use its existing route name. Preserve upload URL safety and download/PDF
  behavior. No Composer manifest or lock changes; the frontend manifests
  changed only for the owner-approved Tiptap packages (§18) and Leaflet with
  its types (§30), and Chart.js (§33).
- **Tiptap: owner-approved and active (2026-10-13, §18).** The owner approved
  Tiptap as the shared back-office editor. `StoreRichTextField` is the one
  rich-text field for converted screens; blog post create/edit (§18), the
  newsletter and the ticket reply (§19) use it. The approval lifts the editor
  hold on the website footer, appearance and custom pages (migrated, §20),
  the digital product create/edit forms (migrated, §21) and the physical
  product create and edit forms (migrated, §§22–23); the script
  policy, `manage_website_scripts` and rich-text WAF exceptions stay held. The
  Blade editor (summernote in `public/assets/js/vendors.js`, started by
  `aiz-core.js` `textEditor()`) and its assets stay while those Blade
  consumers remain: 8 fields in 8 views since §23.
- **Leaflet and Chart.js: owner-approved (2026-10-17).** The owner approved
  both as frontend dependencies, installed from npm and bundled through Vite,
  never from a CDN. Leaflet is active since §30: `leaflet` 1.9.4 (dependency)
  and `@types/leaflet` 1.9.22 (dev dependency), pinned exactly as the lockfile
  resolves them (it adds `@types/geojson` 7946.0.16 for the types); the trip
  map picker is its first consumer, with OpenStreetMap tiles (the provider
  the Blade maps already used) allowed in the strict `img-src` (§2.5).
  Chart.js is active since §33: `chart.js` **4.5.1**, pinned exactly, with
  `@kurkle/color` 0.3.4 resolved by npm. Only bar/doughnut controllers, arc/bar
  elements, category/linear scales and legend/tooltip plugins are registered.
  Canvas rendering needs no CSP change.
  The visit detail/day maps use the shared Leaflet base since §31; Google Map settings reuse it in §32, replacing only the admin picker with OpenStreetMap tiles, without widening CSP or changing the storefront Google Maps partial. Their unpkg
  Leaflet 1.7.1 imports are removed. The shared AIZ chart helper and vendor assets stay: the seller dashboard
  still calls `AIZ.plugins.chart`.
- **Sellers and classified products: convert, not delete (owner decision,
  2026-10-17).** The seller administration screens (§5.1 `sellers/`, the
  seller product list) and the customer classified products are converted in
  later steps, keeping their routes and gates; none is deleted.
- **`products` writers: owner decision pending, behaviour unchanged.** Brand
  and digital/product deletion (§§14–16), product duplication (§16),
  digital product create/update (§21) and physical product create and
  update (§§22–23) still write WMS-owned `products` rows
  beside `products:sync`, the table's single writer (shared-database.md).
  Each now runs in one transaction and is pinned by tests; changing
  who may write `products` needs the owner's decision.
- Product list switches share one write path. The digital-product and
  in-house/all-products lists publish and feature through
  `Concerns\UpdatesStoreProductFlags`, scoped to the products each list shows.
  The root `ProductController` jQuery endpoints stay unchanged for the Blade
  seller list (§16).
- Order tax changes are held: the established entry handlers stored raw product
  tax metadata and summed line prices for grand total. Percentage conversion and
  tax/shipping/coupon adjustments need a business decision.
- Preserve departure ID 9 behavior (the map picker's writer keeps it, §30), Seller app link behavior, shared-session
  logout semantics. Missing data is handled without inventing
  new business policy. Shared image-normalization changes remain a policy gap.
- Remove static-analysis/legacy-test exclusions only for migrated or cleaned
  handlers. Root controllers still serving public/Blade consumers remain excluded
  where necessary. Do not remove legacy shells/assets/includes/CSP exceptions
  while consumers remain. `HandleStoreBladePages` and `store.blade_csp` are still
  required for storefront, seller and authentication pages.

### 5.1 Actual retained backend Blade files

The current backend inventory contains **25 Blade files**, down from 73 after
removing three screens each for carriers, customer packages and categories, the
two brand screens, the digital product list (§15), general settings (§17), the
two blog post forms (§18), the newsletter and ticket detail (§19), the
footer, appearance and three custom-page screens (§20), the two digital
product forms (§21), the physical product create form (§22), and the
physical product edit form, its choice script and the unconsumed blank shell
(§23), the trip list with its status modal (§24), the two trip forms
with their two checkpoint row partials (§25), the trip orders list
(§26), the trip checkpoints page with its status modal (§27), the legacy product
reviews list (§28), the server status page (§29), and the trip map picker with
its three `trips/inc` partials (§30), and the two representative visit
detail screens with their map partial (§31), Google Map settings (§32) and the
admin dashboard (§33).
The in-house and all-products lists (§16) removed none:
`product/products/index.blade.php` is shared with the retained seller product
list. Google Map settings are converted in §32.
These files remain physically present. Hardening a Blade handler does not count
as conversion. Protected shells/downloads/invoices are not conversion targets.

- **customer:** `customer/classified_products/index.blade.php`.
- **downloads:** `downloads/brand.blade.php`, `downloads/category.blade.php`, `downloads/user.blade.php`.
- **inc:** `inc/admin_nav.blade.php`, `inc/admin_sidenav.blade.php`.
- **invoices:** `invoices/invoice.blade.php`.
- **layouts:** `layouts/app.blade.php`, `layouts/layout.blade.php`.
- **product:** `product/products/index.blade.php`, `product/products/sku_combinations.blade.php`, `product/products/sku_combinations_edit.blade.php`.
- **sellers:** `sellers/create.blade.php`, `sellers/edit.blade.php`, `sellers/index.blade.php`, `sellers/payment.blade.php`, `sellers/payment_histories/index.blade.php`, `sellers/payment_modal.blade.php`, `sellers/profile_modal.blade.php`, `sellers/seller_commission/index.blade.php`, `sellers/seller_verification_form/index.blade.php`, `sellers/seller_withdraw_requests/index.blade.php`, `sellers/seller_withdraw_requests/payment_modal.blade.php`, `sellers/seller_withdraw_requests/withdraw_message_modal.blade.php`, `sellers/verification.blade.php`.

## 6. Registered migrated controllers and consumers

Rebuilt from `php artisan route:list --path=temp --json` and actual controller
render calls. Paths below are relative to `resources/js/pages/`. A listed
controller may also return JSON, redirects, PDFs or retained Blade: those are
identified explicitly. Route names are authoritative; do not copy source-branch
line numbers or count sibling snapshots.

| Controller in `App\Http\Controllers\Store\Admin` | Registered route names | Inertia pages / other consumers |
| --- | --- | --- |
| `AddressController` | `admin.addresses.cities`, `admin.addresses.states`, `admin.customers.addresses.store`, `admin.customers.addresses.update`, `admin.customers.addresses.destroy`, `admin.customers.addresses.default` | JSON / redirects / PDF as applicable |
| `AttributeController` | `attributes.index`, `attributes.store`, `attributes.destroy`, `attributes.edit`, `attributes.update` | `Store/Admin/Product/Attributes/Index.vue`, `Store/Admin/Product/Attributes/Edit.vue` |
| `AttributeValueController` | `attributes.show`, `destroy-attribute-value`, `edit-attribute-value`, `store-attribute-value`, `update-attribute-value` | `Store/Admin/Product/AttributeValues/Index.vue`, `Store/Admin/Product/AttributeValues/Edit.vue` |
| `BlogCategoryController` | `blog-category.index`, `blog-category.store`, `blog-category.create`, `blog-category.update`, `blog-category.destroy`, `blog-category.edit` | `Store/Admin/Blog/Categories/Index.vue`, `Store/Admin/Blog/Categories/Create.vue`, `Store/Admin/Blog/Categories/Edit.vue` |
| `BlogController` | `blog.index`, `blog.store`, `blog.create`, `blog.update`, `blog.destroy`, `blog.edit`, `blog.change-status` | `Store/Admin/Blog/Posts/Index.vue`, `Store/Admin/Blog/Posts/Create.vue`, `Store/Admin/Blog/Posts/Edit.vue`; the public blog stays on root `BlogController` (Blade) |
| `BrandController` | `brands.index`, `brands.store`, `brands.edit`, `brands.update`, `brands.destroy` | `Store/Admin/Product/Brands/Index.vue` (list and inline add), `Edit.vue`; public/seller/mobile brand consumers and `Api/V2/BrandController` retained |
| `BrandDiscountController` | `brands_wise_product_discount`, `set_product_discount` | `Store/Admin/Product/BrandDiscounts/Index.vue` |
| `CampaignProductController` | `admin.campaign-products.brands`, `admin.campaign-products.index` | JSON / redirects / PDF as applicable |
| `CarrierController` | `carriers.index`, `carriers.create`, `carriers.store`, `carriers.edit`, `carriers.update`, `carriers.destroy`, `carriers.update_status` | `Store/Admin/Setup/Carriers/Index.vue`, `Create.vue`, `Edit.vue`; public/API carrier controller remains separate |
| `CategoryController` | `categories.index`, `categories.create`, `categories.store`, `categories.edit`, `categories.update`, `categories.destroy`, `categories.featured` | `Store/Admin/Product/Categories/Index.vue`, `Create.vue`, `Edit.vue`; public/seller/mobile category consumers and shared hierarchy utility retained |
| `CityController` | `cities.index`, `cities.store`, `cities.update`, `cities.destroy`, `cities.edit`, `cities.status` | `Store/Admin/Setup/Cities/Index.vue`, `Store/Admin/Setup/Cities/Edit.vue` |
| `ColorController` | `colors`, `colors.destroy`, `colors.edit`, `colors.store`, `colors.update` | `Store/Admin/Product/Colors/Index.vue`, `Store/Admin/Product/Colors/Edit.vue` |
| `ConversationController` | `conversations.admin_index`, `conversations.admin_destroy`, `conversations.admin_reply`, `conversations.admin_show` | `Store/Admin/Support/Conversations/Index.vue`, `Store/Admin/Support/Conversations/Show.vue` |
| `CountryController` | `countries.index`, `countries.status` | `Store/Admin/Setup/Countries/Index.vue` |
| `CouponController` | `coupon.index`, `coupon.store`, `coupon.create`, `coupon.product-options`, `coupon.update`, `coupon.destroy`, `coupon.edit` | `Store/Admin/Marketing/Coupons/Index.vue`, `Store/Admin/Marketing/Coupons/Create.vue`, `Store/Admin/Marketing/Coupons/Edit.vue` |
| `CurrencyController` | `currency.index`, `currency.create`, `currency.settings.update`, `currency.store`, `currency.edit`, `currency.update_status`, `your_currency.update` | `Store/Admin/Setup/Currencies/Index.vue` |
| `CustomerController` | `bulk-customer-delete`, `change_password`, `customers.important`, `customers.not_important`, `customers.store`, `customers.whatsapp_contact`, `customers.page-views`, `customers.index`, `customers.archive`, `customers.create`, `download.trade_license`, `customers.login`, `customers.restore`, `customers.sendAgreement`, `customers.verify`, `customers.update`, `customers.destroy`, `customers.change_activity_status`, `customers.edit`, `customers.update_customer_notes`, `update_password`, `customers.ban`, `representatives.index`, `representatives.create`, `representatives.store` | `Store/Admin/Customers/Customers/Index.vue`, `Store/Admin/Customers/Customers/Create.vue`, `Store/Admin/Customers/Customers/Edit.vue`, `Store/Admin/Customers/Customers/Views.vue` |
| `CustomerPackageController` (Store/Admin) | `customer_packages.index`, `customer_packages.create`, `customer_packages.store`, `customer_packages.edit`, `customer_packages.update`, `customer_packages.destroy` | `Store/Admin/Customers/Packages/Index.vue`, `Create.vue`, `Edit.vue`; root purchase/payment controller and mobile API retained |
| `CustomerNotificationController` | `admin.notifications.all`, `admin.notifications.create`, `admin.notifications.send`, `admin.notifications.delete` | `Store/Admin/Notifications/Customers/Index.vue`, `Store/Admin/Notifications/Customers/Create.vue` |
| `DashboardController` | `shop.admin.dashboard`, `cache.clear` | `Store/Admin/Dashboard/Index.vue` (§33); scoped cache clear retained |
| `DigitalProductController` (Store/Admin) | `digitalproducts.index`, `digitalproducts.create`, `digitalproducts.store`, `digitalproducts.edit`, `digitalproducts.update`, `digitalproducts.destroy`, `digitalproducts.download`, `digitalproducts.published`, `digitalproducts.featured`, `digitalproducts.todays_deal` | `Store/Admin/Product/DigitalProducts/Index.vue`, `Create.vue`, `Edit.vue` (§21); store → list, update → edit form in the same language; download → file response; root `DigitalProductController` removed; seller, mobile and storefront digital product and download consumers separate |
| `DriverController` | `drivers.create`, `drivers.index`, `drivers.store`, `drivers.update`, `drivers.destroy`, `drivers.edit` | `Store/Admin/Trips/Drivers/Index.vue`, `Store/Admin/Trips/Drivers/Create.vue`, `Store/Admin/Trips/Drivers/Edit.vue` |
| `DynamicPopupController` | `bulk-dynamic-popup-delete`, `dynamic-popups.index`, `dynamic-popups.store`, `dynamic-popups.update-status`, `dynamic-popups.create`, `dynamic-popups.update`, `dynamic-popups.destroy`, `dynamic-popups.edit` | `Store/Admin/Marketing/DynamicPopups/Index.vue`, `Store/Admin/Marketing/DynamicPopups/Create.vue`, `Store/Admin/Marketing/DynamicPopups/Edit.vue` |
| `FlashDealController` | `flash_deals.index`, `flash_deals.store`, `flash_deals.create`, `flash_deals.update`, `flash_deals.destroy`, `flash_deals.edit`, `flash_deals.update_featured`, `flash_deals.update_status` | `Store/Admin/Marketing/FlashDeals/Index.vue`, `Store/Admin/Marketing/FlashDeals/Create.vue`, `Store/Admin/Marketing/FlashDeals/Edit.vue` |
| `GeneralSettingsController` | `general_setting.index`, `general_setting.update` | `Store/Admin/Setup/GeneralSettings/Index.vue` |
| `GoogleMapController` | `google-map.index`, `google-map.update` | `Store/Admin/Setup/GoogleMap/Index.vue` with `StoreGoogleMapFormFields` and the shared npm Leaflet base (§32); writer redirects to the same index |
| `LanguageController` | `languages.index`, `languages.store`, `app-translations.export`, `app-translations.import`, `app-translations.store`, `app-translations.show`, `languages.create`, `languages.key_value_store`, `languages.update-status`, `languages.update_rtl_status`, `languages.destroy`, `languages.show`, `languages.edit`, `languages.update` | `Store/Admin/Languages/Index.vue`, `Store/Admin/Languages/Create.vue`, `Store/Admin/Languages/Edit.vue`, `Store/Admin/Languages/Translations.vue` |
| `LegacyWebsiteScriptsController` | `website.scripts.compatibility.update` | Redirect to `website.appearance`; posted by the plain-code scripts form of `Store/Admin/Website/Appearance.vue` (§20) |
| `NewsletterController` | `newsletters.index`, `newsletters.recipients`, `newsletters.send` | `Store/Admin/Marketing/Newsletters/Index.vue` (§19); typed send redirecting to the Inertia dashboard; bounded JSON recipient search |
| `NotificationController` | `admin.all-notification` | `Store/Admin/Notifications/Index.vue` |
| `OfferController` | `offer_deals.brands.create`, `offer_deals.create`, `offer_deals.index`, `offer_deals.store`, `offer_deals.update`, `offer_deals.destroy`, `offer_deals.edit`, `offer_deals.update_multiple_usage`, `offer_deals.update_repeatable`, `offer_deals.update_status` | `Store/Admin/Marketing/Offers/Index.vue`, `Store/Admin/Marketing/Offers/Create.vue`, `Store/Admin/Marketing/Offers/Edit.vue` |
| `OffersCatalogController` | `offers.destroy`, `(unnamed compatibility)`, `offers.index`, `offers.store` | `Store/Admin/Catalogue/Offers/Index.vue` |
| `OrderConfigurationController` | `order_configuration.index`, `order_configuration.update`, `order_configuration.representative.update` | `Store/Admin/Setup/OrderConfiguration/Index.vue` |
| `OrderController` | `all_orders.index`, `bulk-order-delete`, `inhouse_orders.index`, `inhouse_orders.show`, `orders.details`, `order.item.edit_price`, `orders.update_delivery_status`, `orders.update_manager_notes`, `orders.update_payment_status`, `orders.update_tracking_code`, `orders.show`, `orders.destroy`, `all_orders.show`, `pick_up_point.index`, `pick_up_point.order_show`, `seller_orders.index`, `seller_orders.show` | `Store/Admin/Sales/Orders/Index.vue`, `Store/Admin/Sales/Orders/Show.vue`; `bulk-order-delete` returns to the trip orders list for `return_to=trip_orders` (§26) |
| `OrderEntryController` | `new.feat-order.trip.create`, `new.feat-order.trip.edit`, `new.feat-order.trip.store`, `new.feat-order.trip.update`, `feat-order.create`, `feat-order.store`, `orders.search-options` | `Store/Admin/Sales/Orders/Create.vue`, `Store/Admin/Sales/Orders/Edit.vue` |
| `PickupPointController` | `pick_up_points.index`, `pick_up_points.store`, `pick_up_points.create`, `pick_up_points.update`, `pick_up_points.destroy`, `pick_up_points.edit` | `Store/Admin/Setup/PickupPoints/Index.vue`, `Store/Admin/Setup/PickupPoints/Edit.vue`, `Store/Admin/Setup/PickupPoints/Create.vue` |
| `ProductController` (Store/Admin) | `products.admin`, `products.all`, `products.list.published`, `products.list.featured`, `products.list.for_sale`, `products.list.duplicate`, `products.list.destroy`, `products.list.bulk_destroy`, `products.create`, `products.store`, `products.variant_combinations`, `products.admin.edit`, `products.seller.edit`, `products.update`, `products.edit_variant_combinations` | `Store/Admin/Product/Products/Index.vue` (both lists, `listType`), `Create.vue` (§22; store → in-house list, `products.variant_combinations` → JSON variant rows), `Edit.vue` (§23; both edit routes, `listType` `admin`/`seller`; update → the edit form in the same language; `products.edit_variant_combinations` → JSON rows keeping stored stock values); `products.admin.edit`/`seller.edit` send a digital product to `digitalproducts.edit` (§21); `products.seller`, the jQuery switches, approval, duplicate, destroy, bulk delete, `products.fetch` (JSON) and the Blade SKU helper `products.sku_combination` stay on root `ProductController` for the Blade seller list |
| `ProductBulkUploadController` | `bulk_product_upload`, `pdf.download_brand`, `pdf.download_category`, `pdf.download_seller`, `product_bulk_export.index`, `product_bulk_upload.index` | `Store/Admin/Product/BulkUpload/Index.vue` |
| `ProductQueryController` | `product_query.index`, `product_query.show`, `product_query.reply` | `Store/Admin/Support/ProductQueries/Index.vue`, `Store/Admin/Support/ProductQueries/Show.vue` |
| `ProductSearchController` | `order_configuration.product-search`, `products.add-more-choice-option` | JSON / redirects / PDF as applicable |
| `ProfileController` | `profile.index`, `profile.update` | `Store/Admin/Profile/Index.vue` |
| `ReportController` | `commission-log.index`, `in_house_sale_report.index`, `seller_sale_report.index`, `stock_report.index`, `user_search_report.index`, `wallet-history.index`, `wish_report.index` | `Store/Admin/Reports/CommissionHistory.vue`, `Store/Admin/Reports/InHouseSales.vue`, `Store/Admin/Reports/Searches.vue`, `Store/Admin/Reports/SellerSales.vue`, `Store/Admin/Reports/Stock.vue`, `Store/Admin/Reports/WalletHistory.vue`, `Store/Admin/Reports/Wishlist.vue` |
| `RepresentativePackageController` | `rep.packages.create`, `rep.packages.destroy`, `rep.packages.index`, `rep.set-package`, `rep.packages.store`, `rep.packages.update`, `rep.packages.edit` | `Store/Admin/Representatives/Packages/Index.vue`, `Store/Admin/Representatives/Packages/Create.vue`, `Store/Admin/Representatives/Packages/Edit.vue` |
| `RepresentativePaymentController` | `admin.rep.payments.accept`, `admin.rep.payments.export`, `admin.rep.payments.index`, `admin.rep.payments.reject` | `Store/Admin/Representatives/Payments/Index.vue` |
| `ReviewController` | `admin.reviews.index`, `reviews.published`, `reviews.index` | `Store/Admin/Product/Reviews/Index.vue`; `reviews.index` (GET `/temp/reviews`, `view_product_reviews`) redirects to `admin.reviews.index` keeping the query (§28); root `ReviewController` keeps the customer `store` and `product_review_modal` |
| `RoleController` | `roles.index`, `roles.store`, `roles.create`, `roles.update`, `roles.destroy`, `roles.edit` | `Store/Admin/Staff/Roles/Index.vue`, `Store/Admin/Staff/Roles/Create.vue`, `Store/Admin/Staff/Roles/Edit.vue` |
| `SettingsController` | `activation.index`, `business_settings.update.activation`, `facebook_chat.index`, `facebook_chat.update`, `facebook-comment`, `facebook-comment.update`, `file_system.index`, `google_analytics.index`, `google_analytics.update`, `facebook_pixel.update`, `google-firebase.index`, `google-firebase.update`, `google_recaptcha.index`, `google_recaptcha.update`, `payment_method.index`, `payment_method.update`, `smtp_settings.index`, `test.smtp`, `social_login.index` | `Store/Admin/Settings/Index.vue` |
| `ShippingConfigurationController` | `shipping_configuration.index`, `shipping_configuration.update` | `Store/Admin/Setup/ShippingConfiguration/Index.vue` |
| `StaffController` | `staffs.index`, `staffs.store`, `staffs.create`, `staffs.update`, `staffs.destroy`, `staffs.edit` | `Store/Admin/Staff/Staffs/Index.vue`, `Store/Admin/Staff/Staffs/Create.vue`, `Store/Admin/Staff/Staffs/Edit.vue` |
| `StateController` | `states.index`, `states.store`, `states.update`, `states.destroy`, `states.edit`, `states.status` | `Store/Admin/Setup/States/Index.vue`, `Store/Admin/Setup/States/Edit.vue` |
| `SubscriberController` | `admin.subscribers.index`, `subscriber.destroy` | `Store/Admin/Marketing/Subscribers/Index.vue` |
| `SupportTicketController` | `support_ticket.admin_index`, `support_ticket.admin_store`, `support_ticket.admin_show` | `Store/Admin/Support/Tickets/Index.vue`, `Show.vue` (§19); reply → redirect to the detail; customer/seller ticket pages stay on root/seller `SupportTicketController` (Blade) |
| `TaxController` | `tax.index`, `tax.store`, `tax.update`, `tax.destroy`, `tax.edit`, `taxes.tax-status` | `Store/Admin/Setup/Taxes/Index.vue`, `Store/Admin/Setup/Taxes/Edit.vue` |
| `TripController` (Store/Admin) | `trips.index`, `trips.today-trips`, `trips.create`, `trips.store`, `trips.edit`, `trips.update`, `trips.update-status`, `trips.update-notes`, `trips.destroy`, `trips.bulk-trip-delete` | `Store/Admin/Trips/Trips/Index.vue` (both lists, `listType` `all`/`today`) with `StoreTripStatusDialog` (§24); `Create.vue` and `Edit.vue` with `StoreTripFormFields` (§25; store and update → the trip detail `trips.show`, §27); list writes redirect to the screen named by `list` (`all`, `today`, or `map` → the Inertia map picker, whose busy truck cards send `trips.destroy` as a DELETE, §30); the map picker and its writer are `TripMapController`; the forms' vehicle lookup `trips.available-vehicles` (JSON) stays on `Api\V2\Trip\DriverController` |
| `TripCheckpointController` | `trips.show`, `checkpoints.update-notes`, `checkpoints.update-status`, `checkpoints.print-cmr` | `Store/Admin/Trips/Trips/Show.vue` (§27); the notes and status writes redirect to the detail with its query; `checkpoints.print-cmr` redirects to a managed local or HTTP(S) CMR file, else to the detail with a `cmr` error; the map picker's busy truck names link to `trips.show` (§30) |
| `TripOrderController` | `trips.orders`, `trips.orders.bulk-change-delivery-status`, `trips.orders.update_invoice_number`, `trips.orders.update_order_status_to_delivered` | `Store/Admin/Trips/Orders/Index.vue` (§26); writes redirect to the list with its query; `trips.orders.update_order_status_to_delivered` also serves the map picker's selected stops (PUT, `return_to=map` → `trips.create-from-map`, §30) |
| `TripMapController` | `trips.create-from-map`, `trips.store-from-map` | `Store/Admin/Trips/Trips/CreateFromMap.vue` with `StoreTripMap` (Leaflet from npm, §30); the writer (`add_trip`, plus `edit_trip` with `trip_id`) returns to the picker; root `Trip\TripController` removed |
| `TruckController` | `trucks.create`, `trucks.index`, `trucks.photos`, `trucks.store`, `trucks.update`, `trucks.edit`, `trucks.qr` | `Store/Admin/Trips/Trucks/Index.vue`, `Store/Admin/Trips/Trucks/Create.vue`, `Store/Admin/Trips/Trucks/Edit.vue` |
| `UploadController` | `all-file`, `bulk-uploaded-files-delete`, `uploaded-files.index`, `uploaded-files.store`, `uploaded-files.create`, `uploaded-files.files`, `uploaded-files.info`, `uploaded-files.destroy` | `Store/Admin/Uploads/Index.vue`, `Store/Admin/Uploads/Create.vue` |
| `VisitController` | `bulk-visit-delete`, `admin.rep.visits.destroy`, `admin.rep.visits.index`, `admin.rep.visits.show`, `admin.rep.visits.rep-show`, `admin.rep.visits.note`, `visits.note` | `Store/Admin/Representatives/Visits/Index.vue`, `Show.vue`, `RepShow.vue`; both note routes redirect back, with own-visit-only writes (§31); the customer route keeps its middleware |
| `WebsiteAppearanceController` | `website.appearance`, `website.appearance.update` | `Store/Admin/Website/Appearance.vue` (§20) |
| `WebsiteFooterController` | `website.footer`, `website.footer.update` | `Store/Admin/Website/Footer.vue` (§20) |
| `WebsiteHeaderController` | `website.header`, `website.header.update` | `Store/Admin/Website/Header.vue` |
| `WebsiteHomeController` | `website.home.edit`, `website.home.update` | `Store/Admin/Website/Home.vue` |
| `WebsitePageController` | `website.pages`, `custom-pages.create`, `custom-pages.store`, `custom-pages.edit`, `custom-pages.update`, `custom-pages.destroy` | `Store/Admin/Website/Pages/Index.vue`, `Create.vue`, `Edit.vue` (§20); the public `custom-pages.show_custom_page` and `mobile.custom-pages` stay on root `PageController` (Blade) |
| `ZoneController` | `zones.index`, `zones.store`, `zones.create`, `zones.update`, `zones.destroy`, `zones.edit` | `Store/Admin/Setup/Zones/Index.vue`, `Store/Admin/Setup/Zones/Edit.vue`, `Store/Admin/Setup/Zones/Create.vue` |

Visit details/day maps use `Store\Admin\VisitController` since §31.
Google Map settings use `Store\Admin\GoogleMapController` since §32.
The trip map picker is Inertia since §30. Root
`BusinessSettingsController` keeps the seller commission/verification screens and `env_key_update` (the generic settings
writer and its refusing `.env` stub are gone, §20), so it stays in both
analysis exclusion lists. Seller/classified screens use retained root handlers. The newsletter and
the ticket detail use their typed admin controllers and Inertia pages (§19). Explicit approval and implementation gaps are tracked
by source/path in the reconciliation ledger. The unavailable audit commit
`aed3feab5b6edd95fb941ee92a455e058403def5` is excluded and was not recovered or included.


## 7. Independent reconstructed audit (2026-10-11, prompt 3)

This audit starts from integration HEAD `29e5e2527bf7f9920436e5970a4413c46325ed03`.
The unavailable `aed3feab5b6edd95fb941ee92a455e058403def5` remains excluded;
none of its code or claimed verification was recovered. Detailed evidence,
commands and limits are in [the reconciliation ledger](../store-backoffice-reconciliation-20261011.md#independent-reconstructed-audit--prompt-3).

- `HandleStoreBladePages` distinguishes the genuine Inertia root view `app`
  from Blade views, including CMS/policy views with `page` data. Preserve its
  Blade CSP override and 409 `X-Inertia-Location` fallback; do not classify
  arbitrary view variables as an Inertia response.
- Retained backend, frontend and seller uploader layout `app-url` metadata uses
  `url('/temp')`, preserving request scheme/port. File-base metadata uses existing
  `my_asset('')`, avoiding obsolete `/public/` and lost-port thumbnail URLs. Do not change shared asset URL
  helpers as a side effect. The selected Vue resource remains `id,name,type,size,url`.
- Public CMS metadata uses the schema's nullable `keywords` and `meta_image`,
  rather than nonexistent `tags`/`meta_img`. Preserve public content and established
  sanitization. The VoguePay package currency expression now reads the configured
  currency directly; verification renders templates without payment calls.
- Representative receipt QR compilation was already fixed in selected source
  `218f05f15693a06f748b9d20b799cad5f8a75e81`; real receipt PDF tests pass.
- Retained representative web visit-note writes must save only the current store
  actor's own visit. The former integration no-op is fixed with a typed validated
  request; foreign visits retained the established `0` response until §31,
  which replaces both numeric answers with a redirect back and keeps foreign
  visits unchanged. No departure,
  tax, logout or role-grant policy changes accompany this fix.
- Retained `/temp/product-queries` read aliases preserve the previous
  `view_all_product_queries` gate and redirect to selected admin handlers.
  Encrypted legacy detail identifiers are accepted, invalid identifiers return
  404, and customer submission/seller consumers remain separate.
- Browser fixtures use an idempotent Arabic-language setup in dedicated
  `database/e2e.sqlite`; selectors reflect current Inertia language pages.
  Keep single-worker/no-retry/no-server-reuse behavior. Quote fixture seeder
  namespaces in shell commands; use graceful shutdown for Docker-backed servers.
  HTTPS and an installed Chromium executable can be supplied through environment
  overrides. Enforce CSP in browser tests; do not use report-only as success evidence.
- `CellTest` must not manually reserve `Z` beside Faker-generated unique row
  letters. Its four reproduced collision seeds are covered with generated parents;
  lazy factory parent creation remains unchanged.

255 retained/current Blade templates compile and lint as PHP. Syntax coverage
is distinct from representative runtime rendering, PDF rendering, and browser
workflow coverage. Public initial-response HTML was compared with an isolated
pre-pilot `cdf719789541079ce86c5dc1b605520aa1ab7c9f` worktree. Existing SEO gaps
remain recorded: generic homepage listing/search `og:url`, missing `/temp` in
CMS/policy `og:url`, and indexable login. Sampled pages have no canonical tags,
JSON-LD or itemscope structured data; do not describe absent features as migrated.

Retained-module issues are separate from integration regressions: populated
brand admin pages referenced absent `brands.order_level` (resolved by §14 without
schema change); the populated dashboard
product-translation eager load was fixed in §33; legacy API/seller resource
routes still include nonexistent handlers, and legacy uploader deletion
failure handling remains weaker than the selected admin controller. These need
separate scoped work; do not activate core catalogue/seller migrations or change
shared storage policy during this audit. Keep all recorded owner-decision gaps.
Final combined verification and production MySQL/role-grant review remain pending.


## 8. Combined verification attempt (2026-10-11, prompt 4)

The integration branch remains at `29e5e2527bf7f9920436e5970a4413c46325ed03`,
with preserved reconstructed-audit work and eleven additional frontend-test
contract fixes. No local commit, push or deployment. Full mounts now distinguish
page fields/forms from the integrated sidebar search/cache action; exact prop
assertions include Pagination `async:false` and AddResourceLink `inertia:true`.
No production permission or frontend behavior was relaxed to make tests pass.

Current sandbox denies the existing Docker socket with `connect: operation not
permitted`; host PHP/Composer are absent. User internet authorization did not
result in granted runtime access. PHP formatting/static checks, full backend,
Wayfinder generation, build, template compilation/render/PDF checks and isolated
MySQL remain blocked. All-store Playwright attempt exited at PHP web-server
startup; zero browser tests executed in this prompt. Historical focused results
and seven-workflow browser runs are not final combined verification.
Current full frontend run passes 2,050 tests in 227 files; TypeScript passes.
Exact lint/format/diff outcomes are in the ledger's prompt 4 section. These
frontend results do not satisfy the blocked backend/build/browser gate.

Fresh source recount: 255 Blade templates, including 253 shop/73 backend;
§5.1 exactly matches the 73 files. Literal layout `@extends` consumers are backend
admin 49, backend auth/error 3, frontend 49, frontend user panel 19, seller 29.
Counts describe source consumers, not executed endpoints; retain all protected
shells, includes, assets, PDFs, root template and Blade CSP. Source dispositions,
completed/partial/pending/decision-blocked scope in §§4–6 remain unchanged.

Brands/dashboard defects and legacy uploader/route concerns match baseline and
remain separately scoped inherited debt. Six additive permission migrations add
store definitions without role pivot writes/destructive rollback; deployed role
grants and MySQL locking/collation/signed-FK compatibility are still unverified.
Dedicated order-entry and blog/support permission-migration tests remain a
coverage gap. Preserve guards, narrow allowlists, denied-write invariants and
established order tax behavior when completing the blocked checks.

Baseline frontend lock differs in 14 patch package versions; installed current
versions satisfy inspected dependency ranges, with a coherent Vue-family upgrade.
This supports compatible substitution, not historical lock equivalence. Prior
initial-HTML comparison was reviewed but could not be recaptured; inherited SEO
gaps and absent canonical/JSON-LD/itemscope features remain as recorded in §7.
Exact commands, attempted/executed results, dependency differences and next checks
are recorded in the reconciliation ledger. Runtime recovery is required before
claiming combined verification satisfactory or creating the authorized local commit.


### 8.1 Runtime-access follow-up

The running Docker daemon/socket and correct task group membership were confirmed;
local AF_UNIX connect still returns `EPERM` under an active execution filter.
Specific socket/network permission request returned no grant. Do not chmod the
socket, restart the healthy daemon or bypass sandbox/proxy controls; restore
supported managed-executor socket/connection access. Host PHP 8.5 is absent;
the root Ubuntu-PPA installer is unsuitable unchanged for non-root Debian 13,
and missing build prerequisites plus unreachable configured proxy block local
runtime provisioning. Manifests/locks and all 31 changes are preserved.

Only documentation changed; completed frontend results were not rerun.
`git diff --check` passes. Outstanding runtime checks and deployed role grants
remain unverified, with exact diagnostics/fallback assessment in the ledger's
runtime-access follow-up. The local commit gate remains incomplete.


### Authorized local checkpoint

The user explicitly authorized a preservation-only checkpoint with message
`chore: checkpoint reconciliation pending backend verification`. It contains
exactly the 31 intended integration/audit/test/documentation files; scratch,
cache, credentials and generated artifacts are excluded. This authorization
supersedes the earlier decision to leave the blocked work uncommitted, without
changing verification or publication status. Backend/PHP/build/template/PDF,
browser and isolated MySQL checks remain outstanding; deployed grants remain
unverified. Frontend results remain as recorded. No push or deployment.
A verified Git bundle will preserve the integration branch's reachable history.


## 9. Resumed runtime verification (2026-10-11)

The separate worktree started and remains at preservation checkpoint
`924d7d9319cd1a69c41e0b191724a309f05ae86a`. Current results supersede earlier
runtime-blocked status only for the checks actually completed. Exact evidence
and next command are in [the reconciliation ledger](../store-backoffice-reconciliation-20261011.md#resumed-runtime-verification-2026-10-11).

- PHP 8.5.11 with GD WebP and supported Docker execution were recovered during
  the preceding resume; Composer platform requirements passed. Manifests and
  locks remain unchanged. Scratch/runtime extensions and generated data stay
  outside the intended source changes.
- Full backend: **4,961 tests passed / 23,810 assertions**. Subsequent affected
  SQLite order tests: **73 passed / 431 assertions**. Pint and PHPStan passed;
  fresh Wayfinder, production build and TypeScript with generated actions passed.
  The earlier full frontend 2,050-test/227-file run and full lint/format results
  remain valid and were not repeated. Latest changed browser-spec lint/format
  also passed.
- All **255** Blade templates compile and lint. Full backend includes sampled
  retained rendering and PDF evidence; syntax does not establish visual parity,
  every endpoint, or external delivery/payment behavior.
- New dedicated order-entry/blog-support migration tests close the §8 coverage
  gap: store-only definitions, no automatic existing-staff grants, idempotent
  replay and nondestructive rollback preserving permissions/store/web pivots.
  Deployed role grants remain unverified and are not inferred from definitions.
- Isolated MySQL 8.4.11 fresh migrations and **101 tests / 587 assertions** passed.
  JSON assertions compare semantic content; account-search fixtures use integer
  `AccSysID`, matching the schema. Preserve established order tax arithmetic.
  Engine metadata confirms InnoDB/`utf8mb4_unicode_ci` and eight signed product
  FK matches; a two-connection probe confirms held/released row locking,
  transactional rollback and case-insensitive collation. This is not production
  data/configuration or concurrent HTTP-load verification. The owned container
  was stopped afterward.
- Browser contract fixes preserve workflow/security assertions while using the
  canonical uploader, current Inertia visits, retained Blade trips, escaped
  search results, explicit total labels and accessible picker focus return.
  Simulated support errors preserve the HTTPS origin and use a final Inertia
  error response; failed-write redirect coverage is not claimed. RTL saves
  await responses/reload; the endpoint's observed 302 is the current expectation.
- Actual browser runs were **31 passed/8 failed**, then **11 passed/3 failed**,
  then **5 passed/2 failed**. The last failures were the incorrect 303 expectation
  and dependent RTL fixture state. The corrected 302 expectation has not run.
  Latest fresh all-store attempt executed **zero tests**: current managed shell
  denies loopback TCP with `EPERM`, and a network permission request returned no
  grant. Preserve HTTPS/enforced CSP, single worker, zero retries, isolated DB
  reset and no server reuse when supported access is restored. Do not count
  partial attempts as a full pass or bypass execution policy.

The session-owned HTTPS proxy was stopped, and no unrelated processes/healthy
Docker daemon were targeted. Local fixes, traces and generated artifacts remain
preserved; no staging/new commit because the browser gate is incomplete. Keep
all source dispositions and owner-decision gaps in §§4–8, inherited brands/
dashboard/legacy-route issues, and the excluded unavailable audit unchanged.
Production MySQL/data rehearsal, deployed grants, external services and absent
baseline captures remain unverified. No push, PR, merge or deployment.


### Authorized backup checkpoint — pending browser verification

The user's subsequent instruction authorizes a preservation-only local checkpoint
of the intended fixes and both verification records, and a public backup push to
new branch `codex/store-backoffice-reconciliation-pending-browser-backup-20261011-01`
in `arb-rajab/warehouse_management`. Label the commit
`chore: checkpoint reconciliation fixes pending browser verification`. Exclude
scratch/generated files and secrets. Reuse completed checks; do not describe
the incomplete browser gate or deployed-role/production checks as verified.
This narrowly supersedes the previous no-commit/no-push status for backup only;
verify the remote HEAD afterward. No push to `develop` or the existing integration
branch, PR, merge or deployment. Exact intended scope/limitations are in the
ledger's matching authorization section; actual publication outcome is reported
after execution.


## 10. Final all-store browser verification (2026-10-12)

Resumed branch
`codex/store-backoffice-reconciliation-pending-browser-backup-20261011-01`
in `/workspace/warehouse_management`, confirming starting HEAD
`a0c880ea376e716ee633c5c5191c45a0a521780a`. Both records and repository guidance
were read. Approved IPv4/IPv6 loopback bind/connect/transfer probes passed before
servers started. The default sandbox's socket denial was not treated as evidence
about approved execution.

- **Final complete browser run: 39/39 tests passed in 22 files**, exit 0, using
  installed Chromium 151, local HTTPS, enforced CSP, one worker, zero retries and
  no server reuse. Every invocation recreated only the four dedicated e2e SQLite
  databases; mail used the array driver.
- The latest language correction is now browser-verified: RTL saves return 302,
  reloads confirm both persisted values, and dependent Arabic order layout passes.
- Initial complete runs passed 37/39 and 38/39. Traces show failed local navigation
  and application stylesheet transport through the external Python HTTPS helper.
  An unchanged fresh driver/support run passed 3/3. A streaming Node helper fixed
  the transport problem; the five affected driver/marketing/support checks and
  the subsequent full 39-test run passed. No application/test source, assertion
  or retry configuration changed. Failed-attempt evidence is retained externally.
- Native PHP 8.5.11 gained GD WebP through an external runtime extension build;
  WebP encoding and Composer platform checks passed. Fresh Wayfinder/production
  build and TypeScript passed. Manifests/locks remain unchanged.
- Reuse the earlier recorded backend 4,961-test, frontend 2,050-test, isolated
  MySQL 101-test/engine-probe, PHP/template/render/PDF and lint/format evidence
  within its stated limits; those checks were not rerun because application/test
  source is unchanged. Current logs/helpers are external under
  `/workspace/work/store-browser-final-20261012/`.
- Playwright upstreams and both owned HTTPS helpers are stopped. Ports 8010/9443
  have no listeners; no helper container remains running. The managed Docker
  daemon was retained. Generated uploads are preserved externally. Both records'
  file links and `git diff --check` pass; only these records enter the authorized
  local verification commit.

This closes the pending browser gate while preserving all source dispositions,
inherited retained-module issues, owner decisions and excluded unavailable audit
in §§4–9. Production data/configuration, deployed grants, real external delivery/
payment, complete visual parity and unavailable baseline captures remain unverified.
Browser workflows do not establish globally clean console/network output.
No push, PR, merge or deployment. Exact per-run logs and commands are in
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md#final-all-store-browser-verification-2026-10-12).


## 11. Carrier administration follow-up (2026-10-12)

Authorized carrier-only implementation on `codex/carrier-admin-inertia`, from
merged develop `018dd1802c0b2fc99ffbf0e097594fe0f0f3dfef`. Before editing,
actual root controller render calls and all three Blade screens were confirmed.
The former missing `carriers.show` route had no consumer; its permission-ratchet
exception and duplicate resource registrations were removed. Seven implemented
admin actions retain their route names and `manage_carriers` gate. Public/API
registrations and the separate `Api/V2/CarrierController` remain unchanged.

- The selected `Store/Admin/CarrierController`, dedicated filter/store/update/
  status requests and `CarrierResource` expose narrow paginated records and
  explicit edit ranges/zone-price maps. Numeric zone keys serialize as an object
  so Laravel cannot reindex prices against the wrong zone.
- Create/update/delete transact the carrier, ranges and prices together. Updates
  retain the established replace-all behavior, including removed ranges/zones,
  billing changes and transitions to/from free shipping. Existing contiguous
  ranges, inclusive lower/exclusive upper boundaries and decimal schema widths
  are validated; shared shipping arithmetic is unchanged.
- Canonical image-reference validation controls new logos. An unchanged existing
  logo can round-trip without granting access to another owner's replacement;
  previews respect library permission/ownership. No roles or grants change.
- Carrier/CarrierRange/CarrierRangePrice were typed and removed from both analysis
  exclusion lists. `CarrierRangePrice::carrier_ranges` explicitly uses the actual
  `carrier_range_id` column; dedicated relationship tests include unrelated data.
  Separate API and other legacy model exclusions remain.
- Vue list/create/edit use existing StoreAdminLayout, ResourceFormPage, table,
  pagination, status button and StoreUploadField. English/Arabic copy and exact
  decimal client validation require no new dependencies. Menu/shipping links are
  Inertia; their tests match actual destinations.
- Coexistence and legacy-uploader browser fixtures now use retained general
  settings; uploader interactions are scoped to `system_logo_white`. Carrier
  browser coverage exercises paid tariff editing, canonical logo selection,
  native delete confirms, Arabic/RTL and dark contrast through enforced HTTPS.

Current inventory and consumer verification:

- `resources/views/shop/backend`: **70** `.blade.php` files; setup configurations
  now contain only general settings and Google-map settings. Other directory
  counts and retained-module/owner-decision dispositions are unchanged.
- Direct literal Blade inheritance/include scan: **46** templates extend
  `shop.backend.layouts.app`; **3** extend `shop.backend.layouts.layout`
  (store error 503, deliveryboy login, seller login). The primary shell still
  includes admin nav and sidenav once each. The blank layout has no direct
  literal inheritance/include caller and is retained by the shell contract.
- Invoice and three export/download templates still have controller consumers;
  `/temp/reviews`, seller SKU partials, public/seller/auth/email/uploader assets,
  coexistence middleware and Blade CSP remain. No broad shell cleanup occurred.
- Actual route inventory: **1,053** `/temp` registrations, **441** `/temp/admin`
  registrations, seven carrier-admin actions; removed only the broken show and
  duplicate resource registration. Counts are registrations, not module totals.

Verification results are recorded in the
[carrier ledger entry](../store-backoffice-reconciliation-20261011.md#carrier-administration-migration-2026-10-12).
Manifests/locks, tariff helper/API implementation, role grants and unrelated
brands/dashboard defects are unchanged. Commit authorization is local only;
no push, PR, merge or deployment is authorized by this follow-up.


## 12. Customer package administration follow-up (2026-10-12)

Authorized local-only migration on `codex/customer-package-admin`, based on
latest fetched develop `71de5cad28d33561ed832acc80d02cc822191e87` (PR #378).
The package list/create/edit were confirmed Blade before migration. Neither the
migration guide nor the reconciliation prerequisite table identifies a specific
package dependency or business-policy decision. Classified-product activation
and seller feature-use decisions remain separate and unresolved; no feature
flag, role grant or dependency was changed for this migration.

- Six implemented route names remain, with bound packages and DELETE for
  destruction. Both PUT and PATCH still update. The empty, unused show action
  and duplicate resource registrations are removed after a consumer search.
- The established `view_classified_packages`, `edit_classified_package` and
  `delete_classified_package` gates remain. Create/store were already edit-gated;
  the new Add control matches that actual gate instead of the old Blade-only
  `add_classified_package` affordance. No backend privilege is expanded.
- `Store/Admin/CustomerPackageController`, dedicated requests and a narrow
  resource use the established paginated Inertia pattern. The three pages reuse
  StoreAdminLayout, ResourceFormPage, DataTable, Pagination, language tabs,
  canonical StoreUploadField and Wayfinder. All new UI copy is English/Arabic.
- Server validation enforces the translation name width (50), nonnegative
  decimal(20,2) prices, nonnegative signed-integer upload limits, selected
  languages and canonical image-reference ownership/library permissions.
  Unchanged valid foreign logos round-trip, with previews still policy-gated.
- Create/update transact the base package and translation together; update and
  delete lock the package. Updating a nondefault translation leaves the base
  name intact while updating shared price/benefits, as before. Delete removes
  translations and package atomically, preserving existing payment history,
  customer assignments and remaining uploads, including legacy dangling IDs.
- The root CustomerPackageController remains for `purchase_package` and
  `purchase_payment_done`, with those bodies unchanged. Public Blade packages,
  customer products/dashboard, VoguePay, shared purchase helper, payment
  adapters, GatewayPaymentService, seller consumers and V2 API contracts remain.
  CustomerPackage/CustomerPackageTranslation are typed and removed from both
  analysis exclusion lists; the still-legacy root/API/payment model exclusions
  remain. No casts alter legacy amount/benefit values.
- Current backend inventory: **67 Blade files**, **43** literal consumers of
  `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
  direct literal consumers of the blank layout. Shells, their nav includes,
  three downloads, invoice, seller SKU partials, uploader assets and CSP remain.
  Actual route registrations: **1,050** under `/temp`, **438** under
  `/temp/admin`, six customer-package admin actions. Counts are registrations.

Verification and limitations are recorded in the
[customer package ledger entry](../store-backoffice-reconciliation-20261011.md#customer-package-administration-migration-2026-10-12).


## 13. Category administration follow-up (2026-10-12)

Category administration now uses `Store/Admin/Product/Categories` from fetched
`develop` at `03734ff6c3fe97435b08cf8d4c0644c792a09663` on
`codex/category-admin-inertia`. The exact base SHA is also recorded in the
reconciliation ledger. PR #379's 14 head checks, merge SAST and existing deploy
run `38245990290` passed; deployment `7013472103` reports success. That workflow
reused an existing successful tests run and skipped `Test the merged commit`.
All GitHub work was read-only; no deployment was requested.

No category-specific dependency, feature-use or owner prerequisite appears in
the reconciliation decision table. The inherited `CategoryUtility` level writers
adjust by one and return after the first child branch, including on a move across
multiple levels. This migration preserves those bodies and pins their existing
behavior; correcting sibling/subtree level maintenance requires separate scoped
authorization. Deletion still moves immediate children to the deleted category's
parent and clears only that category's product references. It does not cascade
children, product rows, classified rows or other legacy references.

Completed: three Inertia screens, seven implemented route names and the existing
view/add/edit/delete permission vocabulary; StoreAdminLayout, shared form/table/
pagination/actions, translation tabs, status button, canonical upload picker and
Wayfinder. Updates support PUT/PATCH; deletes use DELETE; the empty unused show
and duplicate registrations are removed. Search remains on the base category name
and ordering remains descending priority. Dedicated requests validate schema
bounds, languages, parent references/cycles, attributes and image ownership.
Category/translation/pivot/product and hierarchy writes use transactions, with
ordered hierarchy locks and validation repeated under those locks. Existing
foreign image IDs can round-trip without authorizing replacement or previews.
Shared `ProvidesStoreUploadPreviews` centralizes the unchanged preview boundary
for category, carrier and customer-package forms.

Translated names, type, commission setting/rate, ordering, banner/icon/cover,
filtering attributes, slug and SEO title/description remain. No model casts,
public/seller/mobile contract, role grant, feature flag or upload normalization
policy changed. CategoryTranslation is typed and removed from both analysis
exclusions; Category was already analyzed. CategoryUtility and API exclusions
remain. Only the three exclusively consumed admin Blade screens and the replaced
root controller are removed. Shared `shop.categories.child_category`, seller/SKU
partials, PDFs, shells, assets, legacy uploader and CSP handling remain.

Current inventory: **64** backend Blade files; **40** direct literal consumers of
the primary admin shell, **3** of its secondary layout, **0** of its blank shell;
**1,047** `/temp` and **435** `/temp/admin` registrations. No category-admin Blade
screens remain; the protected category PDF remains. Exact filenames are in §5.1.

Verification results and runtime limits are recorded in the category section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No push, PR, merge, deployment or production-data mutation is authorized.


## 14. Brand administration follow-up (2026-10-12)

Brand administration now uses `Store/Admin/Product/Brands`, from fetched
`develop` at `f39b065d8b376d324a0ec965c333070ffe2cbf6f` (PR #385 merged) on
`ccr-deb0d484-n8nxxo`. Both brand screens were Blade before migration. The
reconciliation decision table has no brand-specific dependency, feature-use or
owner hold; the form has plain text, textarea and image fields only.

**`brands.order_level` evidence.** No schema source defines it: this repo's
`create_brands_table`/legacy-columns migrations (`brands` gains only `serial`),
the legacy `shop.sql` 6.5.0 dump, all 70 vendor `sqlupdates/v*.sql` (only
`v42.sql` adds `order_level`, to `categories`) and all 107 legacy migrations at
`arb-rajab/albaraka-holland@75531338fa8190891c6e334620e461f6c79fe5be`, read-only.
The legacy list read it (500 under local/testing strict attributes) and
`BrandController::store` wrote it, so every brand creation failed with an unknown
column wherever the schema matches these sources. No other reader exists:
`brands.serial` is the Otajer `Mat_Class` import key, not an ordering field; the
admin list orders by name, and storefront/API brand reads apply no ordering. The
converted screens neither read nor write `order_level`; no schema, replacement
field or ordering policy was added. Production's schema was not inspected: if
production has the column, adding it here is a separate owner-approved change.

Completed:

- Five implemented route names with the existing `view_all_brands`, `add_brand`,
  `edit_brand` and `delete_brand` gates; bound `/brands/edit/{brand}`, PUT/PATCH
  `/brands/{brand}` and DELETE `/brands/destroy/{brand}` keep their URIs. The empty
  `create`/`show` actions and duplicate resource registrations are removed. Legacy
  tests that used brand routes as Blade or POST-delete examples now use the still-
  Blade digital-product routes, or the DELETE brand route.
- The list keeps base-name search and name ordering, pages 20 with the per-page
  selector and keeps inline add, gated by `add_brand`. Edit has translation tabs;
  the base name changes only for the default language. Create slugs keep
  space-to-dash replacement or the generated `<name>-<5 random>` shape; update
  slugs are lowercased or regenerated from the posted name, as before. Update
  returns to the edit screen in the same language. Logos use the canonical
  StoreUploadField and image-reference policy; an unchanged foreign logo round-
  trips without preview access. Dedicated requests validate schema widths.
- `BrandDiscountResource` became the shared `BrandResource`, and its batched logo
  lookup moved to `ProvidesStoreBrandLogos`; the brand-discount payload is
  unchanged. The repeated retained-image rule in the carrier, customer-package
  and category requests moved to `ValidatesStoreUploadReference::retainableImageUploadRule()`.
- Brand and BrandTranslation are typed and leave both analysis exclusions with
  the deleted root controller. ImportBrands and API exclusions remain.

**Retained deletion behavior (owner decision):** deleting a brand still deletes
every `products` row carrying it, then its translations and the brand, now in
one transaction. This conflicts with shared-database.md's single-writer rule for
WMS-owned `products`. A product referenced by a pallet, store product barcode or
variation makes the delete fail atomically (those foreign keys restrict) with a
server error; otherwise it cascades WMS product settings/barcodes and nulls
cell-log/verification product references.
Changing this (e.g. clearing `brand_id`, or refusing with a validation error)
needs explicit authorization; tests pin the current behavior.

Retained: public `brands.all` Blade, storefront/seller/customer brand selects,
V2/seller brand APIs, `top`/`serial` writers, `shop.backend.downloads.brand`,
shells, legacy uploader and Blade CSP. No casts, cache invalidation, permission
definitions/grants, flags, manifests/locks or upload normalization policy changed.

Current inventory: **62** backend Blade files; **38** direct literal consumers of
the primary admin shell, **3** of its secondary layout, **0** of its blank shell;
**1,043** `/temp` and **431** `/temp/admin` registrations. No brand-admin Blade
screens remain. Verification is in the brand section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No push, PR, merge, deployment or production-data mutation is authorized.


## 15. Digital product list follow-up (2026-10-12)

The digital product list now uses `Store/Admin/Product/DigitalProducts/Index`,
on `claude/digital-products-list-migration-6tpmmr` (PR #387), branched from
`develop` at `f39b065d8b376d324a0ec965c333070ffe2cbf6f` (PR #385) and then
merged with `develop` at `6de7128` once PR #386 (brands, §14) landed.
The create and edit forms stay Blade: they use the Blade rich-text editor and
the Tiptap dependency has no verified approval (§5).

**Routes and handlers.** Ten registrations replace the resource plus three
overrides. Every route name used by a consumer is kept; the empty, unused
`digitalproducts.show` and the nameless resource `edit`/`destroy` duplicates
are gone.

- `Store\Admin\DigitalProductController`: `digitalproducts.index`,
  `digitalproducts.destroy` (now `DELETE /digitalproducts/destroy/{product}`),
  `digitalproducts.download` (still `GET /digitalproducts/download/{id}` with
  the encrypted id) and three new typed switches, `digitalproducts.published`,
  `.featured` and `.todays_deal` (`POST /digitalproducts/{product}/…`).
- Root `DigitalProductController` keeps only `create`, `store`, `edit` and
  `update`, bodies unchanged, so it stays in both analysis exclusion lists.
  `digitalproducts.update` keeps PUT/PATCH on `/digitalproducts/{id}`; `store`
  still redirects to the (now Inertia) list and `update` still goes back.
- Gates are unchanged per action: `show_digital_products`,
  `add_digital_product`, `edit_digital_product`, `delete_digital_product`,
  `download_digital_product`. The Blade list showed edit, delete and download
  under `add_digital_product`; the Vue list gates each on its route's own
  permission, as §2.2 does for the sidebar.

**List.** Ordering (`created_at desc`, plus `id` as a tie-breaker), base-name
search and the `digital = 1` filter are kept; page size moves from 10 to the
pilot's 20 with the selector. The narrow `DigitalProductResource` sends the
translated name, author, thumbnail through `uploaded_asset()` (placeholder
included, as the Blade did per row), the `number_format` price, the three
flags, the public `product` URL by slug (null without a slug) and the
encrypted download id. Create, edit and download are plain full-load `<a>`
links built from Wayfinder `.url()`; the name links to the storefront page in
a new tab.

**Switches.** The shared `products.published`/`.featured`/`.todays_deal`
endpoints (root `ProductController`, gated by `product_edit`) answer `1`/`0`
for jQuery, take any product id and write stock before a possible refusal, so
an Inertia visit cannot use them. They stay unchanged for the Blade product
lists. The new endpoints keep the `product_edit` gate and the writes:
unpublish also unfeatures and zeroes every stock row, publish sets stock to
1,000, published/featured run `view:clear` and `cache:clear`, today's deal
forgets `todays_deal_products`. Each locks the row in a transaction, accepts
only digital products (404 otherwise) and redirects preserving the query. The
seller-subscription refusal is kept, but now refuses (422 on `status`) before
any write instead of after the stock update.

**Deletion: owner decision pending, behaviour unchanged.** Deleting still
removes the product's translations and stock rows and hard-deletes the
`products` row, now in one transaction with the row locked, and only for a
digital product. This is the same single-writer conflict as brand deletion
(§14; `products` is WMS-owned, shared-database.md). A pallet referencing
the product (`pallets.product_id` restrict) or a store barcode/variation
(no-action FKs) makes the delete fail atomically with a server error; WMS
settings/barcodes cascade and cell-log/verification references null. Tests pin
both outcomes. Soft delete, a refusal with a validation error, or any other
policy needs owner approval. The published switch likewise remains a second
writer of `products.published`, which `products:sync` overwrites for feed
products; that predates this change.

**Download.** Gate `download_digital_product`; the encrypted-id contract is
kept, since external bookmarks cannot be ruled out. Two narrow defect fixes:
an undecryptable or non-integer id and a product whose upload is missing or
whose local file is absent are 404s, not a 500 or an empty 200. The S3 branch
is unchanged (a missing S3 object still fails in the driver).
`seller.digitalproducts.download`, the mobile `purchased-products/download`
and the storefront `digital-products.download` are separate controllers and
untouched.

**Consumers traced.** Only the root `index` rendered the removed list view;
`create`/`edit` views, `shop.categories.child_category`, PDFs, shells, the
legacy uploader and `HandleStoreBladePages`/`store.blade_csp` remain. The Blade
sidebar's `areActiveRoutes(['digitalproducts.index', …])` names still resolve.
`ProductController::admin_product_edit`/`seller_product_edit` still redirect a
digital product to `admin/digitalproducts/{id}/edit` and
`digitalproducts/{id}/edit` without `/temp`, which already missed the store
(inherited debt, unchanged).

**Tests retargeted, none weakened.** PR #386 moved `HandleStoreBladePagesTest`,
`ReconstructedMigrationAuditTest` and `RouteServiceProviderTest` (plus the
`RouteServiceProvider` docblock) from brand routes to the then-Blade digital
product routes. With digital products converted, they now use the retained
general settings page (`/temp/admin/general-setting`) and the `sellers`
resource/`sellers.destroy` override as the duplicate-name example.
`LegacyStoreStateChangingRoutesTest` already accepts DELETE for
`digitalproducts.destroy`.

Current inventory: **61** backend Blade files (62 after §14), **37** direct
literal consumers of `shop.backend.layouts.app`, **3** of
`shop.backend.layouts.layout`, **0** of the blank shell; **1,043** `/temp` and
**431** `/temp/admin` registrations, unchanged by this module because ten
registrations replace ten. Verification is recorded in the digital-product
section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No push, PR, merge, deployment or production-data mutation is authorized.


## 16. In-house and all-products lists follow-up (2026-10-12)

The in-house (`products.admin`) and all-products (`products.all`) lists now use
one Inertia page, `Store/Admin/Product/Products/Index`, with a `listType` of
`admin` or `all`. Work is on `claude/migrate-admin-product-lists-2q0qog`,
branched from `develop` at `2aafc21` (the PR #387 merge), and merged as
PR #388 at `046be2005c3019584483a44eecefddd39116a831`. The owner authorized
this session's PR, its merge into `develop` and `[skip ci]` on both. The PR and
its merge commit carry `[skip ci]`, so no PR CI, no PR or merge SAST and no
`develop` deploy ran for them.

**Still Blade.** The seller list (`products.seller`) stays Blade with seller
administration (§4.1), so the shared `product/products/index.blade.php` stays
and its seller branch is unchanged. Product create/edit stays Blade because it
needs the rich-text editor (Tiptap approval pending, §5). The Vue page links to
it with plain full-load `<a>` links built from Wayfinder `.url()`.

**Routes and gates.** `products.admin` and `products.all` keep their URIs and
their `show_in_house_products`/`show_all_products` gates on
`Store\Admin\ProductController`. Six new row actions each send `list`, the list
they came from, as a query parameter. Each action reaches only a product that
list shows, which is a 404 otherwise, and returns to that list with its filters.

- `products.list.published`, `.featured` and `.for_sale`, all gated by
  `product_edit`, at `POST /products/{product}/…`.
- `products.list.duplicate`, gated by `product_duplicate`, at
  `POST /products/{product}/duplicate`.
- `products.list.destroy`, gated by `product_delete`, at
  `DELETE /products/{product}`.
- `products.list.bulk_destroy`, gated by `product_delete`, at
  `POST /products/bulk-delete`.

The in-house list shows only `added_by = admin`, non-auction, non-wholesale,
non-digital products. The all-products list shows every owner and digital
product, but never an auction or wholesale product.

The root `ProductController` keeps everything else for the Blade seller list
and the editor forms, unchanged: the jQuery switches, approval, duplicate,
destroy, `bulk-product-delete`, `products.fetch`, search and the SKU/form
helpers. It loses only `admin_products`, `all_products` and their two
middleware entries, and stays in both analysis exclusion lists.

**Lists.** The legacy filters are kept, with their legacy query names, so the
seller Blade list's preset links still work.

- **In-house state filters.** `active`, `for_sale` and `featured` always match
  exactly, ticked or not. With nothing ticked, the list shows only products that
  are unpublished, off sale and unfeatured, as before; the page explains this.
  The in-house list has no deleted filter, so its checkbox (a no-op) is gone.
- **All-products presets.** `allProducts` drops every state filter. Otherwise
  the first of `allDeleted`, `allActive`, `allForSale` or `allFeatured` applies
  on its own. Otherwise any ticked switch matches all four switches, `deleted`
  included, exactly. Otherwise retired (`deleted_from_api`) products are hidden.
  The all-products list also keeps `user_id`.
- **Search.** The in-house list matches one phrase. The all-products list
  matches every word, and also matches `mat_id`.
- **Category and sort.** Both lists filter by category. The legacy unvalidated
  `type` (`column,direction`) sort is now limited to the six orders the Blade
  select offered (rating, number of sales, base price); any other value is a 422.
- **Default order.** In-house stays `created_at desc`, with an `id` tie-breaker.
  All-products had no order at all, so it now orders explicitly by `id`.
- **Page size.** Both move from 15 to 20 per page with the per-page selector,
  the deliberate pilot change.
- **Query booleans.** GET booleans are normalized, so legacy `on` values still
  work but a literal `0` now means off.

Two narrow search defect fixes:

- The legacy in-house search `OR` was ungrouped, so an Arabic-name or barcode
  match leaked past the list's own conditions; for example, a seller product
  matching the Arabic name appeared in the in-house list.
- The legacy search went through `Product::barcodes()`. Since the model merge,
  that reads WMS `wms_product_barcodes`, while the Otajer import stores each
  `Mat_ID` in the store's `product_barcodes`. Search now matches both tables,
  so nothing it found before is lost.

**Rows and actions.**

- **Row payload.** `ProductListResource` sends:
  - the synced `name` and `ar_name`, and the thumbnail through
    `uploaded_asset()`;
  - `mat_id` and the legacy unit string;
  - the number of sales, and the base price and profit through
    `single_price()`;
  - the first stock row's quantity, or each variant's, with the legacy "low"
    comparison;
  - the translated category name, the four flags, and the storefront URL by
    slug (null without a slug).
- **Switches.** The digital-product and product lists now share one
  published/featured write path, `Concerns\UpdatesStoreProductFlags`, with the
  rules of §15:
  - unpublishing also unfeatures the product and zeroes its stock, and
    publishing sets stock to 1,000;
  - the seller-subscription refusal happens before any write;
  - published and featured run `view:clear` and `cache:clear`.

  The new for-sale switch writes what `updateSaleStatus` wrote. Today's deal
  (commented out of the Blade list) and seller approval (seller list only)
  stay on the Blade seller list. The refusal message is now the shared
  `messages.store_product_package_limit`.
- **Fetch button.** It keeps the legacy JSON `products.fetch` endpoint, called
  through `useHttp`, and is shown under `add_new_product`, as the Blade list
  showed it.
  - The endpoint stays ungated, as `LegacyAdminPermissionsTest` lists it.
  - It queues the `cron_jobs` row that `check:commands` turns into
    `import:all-products-v2`. That command is a second writer of `products`
    beside `products:sync`, an owner decision left unchanged.
- **Duplicate (second writer of `products`, unchanged).** It still copies the
  `products` row with a random slug suffix, plus its stock and tax rows, but
  not its translations, variations or barcodes. It now runs in a transaction,
  only on a product the list shows. Creating `products` rows here conflicts with
  shared-database.md's single-writer rule; changing it needs owner approval.

**Deletion: owner decision pending, behaviour unchanged.** Single and bulk
deletion still remove a product's translations, stock, taxes, variations and
store barcodes, hard-delete the `products` row and soft-delete its cart lines.
They now run in one transaction with the rows locked. Bulk deletion checks every
member against the list, under lock, before deleting any, and answers 422
otherwise.

Pinned foreign-key outcomes:

- a pallet still referencing a product fails the whole request atomically
  (restrict);
- store barcodes and variations do not block, because the handler deletes them
  first, unlike brand (§14) and digital-product (§15) deletion;
- WMS settings and barcodes cascade;
- cell-log and verification-report references become null.

Caches clear once per request instead of once per product.

**Consumers traced.**

- **Unchanged handlers and links.** Seller panel products
  (`Seller\ProductController`), V2 product APIs, the storefront `product` route
  by slug, `products:sync` and the root `store` redirect to `products.admin`
  are untouched. That redirect now lands on the Inertia list in a full load.
- **Inherited debt.** `admin_product_edit`/`seller_product_edit` still redirect
  digital products to URLs without `/temp`.
- **Route names.** No duplicate route names arise. `admin_sidenav`'s
  `areActiveRoutes` names all resolve.
- **Partials.** `choice_options_script` is used by the create/edit views.
  `sku_combinations*` are rendered by the root and seller controllers' SKU
  helpers. All of them stay.

The sidebar's in-house and all-products entries are Inertia visits. The seller
products entry is a full load.

**Tests adapted in place, none weakened.**

- `LegacyControllerMiddlewareConversionTest` counts five root middleware
  entries instead of seven.
- `LegacyStoreStateChangingRoutesTest` adds `products.list.destroy` and
  `products.list.duplicate`.
- The digital-product refusal test uses the renamed message key.

`HandleStoreBladePagesTest`, `ReconstructedMigrationAuditTest` and
`RouteServiceProviderTest` keep their general-settings/`sellers` examples.

Current inventory: **61** backend Blade files (no file removed), **37** direct
literal consumers of `shop.backend.layouts.app`, **3** of
`shop.backend.layouts.layout` and **0** of the blank shell. Registrations are
**1,049** under `/temp` and **437** under `/temp/admin`: six new action routes
on 1,043/431. Verification is recorded in the product-list section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 17. Setup configuration settings follow-up (2026-10-12)

General settings now use `Store/Admin/Setup/GeneralSettings/Index`. The Google
Map settings screen stayed Blade in this historical step; §32 lifts that hold. Work is on `ccr-b39fe4ad-n2p9xs`, branched from
`develop` at `e3eb0f5` (the PR #390 merge, after PR #389 at `a14c659`). The
owner authorized this session's PR (#391), its merge into `develop` and
`[skip ci]` on both. Every commit, the PR and its merge commit carry `[skip ci]`, so no PR CI,
no PR or merge SAST and no `develop` deploy ran for them.

**Google Map settings: historical hold, lifted in §32.** The screen's enabled state loads a
map script. `google_map.blade.php:105-107` includes
`shop.frontend.partials.google_map` whenever `google_map == 1`, and that partial
loads `https://maps.googleapis.com/maps/api/js?…&libraries=places` (`:117`) and
builds a `google.maps.Map` (`:17`). The default-location inputs are `readonly`
(`:81`, `:89`): only the map fills them. Map activation is an owner decision
(§5), so the GET stays on root `BusinessSettingsController::google_map`, the
view stays, and its sidebar entry stays a full load. The typed
`google-map.update` writer is unchanged. `GoogleMapControllerTest` pins the
script on the enabled screen and its absence on the disabled one.

**General settings.**

- **Routes and gate.** `general_setting.index` (GET `/general-setting`) and the
  new `general_setting.update` (POST `/general-setting`) are on
  `Store\Admin\GeneralSettingsController`, gated `general_settings`. Root
  `general_setting` and its middleware entry are gone. The root controller
  keeps its other actions and stays in both analysis exclusion lists.
- **Typed writer.** `UpdateGeneralSettingsRequest` takes
  `settings[site_name|timezone|system_logo_white|system_logo_black|admin_login_background]`
  and nothing else. Foreign, reserved or environment keys, top-level or nested,
  and the legacy `types[]` shape are 422s before any write. The site name is
  nullable, at most 255 characters. Each logo is an image upload id through
  `retainableImageUploadRule()`: own images, or library images with
  `view_uploads`; an unchanged foreign logo round-trips without a preview.
- **Storage, unchanged.** `UpdatesStoreSettings` writes the four keys as the
  legacy writer did: the first row of each type, ids as strings, null when
  cleared. Reads stay on `get_setting()`.
- **Timezone, unchanged.** It comes from `config('app.timezone')`, which
  `config/app.php:146` hardcodes as `'UTC'`. The legacy writer sent it only to
  the refusing `overWriteEnvFile('APP_TIMEZONE')` and stored nothing. The page
  lists `timezones()` (PHP's identifiers plus the configured value) with the
  configured one selected. The writer requires a listed value, and refuses any
  value other than the configured one with
  `messages.store_timezone_environment_managed`, so the draft stays. It writes
  no row and never calls the `.env` writer. Every legacy save used to log and
  flash that refusal; the typed save does neither.
- **Generic writer.** `business_settings.update` now also reserves
  `UpdateGeneralSettingsRequest::KEYS` (§5). Its gate, its body and the
  footer/appearance forms that post to it are unchanged. Its `site_name` and
  `timezone` branches are now unreachable; `overWriteEnvFile()` stays, pinned by
  `LegacyEnvFileProtectionTest`.
- **Page.** `StoreAdminLayout` + `ResourceFormPage`, `FormField`, three
  `StoreUploadField`s, the timezone select, Wayfinder and 9 `store.generalSettings.*`
  keys in English and Arabic. The sidebar entry is an Inertia visit.

**Consumers traced, all unchanged.**

- `system_logo_white`: the Blade admin sidenav (`inc/admin_sidenav.blade.php:5-7`).
- `system_logo_black`: the seller and delivery-boy login pages
  (`shop/frontend/seller_login.blade.php:12-13`, `deliveryboy_login.blade.php:12-13`).
- `admin_login_background`: no reader. The store login reads `login_page_image`.
- `site_name`: the Blade admin and seller shell footers and sidenav alts, the
  admin invoice PDF (`invoices/invoice.blade.php:88`), the invoice email
  (`shop/emails/invoice.blade.php:80`) and the V2 `business-settings`
  `PUBLIC_KEYS`. The V2 `general-settings` endpoint reads its own table.
- Storefront header/footer logos and email headers read `header_logo` and
  `footer_logo`, which this form never wrote.
- `admin_sidenav` links by `route('general_setting.index')`, with no
  `areActiveRoutes` entry; the name is kept.

**Tests retargeted, none weakened.** The footer form (`website.footer`) stays
Blade for its editor, extends the admin shell and has a single-image
`aizuploader` (`footer_logo`).

- `HandleStoreBladePagesTest` and `ReconstructedMigrationAuditTest` use
  `/temp/admin/website/footer` as their Blade page.
- `SettingsReconciliationTest` and `ReferenceSettingsIsolationTest` use the
  appearance form's `website_name` as the permitted key, under
  `website_appearance`. The first also covers the general-settings keys and the
  four gate permissions still saving the footer and appearance forms.
- `store-staff-coexistence.spec.ts` follows Website setup → Footer.
  `store-legacy-uploader.spec.ts` scopes its upload, select and remove to
  `footer_logo`. The legacy uploader keeps real browser coverage.
- `LegacyControllerMiddlewareConversionTest` pins the root controller's five
  remaining middleware entries. `RouteServiceProviderTest` used only `sellers`
  and needed no change.

Current inventory: **60** backend Blade files, **36** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are **1,050** under `/temp` and **438** under
`/temp/admin`: one new POST on 1,049/437. Verification is recorded in the
general-settings section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 18. Shared rich-text editor and blog post forms (2026-10-13)

Blog post create/edit now use `Store/Admin/Blog/Posts/Create` and `Edit` with
the shared Tiptap editor, `components/StoreRichTextField.vue`. Work is on
`ccr-f394535e-90xr6w`, branched from `develop` at `28643a4` (the PR #391
merge). The owner approved Tiptap as the shared back-office editor and, for this
session only, the dependency, this PR (#392), its merge into `develop` and
`[skip ci]` on both. Every commit, the PR and its merge commit carry `[skip ci]`, so no PR
CI, no dependency-audit workflow, no PR or merge SAST and no `develop` deploy
ran for them; the local checks in the ledger are the only verification.

**Dependencies (exact).** `@tiptap/core`, `@tiptap/pm`, `@tiptap/starter-kit`,
`@tiptap/vue-3`, `@tiptap/extension-image`, `@tiptap/extension-table`,
`@tiptap/extension-text-align` and `@tiptap/extension-text-style` at
**3.31.3**, plus `@floating-ui/dom` **1.8.0**.

- The recorded 3.31.4 was published on 2026-09-30, 13 days before this work,
  so it fails the two-week rule; 3.31.3 (2026-09-04) is the newest patch of
  the same major that passes. `@floating-ui/dom` 1.8.0 dates from 2026-07-11.
- `@tiptap/pm` is a required exact-version peer of `core`, `vue-3` and
  `extension-table`, and the editor imports its `EditorView` type.
  `@floating-ui/dom` is a non-optional peer of `@tiptap/vue-3`.
- `@tiptap/vue-3`'s optional bubble/floating menus resolved to 3.31.4 (a peer
  mismatch with core 3.31.3, and under two weeks old), so the lockfile was
  resolved with `npm install --save-exact --before=2026-09-29T00:00:00Z`. Every
  one of the 52 added lock entries is at least 18 days old; no existing entry
  changed. npm did not hit the `edgesOut` bug, so no `--legacy-peer-deps`
  regeneration was needed. No Composer change.

**`StoreRichTextField` contract (use it for every converted rich-text field).**

- Props `id`, `name`, `label`, `error`, `required`; `v-model` holds the HTML.
  The HTML posts under `name` through a hidden input. Stored HTML posts back
  byte for byte until the content is edited; a blank document posts `''`; an
  empty table or image is kept.
- `required` adds an unnamed, `aria-hidden` validation proxy. On `invalid` it
  shows `store.editor.required` and focuses the content.
- The content is `role="textbox"` with `aria-multiline`, labelled by the field
  label and described by its error (`aria-invalid` when one shows). The
  toolbar follows the WAI-ARIA toolbar pattern: one tab stop, arrow keys,
  Home and End, mirrored in RTL, and Alt+F10 from the content. Toolbar buttons
  return focus to the content. The style select and colour inputs keep it.
  The toolbar state follows each transaction at once, so the select is never
  stale.
- Formatting: paragraph and headings 1–6, bold, italic, underline,
  strikethrough, lists, quote, alignment, text and background colour, links
  (http, https, mailto, tel and relative, as `HtmlSanitizer::safeUrl()`),
  tables with row/column tools, clear formatting, undo and redo. Labels are
  `store.editor.*` in English and Arabic; the dormant `store.richText.*` keys
  of the superseded `cf5ef305` editor are gone.
- Images come only from `StoreFileManagerDialog` (`type="image"`, the canonical
  library policy, shown with `view_uploads`), as upload URLs. Pasted or dropped
  HTML loses `data:` and unsafe images, classes and any style the toolbar
  cannot set. Base64 raster images already stored round-trip but cannot be
  added.
- Stored attributes no extension models (class, dir, lang and other style
  declarations on blocks, tables, cells, images and spans) are kept, so a
  legacy post keeps its bordered tables, indents, image widths and direction.

**Sanitisation and CSP.** `App\Services\HtmlSanitizer` is reused unchanged: the
editor emits no tag or attribute it lacks. Writes clean the body
(`Store\Admin\BlogController::write()`), the edit prop is cleaned
(`BlogResource`), and the public page still prints `clean_html()`. The
cleaner is idempotent, so legitimate stored content renders unchanged. Inertia
editor pages keep the strict policy (§2.5); the browser spec saw no violation.
No CSP directive, `blade_csp` entry, WAF pattern, exclusion or skip-key changed.
The held WAF rules let all editor output through, and still refuse a raw
script payload with a 403.

**Blog forms.**

- Routes, URIs and gates are unchanged: `blog.create`/`blog.store` under
  `add_blog`, `blog.edit`/`blog.update` (PUT) under `edit_blog`.
- `create` and `edit` render Inertia. `edit` sends the full post (cleaned
  body), the live categories in their Blade order (`id`) and canonical upload
  previews for the banner and meta image.
- `StoreBlogRequest`/`UpdateBlogRequest` keep their rules. The banner and meta
  image use `retainableImageUploadRule()`, so an unchanged foreign image
  round-trips without preview access.
- Every field, the slug rules, the default publication on create and the
  status-only publish action are unchanged. Blog posts have no translations.
- Deliberate changes:
  - A new post's slug follows its title until it is edited. Editing an
    existing post's title no longer rewrites its slug, and so its public URL.
  - The retired Blade edit form spoofed PATCH against the PUT-only route
    (`edit.blade.php:14`, `routes/shop-admin.php:546`), so it could not save.
  - Edited content is normalised by Tiptap (`b`→`strong`, `i`→`em`, list items
    wrap a paragraph, `pre`→`pre > code`, tables gain a `colgroup`). The empty
    remains of stripped video embeds are dropped.
- The list's Add and Edit links are Inertia visits. Root `BlogController` keeps
  only the public `all_blog`/`blog_details` actions; its unrouted admin methods
  and middleware are gone, and it stays in both analysis exclusion lists.
- `blog_system/blog/create.blade.php` and `edit.blade.php` are deleted; only
  the removed root actions rendered them. `admin_sidenav`'s
  `areActiveRoutes(['blog.create', 'blog.edit'])` names still resolve.

**Retained.** The Blade editor and its assets serve the newsletter, website
footer/appearance/custom pages, ticket detail, product and digital-product
forms and the seller and customer areas. Also retained: the legacy uploader,
shells, PDFs, `HandleStoreBladePages` and `store.blade_csp`. The rich-text WAF
exceptions, `manage_website_scripts`, script policy, chart/map activation,
seller/classified scope and server status stay held.

**Known limits.** External images in a legacy post do not display inside the
admin editor, because the strict `img-src` is `'self' data: blob:`. They are
kept, and the public Blade page still shows them. As before, body image URLs
are not ownership-checked on the server: the picker enforces the library
policy.

**Tests adapted in place, none weakened.** `BlogControllerTest`'s two Blade
view tests now assert the Inertia renders and full payloads. Its refused-caller
dataset gained a DB snapshot. The list test asserts Inertia create/edit links.

Current inventory: **58** backend Blade files, **34** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are unchanged at **1,050** under `/temp` and
**438** under `/temp/admin`. Verification is recorded in the editor section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 19. Newsletter and support ticket detail on the shared editor (2026-10-13)

The newsletter form now uses `Store/Admin/Marketing/Newsletters/Index`, and the
support ticket detail and reply use `Store/Admin/Support/Tickets/Show`, both
with `StoreRichTextField` (§18). Work is on `ccr-b2e34e7d-piwxdo`, branched from
`develop` at `a22e4d8` (the PR #392 merge). The owner authorized this session's
PR (#393), its merge into `develop` and `[skip ci]` on both. Every commit, the PR and
its merge commit carry `[skip ci]`, so no PR CI, no PR or merge SAST and no
`develop` deploy ran for them; the local checks in the ledger are the only
verification. Sources: `cf5ef305` (newsletter) and `e3995898` (ticket detail),
adapted to current `develop`, not cherry-picked.

**Newsletter.**

- **Routes, gate and send: unchanged.** `newsletters.index`,
  `newsletters.recipients` and `newsletters.send`, all `send_newsletter`.
  The send still refuses an unconfigured SMTP username (`smtp` error), merges
  and de-duplicates the user and subscriber emails, cleans the content with
  `clean_html()` before validation (`SendNewsletterRequest`), queues one
  `EmailManager` per recipient with `Mail::to()->queue()` on the default queue
  connection, and returns to the Blade dashboard. The subject stays plain text.
  The SMTP test stays on the settings page (`test.smtp`, `smtp_settings` and
  `send_newsletter`); the newsletter page has none.
- **Recipients.** The Blade page printed every user and subscriber email into
  two select boxes. The page now sends only `smtpConfigured`; each list fills
  from the existing bounded search (30 emails per query, debounced), keeps its
  selections across searches and posts `user_emails[]`/`subscriber_emails[]`.
  Either list satisfies the required recipient, mirroring `required_without`.
  The search text has no `name`, so it never posts.
- **Page.** `ResourceFormPage` (wide), `StoreNewsletterFormFields`,
  `StoreNewsletterRecipients` and `StoreRichTextField` for `content`, with the
  dormant `store.newsletters.*` keys (one added: `searchFailed`). The sidebar
  entry is an Inertia visit. The send's redirect to the Blade dashboard is a
  full load through `HandleStoreBladePages`.

**Ticket detail and reply.**

- **Routes and gates: unchanged.** `support_ticket.admin_show` (plain or
  `encrypt()`ed id, customerless tickets 404) under `view_all_support_tickets`;
  `support_ticket.admin_store` under `reply_to_support_tickets`. The list's View
  link is now an Inertia visit; the sidebar entry already was.
- **Visibility and side effects, unchanged.** A viewer sees every ticket with
  a customer. Opening one sets `viewed` (seen by staff) and nothing else. A
  reply stores the cleaned HTML, the caller as author and the attachment ids,
  sets `client_viewed = 0` and the chosen status in one transaction, then
  queues `SupportMailManager` to the customer.
- **Status.** The Blade button read "Submit as <current status>" but posted
  `pending` (`show.blade.php:43`, `:134-135`). The form's status select starts
  at the current status (`pending` for an unknown one) and posts what is
  chosen.
- **Page.** Header with author, date and status; the reply form (shown with
  `reply_to_support_tickets`); replies newest first, as the Blade page listed
  them, 20 per page with the per-page selector; the customer's original request
  closes the last page. Bodies render through `StoreSanitizedHtml` from
  `clean_html()` output only (`SupportTicketDetailResource`,
  `TicketReplyResource`). Avatars are not shown.
- **Attachments.** `App\Services\Store\TicketAttachments` reads the stored
  comma-separated ids of the ticket and the page's replies in one query (the
  Blade page queried per id) and returns the canonical `UploadResource`
  (`id, name, type, size, url`), in stored order, skipping missing uploads.
  A ticket's files stay listed for everyone who may open the ticket, whoever
  uploaded them, as before; that does not open them to the library: previews
  still need `view_uploads`, and a reply may attach only its author's images
  or, with `view_uploads`, library images (`uploadIdsRules('image')`). Links
  carry `download`; an upload with no safe http(s) URL shows its name only.
  The reply uses the canonical `StoreUploadField` (multiple images).
- **Reply redirect fix.** The reply returned to the detail by `encrypt()`ed
  id, which can contain a `/` the route cannot match, so it could 404. It now
  returns by the plain id. Customer and seller email links still carry
  `encrypt()`ed ids (inherited, unchanged).
- **Legacy HTML.** Replies are never edited. Stored summernote markup (`b`,
  `u`, `i`, lists, aligned paragraphs) renders as cleaned; the new reply starts
  empty, and the editor's output passes `HtmlSanitizer` unchanged.

**Consumers traced.** Only `NewsletterController::index` and root
`SupportTicketController::admin_show` rendered the two deleted views. Root
`admin_show` and its middleware entry are removed; the root controller keeps
the customer index/store/show and `seller_store`, and the seller controller is
untouched, so the customer and seller ticket pages, the admin-notification
email (`support_ticket.admin_show` link) and the reply email are unchanged.
`admin_sidenav`'s `newsletters.index` link and `areActiveRoutes` names still
resolve. `TicketAttachments`, `TicketReplyResource`, `StoreSanitizedHtml` and
the `store.newsletters.*` keys were dormant integration leftovers and now have
their first consumers; the dormant `StoreTicketAttachment` type and ticket
fixtures were replaced.

**Unchanged.** `HtmlSanitizer`, the strict Inertia CSP, `blade_csp`, WAF
patterns, exclusions and skip keys, route names, URIs, gates, permission
definitions, role grants, stored formats, Blade editor assets (18 fields in 16
views remain), the legacy uploader, shells, PDFs, `HandleStoreBladePages`, and
the held script policy, `manage_website_scripts`, chart/map activation,
seller/classified scope and server status.

**Known limits.** `tickets.created_at` and `ticket_replies.created_at` are
`useCurrentOnUpdate()`, so on MySQL the "seen" save also moves a ticket's
creation time, as the Blade page's did. In the browser harness the retained
Blade dashboard (the newsletter's return target) answers 500: strict mode
refuses its lazy load of product translations. Production does not run strict
mode, and the newsletter spec asserts the redirect rather than the dashboard.

**Tests adapted in place, none weakened.** `NewsletterControllerTest`'s Blade
view test became render and payload tests. `SupportTicketControllerTest`,
`SupportTicketAuthorizationTest` and `HtmlSanitizerTest` assert the Inertia
render and the cleaned props instead of the view, keeping their raw-response
`assertDontSee` checks. The ticket list test expects an Inertia View link; the
menu test lists the newsletter as Inertia. The blog editor spec's CSP and
toolbar helpers moved to `tests/e2e/support/editor.ts`. The browser harness
pins `MAIL_MAILER=array` and a placeholder SMTP username.

Current inventory: **56** backend Blade files, **32** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are unchanged at **1,050** under `/temp` and
**438** under `/temp/admin`. Verification is recorded in the newsletter and
ticket section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 20. Website footer, appearance and custom pages (2026-10-13)

The website footer, appearance and custom pages now use
`Store/Admin/Website/Footer`, `Appearance` and `Pages/Index|Create|Edit`, with
the shared editor (§18) for their rich text. Work is on
`claude/website-settings-inertia-migration-j3i59z`, branched from `develop` at
`ef175fc` (the PR #397 merge, after the PR #393 merge `06a3806`), and merged
with `develop` at `e17cdbd` (PR #399) before the push. The owner
authorized this session's PR, its merge into `develop` and `[skip ci]` on both.
Every commit, the PR and its merge commit carry `[skip ci]`, so no PR CI, no PR
or merge SAST and no `develop` deploy ran for them; the local checks in the
ledger are the only verification. Source: `e3995898`, adapted to current
`develop`, not cherry-picked; its scripts pieces are not taken.

**Footer (`website.footer`, gate `footer_setup`).**

- **Routes.** GET `/website/footer` and the new PUT `website.footer.update` on
  `Store\Admin\WebsiteFooterController`. Without a `lang` the screen opens in
  the back office's current language, as the Blade sidebar linked it.
- **Typed writer.** `UpdateWebsiteFooterRequest` takes `settings[…]` with the
  Blade form's keys only (`KEYS`) and a `lang`. Foreign, reserved,
  environment, script and general-settings keys, nested or top-level, and the
  legacy `types[]` shape are 422s before any write. `seller_app_link` is
  offered and accepted only while `vendor_system_activation` is on and
  `delivery_boy_app_link` only while the delivery boy addon is, as the Blade
  form showed them (`enabledKeys()`).
- **Stored formats, unchanged.** `about_us_description`, `contact_address`,
  `widget_one`, `widget_one_labels` and `frontend_copyright_text` are per
  language; everything else is shared. Upload ids are strings
  (`payment_method_images` comma-separated), the link labels and links JSON
  arrays (`json_encode`'s default escaping, as before), the switches `on` or
  null. The rows list one link per label of the language, as the Blade form
  did. The tutorial switch is ticked for any stored value and the social one
  only for `on`, as the Blade form read them.
- **Validation.** Links are http(s), root-relative or fragments (≤2048), the
  email an email, short texts ≤255, link rows paired and ≤50. Images use
  `retainableImageUploadRule()`; the payment logos keep any already-stored id
  and need the library rule for new ones.

**Appearance (`website.appearance`, gate `website_appearance`).**

- **Typed writer.** `website.appearance.update` (PUT) on
  `Store\Admin\WebsiteAppearanceController` with `UpdateWebsiteAppearanceRequest`:
  name, motto, eight images, both colours (`#rgb`/`#rrggbb`, they print into
  CSS), meta texts, the cookie and popup texts and three switches. The seller
  and delivery boy login backgrounds follow the same feature conditions as the
  Blade form. Script keys are refused here.
- **Scripts: unchanged and held.** The page shows `header_script` and
  `footer_script` as stored, in plain `dir="ltr"` code text areas (never the
  editor), and posts them in their own form to
  `website.scripts.compatibility.update` (`LegacyWebsiteScriptsController`),
  which still allows only those two keys and still returns to the appearance
  screen. The storefront still echoes them raw; the WAF still refuses a
  `<script>` payload with a 403. `WebsiteScriptsController`,
  `UpdateWebsiteScriptsRequest`, `manage_website_scripts` and any
  `config/waf.php` change stay unactivated.

**Custom pages (`website.pages`, `custom-pages.*`).**

- **Routes.** `Store\Admin\WebsitePageController` with the existing gates:
  `website.pages` (`view_all_website_pages`), `custom-pages.create`/`.store`
  (`add_website_page`), `custom-pages.edit` at the same
  `/custom-pages/edit/{slug}` URI and `custom-pages.update` (PUT/PATCH)
  (`edit_website_page`), `custom-pages.destroy`, now `DELETE
  /custom-pages/destroy/{page}` (`delete_website_page`). The `Route::resource`
  duplicates and the empty `custom-pages.index`/`.show` are gone. Root
  `PageController` keeps only the public `show_custom_page` and
  `mobile_custom_page`, unchanged; `website.home.edit` stays ahead of the edit
  route.
- **Behaviour kept.** A new page is a custom page in the default language. The
  slug is normalised as the Blade writer did (spaces to hyphens, anything but
  letters, digits and hyphens dropped), as typed and again on the server, and
  must be unique among all pages; only custom pages change it. A default-
  language save rewrites the base title and content; any save writes the meta
  fields and the language's translation. The list pages 20 at a time with the
  selector (it listed all), in `id` order, with the title in the back office's
  language. The home row opens the home settings.
- **Deliberate changes.** Deleting a policy or home page, and updating the home
  page through this form, are 403s (only custom pages offered delete); a
  duplicate slug is a field error instead of a flash.

**Sanitisation.** The four settings texts and the page content are cleaned
with `HtmlSanitizer` before validation and the edit props are cleaned. The
storefront already prints the footer texts and the popup through
`clean_html()`; the cookie text, custom and policy pages still print raw, as
before, so cleaning on write is what now keeps scripts out of them. The
cleaner is idempotent, so legitimate stored markup renders unchanged; legacy
summernote markup (`b`, `u`, `i`, lists, aligned paragraphs, tables) round-
trips as stored. Links gain `rel="noopener noreferrer nofollow"`. Not kept:
`<iframe>` video embeds, `<font>` colours and forms, which the editor cannot
represent and the cleaner removes; the WAF already refused a save carrying an
iframe.

**Generic writer retired.** After both forms moved, nothing posts to
`business_settings.update`: `rg` over `resources/views`, `resources/js`
(outside the generated Wayfinder files), `public/assets/js`, `app` and
`routes` finds no consumer, and `SettingsReconciliationTest` pins that. The
route, root `BusinessSettingsController::update`, its
`general_settings|footer_setup|website_appearance|edit_website_page` gate (the
#391 gap where any one of four permissions wrote footer and appearance keys)
and `overWriteEnvFile()`, whose last callers were the writer's `site_name` and
`timezone` branches, are removed. The URI now answers 404 for every caller.

**Tests retargeted, none weakened.**

- Generic-writer refusals (`SettingsReconciliationTest`,
  `ReferenceSettingsIsolationTest`, `SettingsControllerTest`,
  `WebsiteHeaderControllerTest`, `WebsiteHomeControllerTest`,
  `GoogleMapControllerTest`, `LegacyAdminPermissionsTest`) now assert the 404
  of the retired URI (`retiredSettingsWriterUri()`) with the same DB
  assertions, and the footer and appearance writers refuse every other page's
  key, plain or localized. The four-permission save test became "each former
  permission saves only its own page". `LegacyEnvFileProtectionTest` pins that
  no `.env` writer is left.
- Blade-page examples moved to the seller list (`/temp/admin/sellers`, seller
  decision): `HandleStoreBladePagesTest`, `ReconstructedMigrationAuditTest`,
  the general-settings shell test, `store-staff-coexistence.spec.ts` (Sellers →
  All sellers) and `store-setup-settings.spec.ts`.
- No seller or trips admin screen uses the legacy uploader, and the admin's
  seller product form (`products.seller.edit`) answers 500 on an inherited
  defect (`edit.blade.php:244`, `$variations` undefined). So
  `store-legacy-uploader.spec.ts` uses the retained product create form,
  scoped to `thumbnail_img`; it keeps real browser coverage, and whoever
  converts the product form must retarget it.
- `LegacyUploadSecurityTest`'s "page editor prints its values escaped" read
  the Blade view; it now checks that the Inertia editor files print no raw
  HTML and that hostile stored values reach the page escaped and cleaned.
- `LegacyControllerMiddlewareConversionTest` pins four root entries;
  `ControllerRedirectsTest` no longer needs its `PageController` exclusion.

**Retained.** The Blade editor and its assets (12 fields in 12 views: the admin
product and digital-product create/edit forms and the seller and customer
areas), the legacy uploader, shells, PDFs, `HandleStoreBladePages`,
`store.blade_csp`, the strict Inertia CSP, the WAF, and the held script policy,
`manage_website_scripts`, rich-text WAF exceptions, chart/map activation,
seller/classified scope and server status.

**Known limits.** Inertia embeds page props as `json_encode()` JSON, which
escapes `</script>` but not `<!--`; a stored script holding an unterminated
`<!--` before a `<script` could keep the appearance page from booting (it
cannot execute). Links in the footer had no format rule before: a stored link
such as `www.example.com` must be corrected on the next save.

Current inventory: **51** backend Blade files, **27** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are **1,047** under `/temp` and **435** under
`/temp/admin`: ten replace thirteen. Verification is recorded in the website
settings section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 21. Digital product create and edit forms (2026-10-13)

Digital product create and edit now use `Store/Admin/Product/DigitalProducts/Create`
and `Edit` with the shared editor (§18) and the canonical upload picker (§2.4).
Work is on `claude/digital-product-forms-inertia-v66574`, branched from
`develop` at `bb942f0` (the PR #400 merge). The owner authorized this
session's PR (#401), its merge into `develop` and `[skip ci]` on both. Every
commit, the PR and its merge commit carry `[skip ci]`, so no PR CI, no PR or
merge SAST and no `develop` deploy ran for them; the local checks in the
ledger are the only verification.

**Routes and gates: names, URIs and methods kept.** `digitalproducts.create`
(GET `/digitalproducts/create`) and `.store` (POST `/digitalproducts`) under
`add_digital_product`; `.edit` (GET `/digitalproducts/edit/{product}`) and
`.update` (PUT/PATCH `/digitalproducts/{product}`) under
`edit_digital_product`, now on `Store\Admin\DigitalProductController` with a
bound product. Edit and update accept only a digital product (404 otherwise).
Root `DigitalProductController` is deleted and leaves both analysis exclusion
lists; the list's Add and Edit links are Inertia visits, download stays a full
load.

**Writes (behaviour kept, now atomic).** `StoreDigitalProductRequest` and
`UpdateDigitalProductRequest` (`Concerns\ValidatesStoreDigitalProduct`)
validate; the controller writes in one transaction, the product row locked on
update:

- **Create.** `added_by = admin`, the store caller as `user_id`, `digital = 1`,
  every form field, the slug `<name letters/digits/hyphens>-<rand(10000, 99999)>`,
  then the posted taxes through `ProductTaxService`, one stock row (`variant`
  and `sku` empty, the unit price, quantity 0) and the default-language
  translation. Returns to the list.
- **Update.** The base name and description change only in the default
  language; the posted language's translation is written; the shared fields
  are written for every language; every stock row is replaced by the same new
  row (so the quantity returns to 0, as before); posted taxes replace all the
  product's tax rows, and with no tax posted (no active tax) they stay. The
  slug is lowercased. Returns to the edit form in the same language (the Blade
  `back()`).
- **Second writer of `products`: owner decision pending.** Creating and
  updating write WMS-owned `products` rows beside `products:sync`
  (shared-database.md), the same conflict as deletion (§§14–16). Behaviour and
  policy are unchanged; tests pin it, including rollback of product, stock,
  translation and taxes together.
- No cache was cleared before and none is now.

**Fields.** Name (≤200, the `products.name` and translation width), category,
photos (CSV, ≤2000 chars, ≤50 ids), thumbnail, tags, description, unit and
purchase price, discount and type, discount range, the active taxes (amount
and type each), meta title, description and image, the file and, on edit, the
slug. Amounts are `decimal(20,2)`: `0`–`999999999999999999.99`, two
decimals. MEDIUMTEXT/LONGTEXT fields (meta texts, slug, description) have no
length rule.

- **Category.** The options are built server-side, as categories (§13) do:
  `Category::treeOptions()` (now shared with the category form) walks the
  digital root categories and every category below them, by id, prefixed
  `--` per level, as the retained `shop.categories.child_category` partial
  printed them; the request accepts only those ids. The partial stays for its
  seller and product consumers.
- **Tags.** The Blade tag widget posted JSON in `tags[0]`; the form now posts
  one comma-separated text, stored trimmed, without empties or repeats and
  comma-joined, the format the storefront already reads.
- **Uploads.** Photos, thumbnail and meta image are images, the file an upload
  of any type, as the legacy pickers offered them. New references need the
  caller's own upload or, with `view_uploads`, the library; an unchanged
  stored reference round-trips whoever owns it without granting a preview
  (`retainableImageUploadRule()`, the new `retainableUploadReferenceRule()`
  for the file and `retainableImageUploadIdsRule()` for the photos, the last
  extracted from the footer's payment logos). `file_name` keeps the upload id
  the encrypted download (§15), the storefront, seller and mobile downloads
  read.
- **Taxes.** Each active tax (`tax_status = 1`, by id) is listed with the
  product's stored amount and type (0 and `amount` without one); `tax_id[]`,
  `tax[]` and `tax_type[]` must match in length, distinct active taxes only.

**Deliberate changes.**

- **Discount range now stored.** Both Blade forms posted `date_range`, and the
  edit form showed a stored range, but neither root `store` nor `update` read
  it (seller forms neither), so a range set here was silently dropped. It is
  now validated (`d-m-Y H:i:s to d-m-Y H:i:s`, real dates, end not before
  start; `Concerns\ValidatesDiscountDateRange`, shared with the brand
  discount) and stored as `ProductService` stores it: `strtotime()` of each
  half in the app timezone (UTC). An empty range clears both dates, so the
  discount applies without a period, as the storefront treats null dates.
- **Blank slug on update** is regenerated from the base name as on create,
  instead of being stored empty.
- **Validation.** Fields gained the schema bounds above; unknown categories,
  inactive taxes and foreign uploads are 422s.
- **Shared product edit redirect fixed.** `ProductController::admin_product_edit`
  and `seller_product_edit` sent a digital product to `admin/digitalproducts/{id}/edit`
  and `digitalproducts/{id}/edit`, URLs without `/temp` that match no route,
  so the all-products list's Edit for a digital product reached a 404. They
  now redirect to `digitalproducts.edit` with the request's `lang`.

**Description.** Cleaned with `HtmlSanitizer` before validation (stored base
and translation alike) and on the edit prop. The storefront digital product
page already prints `clean_html()` (`digital_product_details.blade.php:555`),
so legitimate content renders unchanged; the mobile product APIs return the
stored text, which is now cleaned. Legacy summernote markup round-trips.
The strict CSP, `blade_csp`, WAF patterns and exclusions are unchanged; the
WAF still refuses a `<script>` or `onerror=` save with a 403 before any write.

**Retained.** `shop.categories.child_category`, the seller and customer digital
product forms and their Blade editor, product create/edit, the legacy uploader
and its browser test on the physical product create form, shells, PDFs,
`HandleStoreBladePages`, `store.blade_csp`, and the held script policy,
`manage_website_scripts`, rich-text WAF exceptions, chart/map activation,
seller/classified scope and server status.

**Known limits.** In the browser harness (strict mode) a storefront product
page lazy-loads translations of its owner's other products and 500s when that
list holds a product without a slug; the browser spec gives its fixtures their
own owner. Production does not run strict mode.

**Tests adapted in place, none weakened.** `DigitalProductControllerTest`'s
retained-Blade-forms test asserts the Inertia renders and the update redirect
to the form; its migration test asserts both views are gone. The list's
Vitest and browser specs expect Inertia create/edit visits.

Current inventory: **49** backend Blade files, **25** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are unchanged at **1,047** under `/temp` and
**435** under `/temp/admin`: five replace five. Blade editor: **10** fields in
**10** views. Verification is recorded in the digital product forms section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 22. Physical product create form (2026-10-13)

The physical product create form now uses `Store/Admin/Product/Products/Create`
with the shared editor (§18), the canonical upload picker (§2.4) and a typed
variant endpoint. Work is on `ccr-68328473-iie1w4`, branched from `develop` at
`6a10019` (the PR #401 merge). The owner authorized this session's PR (#402), its
merge into `develop` and `[skip ci]` on both. Every commit, the PR and its
merge commit carry `[skip ci]`, so no PR CI, no PR or merge SAST and no
`develop` deploy ran for them; the local checks in the ledger are the only
verification. The edit form stays Blade for the next session.

**Routes and gate.** `products.create` (GET `/products/create`) and
`products.store` (POST `/products/store`) keep their names and URIs under
`add_new_product`, now on `Store\Admin\ProductController`; the new
`products.variant_combinations` (POST `/products/variant-combinations`, same
gate) returns the variant rows as JSON. Root `ProductController` loses
`create`, `store` and their middleware entry (four remain) and keeps the
edit/update, seller list, jQuery switches and the Blade SKU endpoints
(`products.sku_combination`, `.sku_combination_edit`), which the edit form
and `Seller\ProductController` still render. The list's Add link and the
sidebar entry are Inertia visits; Blade links by route name still resolve.

**The legacy write path did not work.** `Product`'s `#[Fillable]` holds only
the four `products:sync` columns since the model merge, and
`ProductService::store()` and `ProductStockService::store()` mass-assign the
form: outside strict mode every other column was silently dropped (no
category, price, unit, slug or owner), and in local/testing (strict mode) the
write throws `MassAssignmentException`. `Services\Store\InHouseProductWriter`
computes what `ProductService::store()` computes (the store's own non-vendor
admin account as owner, approval, comma-joined tags, the discount period, the
meta defaults, the shipping cost, a numbered unique slug, the colour, choice
and attribute JSON, publication from the button pressed) and sets the
columns explicitly; stock rows are written as `ProductStockService` meant to.
Taxes and the flash deal still go through `ProductTaxService` and
`ProductFlashDealService`. The services are unchanged for the seller panel
and the seller API, which keep the defect (reported, out of scope); the import
commands write products directly and share none of them.

**Atomic store.** Product, stock rows, taxes, flash deal entry, default-
language translation, product types and store barcodes commit or fail
together in one transaction; `view:clear` and `cache:clear` run after commit
(never on failure). The request then returns to the in-house list.
`digital = 0`, `added_by = admin` and the owner are set by the server; a
posted `digital`, `added_by`, `user_id`, auction, wholesale or counter field
is ignored.

**Second writer of `products`: owner decision pending.** Creating a product
writes WMS-owned `products` beside `products:sync` (shared-database.md), the
same decision as §§14–16 and §21; behaviour and policy are kept, tests pin it.

**Fields (`StoreProductRequest`).** Every field the Blade form posted, with the
schema widths: name ≤200, unit ≤20 (both also `product_translations`), weight
`decimal(8,2)`, quantities `int`, tags ≤500 (comma-separated, as §21), photos
≤2000 and ≤50 ids, amounts `decimal(20,2)`, SKU ≤255, video link ≤100 and
external link ≤500 as http(s) URLs, button text ≤255. The category is one of
the physical tree (`Category::treeOptions()`); taxes use the shared
`ValidatesStoreProductTaxes` (extracted from §21's digital rules); the
discount keeps the legacy rule (flat below the unit price, percentage below
100) and the range uses `ValidatesDiscountDateRange`. Uploads: photos,
thumbnail, meta image and variant photos are images, the PDF a `document`
upload, each the caller's own or, with `view_uploads`, the library's.
Shipping (product-wise only with `shipping_type = product_wise_shipping`),
estimated days, flash deal (`Flash_deals`, active deals only, a type required
with a deal), taxes (`VAT_TAX_section`) and the cash-on-delivery switch
(`cash_payment`) are excluded while their settings hide them, as the form
never posted them.

**Variants.** Colours (by code, while "use colours" is on) and attributes with
their values (each value one of its attribute's, distinct per attribute)
generate the rows. `Services\Store\ProductVariantCombinations` mirrors the
Blade table (`sku_combinations.blade.php`): the cartesian product with colours
first, then each attribute in list order, values in list order; a colour is
named by its stored name, every other part with spaces removed, joined with
`-`; each row defaults to the unit price as typed, an empty SKU and a quantity
of 10. Pest compares the JSON with the retained Blade endpoint for four choice
sets. The store recomputes the names and refuses rows that do not match; at
most 500 rows (the legacy form had no bound and timed out), no name wider than
`product_stocks.variant`, no two equal names.

**Deliberate changes.**

- **The colour row is shown.** Both admin Blade forms hid it (`d-none`), so
  colours could not be chosen; the owner's scope lists colours among the
  form's fields. The write is the legacy one.
- **Cash on delivery.** A hidden input after the switch always posted `1`, so
  unticking it did nothing. The switch is now honoured where it shows; where
  `cash_payment` hides it, `1` is stored as before.
- **Removed addons.** The POS barcode, refund and club-point fields depended on
  addons removed in phase 1 (§2.2); `refundable` and `earn_point` have no
  column. They are not offered.
- **Barcodes.** Store barcodes (`product_barcodes`) must be distinct and not
  already stored, the convention the Otajer import keeps; WMS barcodes
  (`wms_product_barcodes`, unique, #389) are untouched by this form.
- **A colour name with a space** (e.g. "Light Blue") kept its space in the
  variant name but PHP rewrote the posted field name, so the legacy stock row
  lost its price, SKU and quantity; the structured rows store them.
- An attribute chosen without a value is a field error instead of a 500.

**Description.** Cleaned with `HtmlSanitizer` before validation (product and
translation alike); the meta description default strips the cleaned text.
The storefront prints `clean_html()` (`product_details/description.blade.php:22`),
so legitimate content renders unchanged; the mobile API returns the stored,
now cleaned, text. The strict CSP, `blade_csp`, WAF patterns and exclusions are
unchanged: the WAF still refuses a `<script>`, `onerror=` or `javascript:`
payload with a 403 before any write.

**Components (reusable by the edit session).** `StoreProductFormFields`,
`StoreProductVariantFields`, `StoreProductListField` (types and barcodes),
`StoreProductTaxFields` and `StoreProductDiscountFields` (both extracted from
§21's digital form, now their second consumer), `lib/storeProductForm.ts`,
`store.products.form.*` and the shared `store.productForm.*` keys in English
and Arabic. The publish button is the form's submit; "Save & unpublish" posts
`button=unpublish`, first in the form as on the Blade page.

**Consumers traced.** Only root `create` rendered `create.blade.php`, now
deleted. `choice_options_script.blade.php` (edit form),
`sku_combinations*.blade.php` (edit form, `Seller\ProductController:209/239`),
`shop.categories.child_category`, PDFs, shells, the legacy uploader,
`HandleStoreBladePages` and `store.blade_csp` stay. `admin_sidenav` and the
seller list's Blade links use the route name.

**Tests adapted in place, none weakened.** `ProductControllerTest`'s retained
forms test asserts the Inertia create render and the Blade edit form;
`LegacyControllerMiddlewareConversionTest` counts four root entries; the digital
form's Vitest reads the shared `store.productForm.*` labels;
`storeAdminMenu.test.ts` lists the create entry as Inertia; the list's Vitest
expects an Inertia Add link. `store-offers-catalogue.spec.ts` drives the
choice script on the retained Blade edit form. `store-legacy-uploader.spec.ts`
moved to the seller panel's product form (`/temp/seller/product/create`), which
keeps the legacy uploader long-term: a seeded seller uploads, selects, removes
and deletes through the seller endpoint, and the Blade-to-Inertia hop now
starts from the retained seller list.

**Known limits.** In the strict-mode browser harness the storefront product
page still lazy-loads the owner's other products (§21); the spec gives its
fixtures their own owner before visiting it. The seller product form lazy-loads
tax rows under strict mode (inherited); Pest relaxes lazy loading for that one
render.

Current inventory: **48** backend Blade files, **24** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout` and **0**
of the blank shell. Registrations are **1,048** under `/temp` and **436** under
`/temp/admin`: three replace two. Blade editor: **9** fields in **9** views.
Verification is recorded in the product create form section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 23. Physical product edit form (2026-10-14)

The physical product edit form now uses `Store/Admin/Product/Products/Edit`
with the create form's components (§22), the shared editor (§18), the
canonical upload picker (§2.4) and the language tabs. Work is on
`claude/magical-lamport-g4w0c0`, from `develop` at `cb02808` (the PR #402
merge). The owner authorized this session's commit, PR (#403), its merge into
`develop` and `[skip ci]` on all of them, knowing the checks had not run. So no
PR CI, no PR or merge SAST and no `develop` deploy ran, and the local checks in
the ledger are the only verification: every backend, Vitest, type and browser
check is still outstanding (no PHP 8.5, `vendor/` or `node_modules` in the
session).

**Routes and gate: names kept.** All four are on `Store\Admin\ProductController`
under `product_edit`, the root controller's gate:

- `products.admin.edit` (GET `/products/admin/{product}/edit`) and
  `products.seller.edit` (GET `/products/seller/{product}/edit`): the same
  page, `listType` `admin` or `seller`; each language tab stays on the route it
  was opened from. The URI parameter is now the bound `{product}` (it was
  `{id}`), so the Blade seller list's links pass `product`. A missing id is a
  404; a digital product still goes to `digitalproducts.edit` with its `lang`.
- `products.update` (POST `/products/update/{product}`, method and URI kept):
  `UpdateProductRequest`; a digital product is a 404 before validation.
- `products.edit_variant_combinations` (new, POST
  `/products/{product}/variant-combinations`): the variant rows for the edit
  form (below); a digital product is a 404.

Root `ProductController` loses `admin_product_edit`, `seller_product_edit`,
`update`, `sku_combination_edit` (route `products.sku_combination_edit`, whose
only consumer was the deleted form) and their gate entries, keeps its four
middleware entries (`product_edit` now covers only the jQuery switches) and
stays in both analysis exclusion lists. `LegacyAdminPermissionsTest` drops
the `sku_combination_edit` ratchet entry. The list's Edit link is now an
Inertia visit; the sidebar has no edit entry, so `storeAdminMenu.ts` and its
test are unchanged.

**Writes (behaviour kept, now atomic).** `UpdateProductRequest` and
`StoreProductRequest` share `Concerns\ValidatesStoreProduct` (extracted from
§22's request: the same fields, widths, settings exclusions, variant checks
and description cleaning). `InHouseProductWriter::update()` sets every column
explicitly, in one transaction with the product row locked; `view:clear` and
`cache:clear` run after commit. The legacy `ProductService::update()` mass-
assigned the form, which Product's `#[Fillable]` reduced to the four
`products:sync` columns, so the Blade form saved almost nothing (§22's
defect); `ProductService` and `ProductStockService` stay unchanged for the
seller panel and API.

- **Languages.** The base name, unit and description change only in the
  default language; the edited language's translation is written (created when
  missing). Everything else is shared and written whatever the language, as
  the legacy update did. Meta defaults take the posted (translated) name and
  description, as before. Without a `lang` the form opens in the default
  language; an unknown `lang` is refused.
- **Kept as they are:** owner, `added_by`, approval, publication, `digital`.
- **Stock rows.** A stored row is kept, updated in place (same id), for each
  posted row whose variant it already names (the first such row when names
  repeat); new variants add rows and rows no posted variant names are deleted.
  Without variants one empty-variant row holds the unit price, quantity and
  SKU. The legacy update deleted and recreated every row.
- **Taxes** are replaced when posted; with the tax section off they stay.
  **Types and store barcodes** are replaced; the product's own barcodes may be
  posted again, another product's may not. **Flash deal:** a chosen active
  deal applies through `ProductFlashDealService` (discount and period); none
  chosen leaves an existing entry, as the service always did.
- **Second writer of `products`: owner decision pending.** Updating writes
  WMS-owned `products` beside `products:sync` (shared-database.md), as §§14–16,
  §21 and §22; policy unchanged, tests pin it.

**Variant editor.** The page sends the stored choice set: colours in the list's
order (on while any is chosen), attributes in their stored order (it names the
stored variants), each attribute's values in its value list's order with a
repeated value offered once, the values lists and the rows. Stored colours,
attributes and values that no longer exist are left out, so they drop on
save, as the Blade selects dropped them. The initial rows are the stored rows
(price, SKU, quantity, photo with a policy-gated preview); the page does not
regenerate them until a choice or the unit price changes.

**Decision: changing the choices.** The rows regenerate through
`products.edit_variant_combinations`: a variant that already has a stored row
keeps its stored price, SKU, quantity and photo; a new variant starts at the
unit price as typed, an empty SKU and quantity 10 (the create defaults). On
save the rows of dropped variants are deleted. Deliberate changes from the
Blade SKU table: it re-priced every row to the typed unit price once the unit
price differed from the stored one, and pre-filled a new row's SKU with its
variant name.

**Uploads.** Stored references round-trip whoever owns them without a preview
(`retainableImageUploadRule()`/`retainableUploadReferenceRule()`/
`retainableImageUploadIdsRule()`); a variant photo is retained only on the
variant whose stored row holds it. New references follow the library rules.

**Deliberate changes.**

- **Slug.** A typed slug is normalised (`Str::slug`) and must be free among
  other products (field error). A blank slug is regenerated from the base
  name, numbered as on create. The legacy update renumbered a slug on every
  save once another product's slug began with it.
- **`variant_product`** now returns to 0 when the variants are removed.
- **Hidden switches keep their values.** The legacy update set
  `cash_on_delivery` and `is_quantity_multiplied` to 0 when their settings hid
  them and reset `shipping_cost` to 0 outside product-wise shipping; they now
  keep their stored values.
- **Sections follow the create form.** The Blade edit form hid the colours,
  shipping, today's deal, flash deal and shipping days blocks (`d-none`) but
  still posted them; they show under the same settings as on create (§22).
- **Otajer images** (`old_photo`, multiple images) and the slug show only on
  the edit form, as on the Blade form. The removed addons' fields stay out.
- **Validation.** The create form's schema bounds apply; legacy free-text
  video or external links that are not http(s) URLs must be corrected on the
  next save.

**Description.** Cleaned with `HtmlSanitizer` before validation (base and
translation) and on the edit prop, as §22. The strict CSP, `blade_csp`, WAF
patterns and exclusions are unchanged; the WAF still refuses a `<script>` or
`onerror=` save with a 403 before any write.

**Consumers traced and files deleted (`git rm`).**

- `product/products/edit.blade.php`: rendered only by the removed root
  `admin_product_edit`/`seller_product_edit`.
- `product/products/choice_options_script.blade.php`: included only by that
  form (`rg` over `resources/views`, `app`, `routes`, `public/assets/js`). The
  seller and customer forms have their own scripts.
- `layouts/blank.blade.php`: no `@extends`/`@include`/`view()` caller in
  `resources/views`, `app`, `routes` or `config`; its markup is the removed
  installer's card. `StoreBackOfficeShellTest` pinned only its existence and
  now asserts it is gone with no reference.
- Kept: `sku_combinations_edit.blade.php` (rendered by
  `Seller\ProductController::sku_combination_edit`, `seller.products.sku_combination_edit`),
  `sku_combinations.blade.php` (seller and root `products.sku_combination`),
  `product/products/index.blade.php` (seller list) and
  `product/reviews/index.blade.php`: root `ReviewController::index` still
  renders it for `reviews.index` (`/temp/reviews`), linked from `admin_sidenav`
  and pinned by `ReviewControllerTest`, so it was not an orphan.

**Tests adapted in place, none weakened.** `ProductControllerTest`'s retained
forms test asserts both Inertia forms, the four routes and their gate;
`ProductCreateFormTest` asserts the edit view and choice script are gone and
the admin SKU edit route is removed (the seller one still renders);
`DigitalProductFormTest` passes `product` to the edit routes;
`LegacyControllerMiddlewareConversionTest` pins the remaining `product_edit`
entry; `store-offers-catalogue.spec.ts` drives hostile attribute titles and
values on the Inertia edit form. New: `ProductEditFormTest.php` (beside
`ProductCreateFormTest.php`, as §22 did; no `Legacy*Test` held edit or update
cases to move), `Edit.test.ts`, edit cases in the form, variant and list field
tests, and `store-product-edit.spec.ts`.

**Known limits.** On MySQL the stored decimals come back as `12.50`, so the
edit form shows them as stored (Pest asserts the sqlite values). A product
whose stored choice order does not match its stock names (older imports)
shows default rows for the unmatched variants; saving deletes the unmatched
rows, as the Blade form did.

Current inventory: **45** backend Blade files, **23** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`, no
blank shell. Registrations are unchanged at **1,048** under `/temp` and
**436** under `/temp/admin`: four replace four (counted from the route files;
`route:list` could not run). Blade editor: **8** fields in **8** views.
Verification is recorded in the product edit form section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 24. Trip lists and status actions (2026-10-14)

The all-trips and today's-trips lists now share `Store/Admin/Trips/Trips/Index`
(`listType` `all` or `today`); the Blade status modal is `StoreTripStatusDialog`.
Work is on `ccr-861a6e50-1xombz`, from `develop` at `4cbb84d` (the PR #403
merge). The owner authorized this session's commit, PR (#404), its merge into
`develop` and `[skip ci]` on all of them, because the checks run locally. So no
PR CI, no PR or merge SAST and no `develop` deploy ran. The ledger records what
ran here (Vitest, vue-tsc, ESLint and Prettier, against stand-in Wayfinder
modules) and what did not (Pest, Pint, PHPStan, Wayfinder generation, the build
and Playwright: no PHP 8.5 or `vendor/` in the session).

**Routes and gates: names and permissions kept.** Six routes moved from root
`Trip\TripController` to `Store\Admin\TripController` (`HasMiddleware`):

- `trips.index` (GET `/trips/index`) and `trips.today-trips` (GET
  `/trips/today-trips`): `view_all_trips`; `FilterTripsRequest` (`per_page`,
  `search`, `status[]`).
- `trips.update-status` (PUT `/trips/{trip}/status`; was POST
  `/trips/update-trip-status` with `id`) and `trips.update-notes` (PUT
  `/trips/{trip}/notes`; was POST `/trips/update-trip-notes` with `trip_id`):
  `edit_trip`.
- `trips.destroy` (DELETE `/trips/{trip}`; was POST `/trips/delete/{id}`) and
  `trips.bulk-trip-delete` (POST `/trips/bulk-trips-delete`, URI kept):
  `delete_trip`.

Every write takes `list` (`StoreTripList`: `all`, `today` or `map`) and returns
to that screen with the rest of its query, as the product lists' `list` does
(§16). A missing bound trip is a 404. The root controller keeps the trip forms,
the checkpoints page and its actions, the map picker, the trip orders and the
CMR download, and stays in both analysis exclusion lists. Its
`lockTruckForTrip()` moved unchanged to `Concerns\ReservesTripTrucks`, which
both controllers use. No gate changed, so `LegacyAdminPermissionsTest` is
unchanged; `LegacyAdminOwnershipTest`'s four trip-list rows moved to
`TripControllerTest`'s per-permission dataset.

**Behaviour kept.**

- **Status transitions:** the legacy body, unchanged, in one transaction.
  Closing keeps the first close time, marks the trip's order checkpoints
  delivered and frees the truck only when no other open trip holds it; starting
  or reopening reserves the truck again (a `truck_id` error, shown in the
  dialog, when another open trip holds it) and keeps the first start time. The
  response is a redirect instead of the jQuery `1`.
- **Departure ID 9:** untouched; only the retained create and map writers add
  the departure checkpoint.
- **Search:** the `Trip::search()` scope, unchanged (exact code; status,
  driver, truck and checkpoint customer by `LIKE`).
- **Deleting** removes the checkpoints with one query, so no checkpoint
  `deleting` hook runs and the orders keep their delivery status, as before.

**Deliberate changes.**

- **Truck availability on delete.** The legacy single delete freed the truck
  even while another open trip held it, and the bulk delete never freed it (a
  truck whose only open trip was bulk-deleted could not be assigned again).
  Both now free it only when no other open trip holds it, with the trip locked.
- **Bulk selection** is validated (1–100 distinct existing trips; otherwise a
  field error and nothing deleted). The legacy handler skipped unknown ids.
- **Notes** take the edit form's rule (`ValidatesStoreTrip`: nullable string,
  at most 255); the inline Blade field accepted any length. They still save on
  change; "Notes saved." shows under the row instead of a toast.
- **Lists** page 20 at a time with the per-page selector (was 15), newest
  first then by id. The status filter is `status[]` (any of pending, started,
  closed) instead of the `pending`/`started`/`closed`/`allTrips` flags. Today's
  list uses `whereDate('due_date', today())`, equal to the old comparison on
  MySQL.
- **Display.** Start and close times use `formatDateTime` (the browser's locale
  and time zone; Blade printed server-time `Y-m-d H:i`); the distance is in
  kilometres to two places in the locale's number format; the duration uses
  the shared `formatDuration` with translated units (Blade printed Carbon's
  English short span, `1s` where the page now shows `0s`).
- **Links.** Driver and truck names link to their Inertia edit pages for a
  viewer with `edit_driver`/`edit_truck` (pages.md). The trip code (checkpoints
  page), Edit and Create trip load their Blade pages in full; Create order
  (`add_order`) is an Inertia visit to the order entry.

**Map picker.** Its truck cards' delete link is now `data-method="delete"` with
`route('trips.destroy', ['trip' => …, 'list' => 'map'])`. `aiz-core.js`
`postLink` also handles `a[data-method="delete"]`, adding `_method=DELETE` to
the CSRF form; `.confirm-delete` passes the mark on to the modal's link as
before. After the delete the picker reloads (it was Blade `back()`).
`LegacyStoreStateChangingRoutesTest` accepts `data-method="post"` or `"delete"`
and pins the `_method` field.

**Consumers traced and files deleted (`git rm`).**

- `trips/index.blade.php`: rendered only by the removed root `getTrips()`.
- `trips/components/update-trip-status-modal.blade.php`: included only by
  `trips/index.blade.php`.
- `trips.update-status`, `trips.update-notes` and `trips.bulk-trip-delete` had
  no consumer outside those two views; `trips.destroy` also serves the map
  picker (above). Traced with `rg` over `resources/views`, `app`, `routes`,
  `public/assets/js`, `resources/js` and `tests`.
- Kept: `trips/inc/scripts.blade.php` (create, edit and map picker),
  `inc/map-popups`, `inc/map-scripts`, the checkpoint components and
  `update-checkpoint-status-modal` (checkpoints page) and
  `shop.modals.delete_modal`. `admin_sidenav.blade.php` links the lists by name
  and needs no change.

**Menu.** All trips and Today's trips are Inertia entries from the new
controller (`storeAdminMenu.ts` and its test); Create trip, Create trip from
the map and Orders stay Blade.

**Tests.** `TripControllerTest.php` keeps the root controller's cases; its
status cases now use the bound PUT route and assert the redirect, and new cases
cover the list payload, filters with noise rows, pagination and `per_page`,
refused callers, the per-permission matrix, notes, delete (orders, trucks, the
map picker's spoofed DELETE, 404/405) and bulk delete with a DB-unchanged
assertion on every refusal. The Blade list's permission-rendering case became
Vitest gating cases. New: `Trips/Trips/Index.test.ts`,
`StoreTripStatusDialog.test.ts` and `store-trips.spec.ts` (native confirms, the
dialog, notes on change, the map picker's modal delete);
`store-drivers.spec.ts` now expects the Inertia list and continues to the
Blade create form.

Current inventory: **43** backend Blade files, **22** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,048** under `/temp` and **436** under
`/temp/admin`: six replace six (counted from the route files; `route:list`
could not run). Blade editor: **8** fields in **8** views. Verification is
recorded in the trip list section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 25. Trip create and edit forms (2026-10-15)

The trip create and edit forms now use `Store/Admin/Trips/Trips/Create` and
`Edit` (`ResourceFormPage`, `wide`, `StoreAdminLayout`) with
`StoreTripFormFields`. Work is on `ccr-0ba426e7-qihjvq`, from `develop` at
`1f68656` (the PR #404 merge). The owner authorized this session's commit,
PR (#406), its merge into `develop` and `[skip ci]` on all of them, because the
checks run locally. So no PR CI, no PR or merge SAST and no `develop` deploy
ran. The ledger records what ran here (Vitest, vue-tsc, ESLint and Prettier,
against stand-in Wayfinder modules) and what did not (Pest, Pint, PHPStan,
Wayfinder generation, `route:list`, the build and Playwright: PHP 8.3.6 and no
`vendor/` in the session, as for #404, whose PHP checks are therefore still
outstanding too).

**Routes and gates: names and permissions kept.** Four routes moved from root
`Trip\TripController` to `Store\Admin\TripController`:

- `trips.create` (GET `/trips/create`) and `trips.store` (POST
  `/trips/store`, URI kept): `add_trip`.
- `trips.edit` (GET `/trips/{trip}/edit`; was `/trips/edit/{id}`) and
  `trips.update` (PUT `/trips/{trip}`; was POST `/trips/update` with `id`):
  `edit_trip`. A missing bound trip is a 404; a POST to the old update URI
  is now a 405 (the PUT owns the pattern) and the old edit URI a 404.

No retained Blade screen posted to the old routes, so none stays. The lookups
`trips.available-vehicles` and `trips.user-coordinates` are unchanged JSON
routes on `Api\V2\Trip\DriverController` (the driver API is a separate
consumer); the form calls the first through its Wayfinder action with the same
`date` and `excluded_trip_id` parameters as the Blade script. The root
controller keeps the checkpoints page and its actions, the map picker and its
writer, the trip orders and the CMR download, and stays in both analysis
exclusion lists.

**Writers: behaviour kept.** `store` and `update` are the legacy bodies,
moved:

- **Departure ID 9:** a new trip still starts with the departure checkpoint
  (store user 9), refused with a `departure` field error when that user is
  missing.
- **Trucks:** `Concerns\ReservesTripTrucks` is reused unchanged (only its
  docblock names the new callers): an open trip elsewhere or an unavailable
  truck is a `truck_id` error, a closed trip does not reserve its truck again,
  and a truck is freed only when no other open trip holds it, the trip's old
  truck included (§24's rule).
- **Checkpoints:** a stored checkpoint keeps its identity, order or customer,
  coordinates, status, arrival and attachments, and takes only its new
  position and notes; rows left out are deleted with one query (no `deleting`
  hook) and new rows are added. The shortest-path sort, the driver
  notification and the redirect to the trip's Blade checkpoints page
  (`trips.show`, reached from Inertia through `HandleStoreBladePages`'s 409)
  are unchanged.
- **Shared steps:** `createTrip()` (now given the validated array),
  `formatCheckpointsArray()`, `applyDefaultFirstCheckpoint()`,
  `hasValidCoordinates()` and `sortBasedOnShortestPath()` moved unchanged to
  `Concerns\WritesTripCheckpoints`, used by both controllers (the map picker's
  writer still needs them).

**Validation.** `StoreTripRequest` and `UpdateTripRequest` share
`Concerns\ValidatesStoreTrip` (driver, truck and due date, notes at most 255,
checkpoint notes at most 1000). Each row's reference is now a
`Rule::exists` on the table its type names (`orders` for a delivery, `users`
otherwise), so an unknown driver, truck, order or customer is a 422 field
error. The update request takes the bound trip instead of `id`; a stored row
must belong to it, appear once and keep its type and reference, and the create
form refuses any stored id.

**Deliberate changes.**

- **Stored checkpoints show as text.** Their order or customer cannot change
  (the server refused it already), so the edit form shows the order code and
  customer, or the customer, and posts the reference hidden. The Blade select
  offered only orders out for delivery, so a trip holding an order since
  delivered could not be saved; it now can.
- **Order of stops** changes with Move up and Move down buttons instead of
  SortableJS drag handles (loaded from jsDelivr `@latest`); no dependency was
  added. The shortest-path sort still reorders on save when routing answers.
- **No map on the forms.** The Blade forms embedded the Leaflet checkpoint map
  (`inc/map-scripts`); the map dependency's approval is unverified (§5), so
  the Inertia forms have none. `inc/map-scripts.blade.php` stays, now with no
  Blade includer, as the owner kept the map partials out of scope;
  `storeTripBladeMap.test.ts` still loads it.
- **Native constraints mirror the rules.** Due date (`type="date"`, required),
  truck (required), notes (`maxlength` 255), each new row's reference
  (required) and checkpoint notes (`maxlength` 1000). A customer checkpoint's
  notes were `required` only in the Blade markup; the server never required
  them, so the field is optional. An empty checkpoint list is refused by the
  browser (a required marker with "Add at least one checkpoint.") as well as
  by the server.
- **Vehicles.** A driver or truck no longer free on a newly chosen date is
  cleared; the Blade script re-selected only the stored value. The date,
  driver and truck hints are kept. Errors show under their fields instead of
  a list above the form.
- **Choices.** Orders out for delivery, newest code first (kept), labelled
  `#code customer`; store users who are not representatives, now by name
  ascending (the Blade lists ran by name descending), sent once as page props
  with only their id, code or name.
- **Links.** The list's Create trip and Edit, and the sidebar's Create trip,
  are Inertia visits (`storeAdminMenu.ts` and its test); Create trip from the
  map and Orders stay Blade. `admin_sidenav.blade.php` links by name and needs
  no change.

**Consumers traced and files deleted (`git rm`).** Traced with `rg` over
`resources/views`, `app`, `routes`, `public/assets/js`, `resources/js`,
`config` and `tests`.

- `trips/create.blade.php` and `trips/edit.blade.php`: rendered only by the
  removed root `create`/`edit`.
- `trips/components/checkpoint-template.blade.php` and
  `trips/components/checkpoint.blade.php`: included only by those two forms.
- Kept: `trips/inc/scripts.blade.php` (still included by
  `create-from-map.blade.php`), `inc/map-popups` (checkpoints page, map picker,
  `inc/map-scripts`), `inc/map-scripts` (above),
  `update-checkpoint-status-modal` (checkpoints page).

**Tests.** `TripControllerTest.php`: the update cases now use the bound PUT;
a `trip forms` block adds the create and edit payloads (with noise rows), the
redirects, refused callers, the per-permission matrix, a validation dataset
run against both writers with a DB-unchanged assertion, the stored-id
refusal on create, truck release with another open trip, and reorder, keep,
add and drop on update. `LegacyAdminOwnershipTest`'s last three rows (trip
create, store and update) moved into that matrix, so the emptied file was
removed; `LegacyShopRouteAccessTest`'s docblock no longer names it. No gate
changed, so `LegacyAdminPermissionsTest` is unchanged. New: `Create.test.ts`,
`Edit.test.ts`, `StoreTripFormFields.test.ts` (full mounts) and a
`store-trips.spec.ts` case (the vehicle lookup on a date, the browser's
empty-list refusal, save to the Blade checkpoints page, edit with a reorder);
`Index.test.ts` and `store-drivers.spec.ts` now expect the Inertia form.

Current inventory: **39** backend Blade files, **20** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,048** under `/temp` and **436** under
`/temp/admin`: four replace four (counted from the route files; `route:list`
could not run). Blade editor: **8** fields in **8** views. Verification is
recorded in the trip form section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 26. Trip orders list (2026-10-15)

The trip orders screen now uses `Store/Admin/Trips/Orders/Index`
(`StoreAdminLayout`, `DataTable`, `Pagination`). Work is on
`ccr-23c5d0cb-ziq8z5`, from `develop` at `78c9c3a` (the PR #413 merge). The
owner authorized this session's commits, PR (#419), its merge into
`develop` and `[skip ci]` on all of them, because the checks run locally. So
no PR CI, no PR or merge SAST and no `develop` deploy ran. Unlike #404 and
#406, this session had PHP 8.5.11, `vendor/`, the dev tools, generated
Wayfinder files and the Vite build: Pest, Pint, PHPStan, `route:list`, the
build and Playwright ran here, for this change and for the checks #404 and
#406 had left outstanding (the ledger records each).

**Routes and gates: names and permissions kept.** Four routes moved from root
`Trip\TripController` to `Store\Admin\TripOrderController` (`HasMiddleware`):

- `trips.orders` (GET `/trips/orders`): `view_all_trips`;
  `FilterTripOrdersRequest` (`per_page`, `search`, `delivery_status`, `from`,
  `to`).
- `trips.orders.bulk-change-delivery-status` (POST, URI kept),
  `trips.orders.update_invoice_number` (PUT `/trips/orders/{order}/invoice-number`;
  was POST `/trips/orders/update-invoice-number` with `order_id`) and
  `trips.orders.update_order_status_to_delivered` (PUT
  `/trips/orders/{order}/delivered`; was POST `/trips/orders/update-order-status`
  with `order_id`): `edit_trip`. A missing bound order is a 404; the two old
  URIs are now 404s.

Every write returns to the list with its query (`redirect()->route()`, no
`back()`). The root controller keeps the checkpoints page and its actions,
the map picker and its writer and the CMR download, and stays in both
analysis exclusion lists. No gate changed, so `LegacyAdminPermissionsTest`
and `LegacyStoreStateChangingRoutesTest` needed no change (both ran and
pass: the permission sweep accepts the 404 a bound route gives for an
unknown id before its permission check, and no route here answers GET with
a write).

**Behaviour kept.**

- **Statuses shown:** every order except pending and cancelled ones.
- **Bulk transition:** each selected order takes confirmed or ready for
  delivery, whatever it had, saved per order in one transaction.
- **Invoice number:** stored as typed (an empty field clears it) and the
  order goes out for delivery (`on_delivery`) whatever its status, saved on
  change as before.
- **Mark as delivered:** sets `delivered`.
- **Order totals:** the stored grand total through `single_price()`; nothing
  recomputes tax or totals (the order tax hold, §5, is untouched).
- **Columns:** every Blade column, including the latest checkpoint's trip,
  driver, truck and photos (upload ids resolved in one query, as the truck
  list does) and the Print CMR link (Blade redirect, full page). The Edit link
  keeps the Blade condition (not a representative's order, unpaid, no
  commission, not delivered or cancelled) under `edit_order`.

**Deliberate changes.**

- **Search** is grouped inside the list's statuses: code, invoice number,
  shipping address, or the customer (for a representative's order, the
  customer it was placed for) by name, phone or accounting id. The Blade
  query called `orWhereHas('customer')`, an accessor rather than a relation,
  so every search answered 500; its ungrouped `orWhere`s would also have
  matched pending and cancelled orders.
- **Filters** live in the query string: the delivery status filter (the
  Blade list's four choices plus All) no longer sticks in the session, and
  the date range is two `type="date"` fields (`from`/`to`, `Y-m-d`) instead
  of the `d-m-Y to d-m-Y` picker. The list pages 20 at a time with the
  per-page selector (was 15), newest first then by id.
- **Validation:** the bulk action takes 1–100 distinct existing orders and
  one of its two statuses, the invoice number at most 255 characters (the
  column); anything else is a 422 field error with nothing written. The
  Blade handlers ignored an empty selection and saved an unknown status
  unchanged.
- **Controls are gated by the route's permission:** the bulk status buttons,
  the invoice field and Mark as delivered need `edit_trip` (the Blade list
  showed the bulk menu under `delete_order` and the delivered button to
  everyone); Delete selection keeps `delete_order` and posts to
  `bulk-order-delete` with `return_to=trip_orders`, which now returns to this
  list instead of the all-orders list. Status changes and deletes ask with
  a native confirmation (`confirmDeliveryStatus`, `confirmDelete`); the Blade
  delivered button, titled "Change status", changed the order on one click.
  It is not offered for an order already delivered.
- **Removed dead code:** the Blade page's manager-notes modal was never
  opened (`openUpdateModal()` had no caller), so the page shows the notes as
  text; they stay editable on the order detail page.
- **Display.** Delivery dates show as `YYYY-MM-DD` (Blade `d-m-Y`), statuses
  use the order module's translated labels, a missing upload is left out
  (Blade showed the placeholder image), the address links to Google Maps in
  a new tab with `rel="noopener noreferrer"`, and customer, driver and truck
  link to their Inertia edit pages for viewers with `edit_customer`,
  `edit_driver` and `edit_truck` (pages.md). The trip code opens the Blade
  checkpoints page in full.

**Map picker.** Its order popup's Mark as delivered (`create-from-map.blade.php`)
now posts JSON `{ _method: 'PUT', return_to: 'map' }` to the bound route,
built from `route(..., ['order' => '__order__'])` with the popup's order id,
and reloads on any successful response: the 302 to `trips.create-from-map`
is followed as a GET. The popup button stays under `edit_trip`.

**Consumers traced and file deleted (`git rm`).** Traced with `rg` over
`resources/views`, `app`, `routes`, `public/assets/js`, `resources/js`,
`config` and `tests`.

- `trips/orders.blade.php`: rendered only by the removed root `all_orders`.
- `trips.orders.update_order_status_to_delivered`: also the map picker
  (above); the other three routes had no consumer outside the Blade list.
  `admin_sidenav.blade.php` links `trips.orders` by name and needs no change.
- Kept: `shop.modals.delete_modal` (other consumers),
  `checkpoints/index`, `components/update-checkpoint-status-modal`,
  `create-from-map`, `inc/map-popups`, `inc/map-scripts`, `inc/scripts` and
  the CMR download.

**Menu.** Orders under Trips is an Inertia entry (`storeAdminMenu.ts` and its
test); Create trip from the map stays Blade.

**Tests.** New `TripOrderControllerTest.php`: the full row payload for an
order with a trip and photos and one without (with an older checkpoint and an
unrelated upload as noise), the hidden statuses, every filter and the search
branches with noise rows, the session filter no longer sticking, pagination
and `per_page`, refused callers on the list and on every write with a
DB-unchanged assertion, the per-permission matrix, each write's behaviour and
redirect, validation datasets, 404s and the gone URIs, the map picker's JSON
spoofed PUT and its script, and `bulk-order-delete`'s `return_to`. No
`Legacy*Test` case covered this screen, so none moved. Vitest: a full mount of
`Trips/Orders/Index.test.ts`, `confirmDeliveryStatus` in `confirm.test.ts`,
the menu, and a `storeTripBladeMap.test.ts` case for the popup's request. New
`store-trip-orders.spec.ts`: the invoice saved on change, the native confirms
for delivered and the bulk status, and the map picker's popup request and
reload (with a stand-in for the unpkg Leaflet, which this sandbox cannot
reach and without which the picker's script stops before its click handler).

**Driver notification fix.** In the same session, ahead of this step, the
trip update writer (§25) compares the stored and posted driver as integers,
so saving a trip with its driver unchanged no longer notifies the driver
(the ledger's #406 "known limit"); `TripControllerTest` covers unchanged,
changed, removed and no-driver saves.

Current inventory: **38** backend Blade files, **19** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,048** under `/temp` and **436** under
`/temp/admin`: four replace four (from `route:list`). Blade editor: **8**
fields in **8** views. Verification is recorded in the trip orders section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 27. Trip detail and checkpoints (2026-10-16)

The trip checkpoints page (`trips.show`) now uses
`Store/Admin/Trips/Trips/Show` (`StoreAdminLayout`, `DataTable`). Work is on
`claude/store-backoffice-trip-checkpoints-2ga29g`, from `develop` at `9eb862a`
(the PR #420 merge). The owner authorized this session's commits, PR (#421),
its merge into `develop` and `[skip ci]` on all of them, because the checks run
locally. So no PR CI, no PR or merge SAST and no `develop` deploy ran. The
session had PHP 8.5.11, `vendor/` with the dev tools, generated Wayfinder
files and the font-less Vite build; the ledger records what ran.

**Routes and gates: names and permissions kept.** Four routes moved from root
`Trip\TripController` to `Store\Admin\TripCheckpointController`
(`HasMiddleware`):

- `trips.show` (GET `/trips/show/{trip}`, URI kept, now bound) and
  `checkpoints.print-cmr` (GET `/trips/print-cmr/{checkpoint}`, URI kept,
  now bound): `view_all_trips`.
- `checkpoints.update-notes` (PUT `/trips/checkpoints/{checkpoint}/notes`; was
  POST `/trips/update-checkpoint-notes` with `checkpoint_id`) and
  `checkpoints.update-status` (PUT `/trips/checkpoints/{checkpoint}/status`;
  was POST `/trips/update-checkpoint-status` with `id`): `edit_trip`.

A missing trip or checkpoint is a 404; a POST to either old write URI is a 405
(the trip update PUT owns the one-segment pattern). Both writes redirect to the
trip detail with the request's query instead of answering JSON `{success}` (the
notes) or the bare `1` (the status). No other caller read those answers: the
retained map picker only links `trips.show` by name (its truck cards) and
needed no change. The root controller keeps only the map picker and its
writer and stays in both analysis exclusion lists. No gate changed, so
`LegacyAdminPermissionsTest` and `LegacyStoreStateChangingRoutesTest` needed no
change (both ran and pass).

**Behaviour kept.**

- **Status:** the legacy transition in one transaction: the checkpoint is
  locked (`lockForUpdate`), takes any `CheckpointStatus` value, and a delivered
  checkpoint keeps its first `arrived_at`. The trip's own status flow (§24) is
  untouched: a checkpoint status never changes the trip.
- **CMR download:** still a GET that sends the browser to the uploaded file.
- **Columns:** every Blade column: customer (for an order, the customer it was
  placed for), representative, the customer's stored shipping address, status,
  arrival, estimated time, notes, phone, product count, company, amount
  (`single_price()` of the stored grand total), invoice number, photos and the
  CMR link. Stops keep their stored order (`sort_order_in_trip`, then id).

**Deliberate changes.**

- **No map.** The Blade page drew a Leaflet map (unpkg) with an OSRM route
  fetched on every page load. The map dependency's approval is unverified (§5),
  so the Inertia page shows the stops as an ordered table and the server no
  longer calls the routing service on this page. The root
  `getCheckpointsMapData()`/`checkpointMapPoints()` had no other caller and were
  removed with the two Pest cases and two Vitest cases that tested only them.
  `inc/map-popups` stays (map picker and `inc/map-scripts`).
- **Notes are validated** with the trip form's checkpoint notes rule
  (`ValidatesStoreTrip`: nullable string, at most 1000) through
  `UpdateCheckpointNotesRequest`; the Blade handler saved any value for any id.
  They save on change; "Notes saved." shows under the row instead of a toast.
- **Status** changes inline from a select, after a native confirmation
  (`confirmDeliveryStatus`, "Mark stop N as …?"); a refused confirmation or a
  server error puts the select back. The Blade modal saved without asking.
- **CMR safety.** The download resolves the first upload id in `cmr_file` and
  redirects only to a managed local file (`uploads/all/…`) or an HTTP(S) URL,
  the upload library's rule (`UploadResource`, §2.4). A checkpoint with no
  file, an unknown upload, a script link or a path outside the uploads returns
  to its trip detail with a `cmr` field error (`messages.trip_cmr_unavailable`)
  instead of `back()` with a session flash; the legacy helper sent the
  placeholder image for an unknown upload.
- **Display.** A trip header (status, due date, driver, truck, start and close
  times, distance, duration, notes) replaces the "checkpoints | driver" title;
  the stop column uses the §25 text (`#code customer`, or the customer) through
  the shared `lib/storeTrip.ts` `tripCheckpointLabel()`, which the trip form now
  uses too. Arrival uses `formatDateTime` (Blade: server-time `Y-m-d H:i`); the
  estimated time is the stored seconds through `formatDuration` (Blade: Carbon's
  English short span), or "-" when none; a missing photo upload is left out.
  Links follow pages.md: order (`view_order_details`), customer
  (`edit_customer`), driver and truck (`edit_driver`, `edit_truck`), and Edit
  trip (`edit_trip`, Inertia).
- **Printing.** The Print button calls `window.print()`; `StoreAdminLayout`'s
  sidebar and mobile bar carry `print:hidden`, so a printed store page shows
  only its content (the Blade page hid everything but its card).
- **Links to the detail** from the trip lists, the trucks list and the trip
  orders list are Inertia visits now (the trucks list through `TableLink`);
  the trip forms' store and update redirect there as before.

**Consumers traced and files deleted (`git rm`).** Traced with `rg` over
`resources/views`, `app`, `routes`, `public/assets/js`, `resources/js`,
`config` and `tests`.

- `trips/checkpoints/index.blade.php`: rendered only by the removed root
  `show`.
- `trips/components/update-checkpoint-status-modal.blade.php`: included only by
  that page; the only Blade poster of `checkpoints.update-status`.
- `checkpoints.update-notes`: posted only by that page. `trips.show`: also the
  map picker's truck cards (by name). `checkpoints.print-cmr`: also the trip
  orders list (Wayfinder action moved to the new controller).
- Kept: `create-from-map`, `inc/map-popups`, `inc/map-scripts`, `inc/scripts`
  and `shop.modals.delete_modal` (other consumers). `admin_sidenav.blade.php`
  has no entry for the detail; `storeAdminMenu.ts` needed no change (no menu
  entry points at the detail).

**Tests.** New `TripCheckpointControllerTest.php`: the full payload (a
representative's order with photos, a customer stop, another trip's checkpoint,
an unrelated upload and another order's lines as noise), an empty trip and a
deleted order, 404s, refused callers on every route with a DB-unchanged
assertion on the writes, the per-permission matrix, notes (save, clear,
validation, old URI), status (every value, first arrival kept, validation, old
URI) and the CMR hit (HTTPS and managed local) and miss dataset.
`TripControllerTest`'s three checkpoint status cases, its empty-detail case and
the trips.show refusal moved there; its Blade rendering case became Vitest
gating cases. Vitest: a full mount of `Trips/Trips/Show.test.ts`,
`lib/storeTrip.test.ts`, and the trip list, trucks and trip orders tests
expecting Inertia links. Playwright: new `store-trip-detail.spec.ts` (native
confirm dismissed and accepted, notes on change, print media hiding the
sidebar, the CMR miss banner); `store-trips.spec.ts` now lands on the Inertia
detail after saving a trip.

Current inventory: **36** backend Blade files, **18** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,048** under `/temp` and **436** under
`/temp/admin`: four replace four (from `route:list`). Blade editor: **8**
fields in **8** views. Verification is recorded in the trip detail section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 28. Legacy reviews screen retirement (2026-10-17)

The Blade product reviews list (`shop.backend.product.reviews.index`, served by
root `ReviewController::index` at `reviews.index`) is gone. The Inertia list
`admin.reviews.index` (`Store/Admin/Product/Reviews/Index`, §6) has been the
working screen since the reviews migration; the Blade list was only still
reachable through the Blade sidebar. Work is on
`claude/store-backoffice-reviews-step-28-lqx4cm`, from `develop` at `fb04f56`
(the PR #421 merge). The owner authorized this session's commits, PR (#422),
its merge into `develop` and `[skip ci]` on all of them, because the checks run locally.
So no PR CI, no PR or merge SAST and no `develop` deploy ran.

**Routes and gates: name and permission kept.**

- `reviews.index` (GET `/temp/reviews`, `view_product_reviews`) is kept as a
  redirect: `Store\Admin\ReviewController::redirectLegacyIndex` answers a 302
  to `admin.reviews.index` carrying the whole query string (`rating`,
  `per_page`, `page`), so old bookmarks and the one-line habit of typing
  `/temp/reviews` still work. It sits in the same `auth` group of
  `routes/shop-web.php` as before, ahead of
  `Route::resource('/reviews', ...)->except('index')`, and the Store
  controller's `permission:view_product_reviews` middleware now lists it
  beside `index`.
- The refused answers are those of the legacy group, not of the store admin
  group: a guest goes to the shop login, and a customer or a staff member
  without the permission gets 403 (the admin list itself answers a customer
  with 404). No gate was added or removed, so `LegacyAdminPermissionsTest` and
  the route ratchets needed no change (they ran and pass).
- The root `ReviewController` stays for customers: `store`
  (`reviews.store`, `LegacyReviewTest`), `product_review_modal` and the empty
  resource stubs. It loses `index` and, with it, its `HasMiddleware`
  declaration: the only route that middleware gated was `index`. The rest of
  the controller is untouched, and it stays in both analysis exclusion lists.

**Consumers traced and files deleted (`git rm`).** Traced with `rg` over `app`,
`routes`, `resources`, `tests`, `public/assets/js`, `config` and `lang`.

- `product/reviews/index.blade.php`: rendered only by the removed root `index`.
  Its form already posted to `admin.reviews.index` and its switches to
  `reviews.published`.
- `reviews.index`: linked only by `inc/admin_sidenav.blade.php`, now pointing at
  `admin.reviews.index` (the Inertia `storeAdminMenu.ts` already did), and
  asserted by `ReviewControllerTest`. `api.reviews.index`, `reviews` in the
  seller routes and the customer `reviews.store` form are separate.
- No `public/assets/js`, `config`, `lang` or Playwright reference.

**Tests.** `ReviewControllerTest`: the case that rendered the Blade page now
asserts the redirect (plain, and with `rating`, `per_page` and `page` kept); a
dataset case refuses a guest (login), a customer and a staff member without the
permission, and never redirects them to the list; a third case pins
`reviews.index` to the redirect action, `reviews.store` as still registered and
the view as gone.

Current inventory: **35** backend Blade files, **17** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,048** under `/temp` and **436** under
`/temp/admin` (`reviews.index` was a resource route and is now an explicit one;
from `route:list`). Blade editor: **8** fields in **8** views. Verification is
recorded in the reviews retirement section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 29. Server status page deletion (2026-10-17)

The System → Server status page is gone, on the owner's decision. It was a
`Route::view('/system/server-status', 'shop.backend.system.server_status')`
named `system_server` (middleware `RaizerSuperAdmin`) with no controller. Its
checks targeted PHP 7.3/7.4 and MySQL 5.6, so it showed a red cross on every
working PHP 8.5 install, and nothing else used it. Work is on
`ccr-ff665fd6-s90gn3`, from `develop` at `ead3b91` (the PR #422 merge). The
owner authorized this session's commits, one PR into `develop`, its merge and
`[skip ci]` on all of them, because the checks run locally. So no PR CI, no PR
or merge SAST and no `develop` deploy ran. The PR is #423.

**Removed.**

- The route `system_server` (`routes/shop-admin.php`); `GET
  /temp/admin/system/server-status` now answers 404, even for a super admin.
- `system/server_status.blade.php` (`git rm`); the `system/` directory is gone.
- The Blade sidebar's whole System group in `inc/admin_sidenav.blade.php`: its
  only child was Server status. `storeAdminMenu.ts` never had an entry; its
  doc comment now says the page was deleted.

**Traced and kept.** `rg` over `app`, `routes`, `resources`, `config`,
`public/assets/js`, `tests`, `lang` and `.ai` found only the files above plus
documentation. No Vue, Wayfinder or search-index reference.

- `RaizerSuperAdmin` (alias in `bootstrap/app.php`) is still used by
  `cache.clear` (`routes/shop-admin.php`), the business-settings commission
  activation route and `Store\Admin\SettingsController`; kept.
- The `server_status` permission name was used only by the removed sidebar
  gates. It is not seeded or migrated in this repository (it may exist as a
  production row); nothing was changed, and a data cleanup needs operational
  review. The `System` and `Server status` translation strings are shared
  `translate()` keys, left alone.

**Tests.** `StoreBackOfficeShellTest` no longer lists the view or pins the
`Route::view` line; it asserts the view is absent and `routes/shop-admin.php`
no longer mentions `server_status`. A new case asserts `system_server` is
unregistered, no route URI contains `server-status`, `system/` is gone and a
super admin gets a 404. The Legacy ratchets (`LegacyAdminPermissionsTest`,
`LegacyStoreStateChangingRoutesTest`, `LegacyShopRouteAccessTest`,
`LegacyTempRoutesReachableTest`) did not pin the route and needed no change.

Current inventory: **34** backend Blade files, **17** direct literal consumers
of `shop.backend.layouts.app`, **3** of `shop.backend.layouts.layout`.
Registrations fall to **1,047** under `/temp` and **435** under `/temp/admin`
(from `route:list`). Blade editor: **8** fields in **8** views. Verification is
recorded in the server status section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.

## 30. Trip map picker (2026-10-17)

The trip map picker (`trips.create-from-map`) now uses
`Store/Admin/Trips/Trips/CreateFromMap` (`StoreAdminLayout`) with the new
`components/StoreTripMap.vue`, on Leaflet installed from npm. Work is on
`ccr-a8851ce2-oefr1s`, from `develop` at `2e3d8cf` (the PR #423 merge). The
owner authorized this session's commits, one PR into `develop` (#427), its
merge and `[skip ci]` on all of them, because the checks run locally. So no PR
CI, no PR or merge SAST and no `develop` deploy ran. The ledger records what
ran.

**Owner decisions (2026-10-17), recorded in §5.** Leaflet and Chart.js are
owner-approved frontend dependencies (npm through Vite, no CDN). Sellers and
classified products will be converted, not deleted, in later steps.

**Dependency.** `leaflet` 1.9.4 (`dependencies`) and `@types/leaflet` 1.9.22
(`devDependencies`), pinned exactly at the versions the lockfile resolves;
`package-lock.json` adds those two and `@types/geojson` 7946.0.16 (required by
the types). No Composer change. The component imports Leaflet's ESM build
by name, its stylesheet (`leaflet/dist/leaflet.css`) and its marker images
through Vite, so they ship as bundled assets of the page chunk. `npm audit`
reports 6 findings, all in dev tooling (`braces`, `micromatch`, `fast-glob`,
`@vue/eslint-config-typescript`, `eslint-plugin-vue`,
`postcss-selector-parser`); they exist without Leaflet too and were left for
a separate change.

**CSP (§2.5).** Tiles come from OpenStreetMap, the provider the Blade maps
already used, at the tile server's current host
`https://tile.openstreetmap.org/{z}/{x}/{y}.png` (the Blade pages used the
`{s}.` subdomain form, which OpenStreetMap no longer recommends). Leaflet loads tiles as `<img>`, so
`config/secure-headers.php` gained exactly `img-src.allow:
['https://tile.openstreetmap.org']`. No other directive changed, and
`store.blade_csp` is untouched. The browser spec runs under the enforced
policy and reports no violation; without the entry, the browser blocked every
tile and the same spec failed (checked once in this session, then restored).

**Routes and gates: names, URIs and permission kept.** Both routes moved from
root `Trip\TripController` to `Store\Admin\TripMapController` (`HasMiddleware`,
`permission:add_trip`):

- `trips.create-from-map` (GET `/trips/create-from-map`): the page.
- `trips.store-from-map` (POST `/trips/store-from-map`): the writer, with
  `StoreMapTripRequest` (unchanged); a `trip_id` also needs `edit_trip`
  (`Gate::authorize`, a 403), as before.

Root `Trip\TripController` had nothing else left and is deleted, with its
`pint.json` and `phpstan.neon` entries (`StaticAnalysisExclusionsTest`) and
the `ControllerRedirectsTest` path exclusion; the `Trip/` directory is gone.
`routes/shop-admin.php` no longer aliases `Store\Admin\TripController`.
For PHPStan, `Truck::trips()`, `Trip::checkpoints()` and
`Checkpoint::relationable()` gained their relation return types (behaviour
unchanged; the three models stay in both exclusion lists). The page loads
the trucks' open trips, their drivers and checkpoints, and the checkpoints'
orders or users in a fixed number of queries (`loadOpenTrips()`). No gate
changed, so `LegacyAdminPermissionsTest` and the route ratchets needed no
change (they ran and pass).

**Writer: behaviour kept.** The legacy body, moved unchanged except for its
answer, in one transaction: without `trip_id` a new trip on the truck
(due today, no driver) starting with the departure checkpoint (store user 9;
a `departure` field error when missing); with `trip_id` the truck's open trip
gains the orders after its last stop. `Concerns\ReservesTripTrucks` and
`Concerns\WritesTripCheckpoints` are reused unchanged (only their docblocks
name the new caller): a truck another open trip holds is a `truck_id` error,
an order already on an open trip or no longer out for delivery a `markers`
error. The orders keep their posted order, then the shortest-path sort
reorders when routing answers; the driver is notified. `prepareCheckpoints`
moved with the writer (now `findOrFail` on the validated order).

**Deliberate changes.**

- **Answer.** The writer redirects to the picker (`trips.create-from-map`)
  instead of answering JSON `{success: true}`; a refusal is an Inertia field
  error shown in a banner, where the Blade page only logged a failed fetch.
- **Stops.** A marker click (or Enter or Space on the focused marker, which is
  a `role="button"` with the order's label) selects or drops an order; the
  selected orders show as an ordered list of stops with every popup detail
  (code and customer, customer ID, postal code, phone, address, Otajer ID) and
  the §25 Move up, Move down and Remove buttons, and are posted in that order.
  The Blade page posted them in click order with no way to reorder. Leaflet
  popups are gone; the selected marker takes a hue-shifted copy of the marker
  icon plus `aria-pressed`.
- **Address.** The order's stored shipping address (`address`), else the
  customer's, as the trip orders list shows it (§26); the Blade popup printed
  the raw JSON column.
- **Gates follow the routes.** Mark as delivered (`edit_trip`, as the Blade
  popup) now asks with `confirmDeliveryStatus`; Delete trip (`delete_trip`)
  asks with `confirmDelete` instead of the Blade modal; adding stops to a busy
  truck's trip is offered only with `edit_trip`, which its write needs (the
  Blade button was offered to everyone and failed). Assign buttons stay
  disabled until a stop is selected (the Blade page alerted).
- **Display.** Truck photos are the first upload id resolved through
  `TruckPhotoResource` (HTTP(S) only), else a truck icon (Blade: the
  placeholder image); a busy truck's name links to its latest open trip's
  Inertia detail, a stop's customer to the Inertia customer edit
  (`edit_customer`, pages.md); "Unknown customer" is translated (Blade printed
  Arabic text in every locale). Trucks are listed by id, trips latest first
  then by id, stops by position then id; the Blade page queried each truck's
  trips several times per card.
- **Map.** The container is `dir="ltr"` (map geometry does not mirror in
  Arabic) and `isolate`, so Leaflet's pane z-indexes stay under the layout's
  menu. Markers at the same spot are still spread east by 0.0011°.

**Consumers traced and files deleted (`git rm`).** Traced with `rg` over
`app`, `routes`, `resources`, `config`, `public/assets/js`, `tests` and `lang`.

- `trips/create-from-map.blade.php`: rendered only by the removed root
  `create_from_map`.
- `trips/inc/map-popups.blade.php`, `trips/inc/scripts.blade.php`: included
  only by the picker; `trips/inc/map-scripts.blade.php`: no includer since §25.
  `resources/views/shop/backend/trips/` is gone.
- `resources/js/testing/storeTripBladeMap.test.ts` tested only those three
  scripts and is removed with them; `StoreTripMap.test.ts` and
  `CreateFromMap.test.ts` cover the new map and page.
- Kept: `trips.user-coordinates` (its only UI consumer was `inc/map-scripts`;
  `LegacyDataExposureTest` pins it, and the driver API shares the handler),
  `public/assets/img/selected-marker.png` and `default_truck.png` (public
  assets, not removed here), the visit maps and `admin_sidenav.blade.php`
  (links the picker by name, no change).

**Menu.** Create trip from the map is an Inertia entry from
`TripMapController` (`storeAdminMenu.ts` and its test).

**Tests.** New `TripMapControllerTest.php`: the full payload (a
representative's order for another customer, unlocated, out-of-range, pending
and delivered orders as noise; free, plain, busy and idle trucks; a closed
trip, a deleted order's stop and an unrelated upload as noise), the empty
page, refused callers on both routes with a DB-unchanged assertion on the
write, the routes' names, action and permission with the root controller and
views gone, a new trip and an append (posted order, departure, truck, driver
notification), `edit_trip` for an append, an 11-case validation dataset and the
departure error with nothing written, GET on the writer (405), and the row
actions' DELETE/PUT with their 404s. `TripControllerTest`'s nine map cases moved
there; its reflection case now uses `Store\Admin\TripController`.
`TripOrderControllerTest`'s Blade-script and Blade-rendering cases became the
PUT-with-query case and Vitest gating. `HandleStoreBladePagesTest` pins the
picker's strict `script-src` and its exact `img-src`. Vitest: full mounts of
`StoreTripMap.test.ts` (Leaflet mocked with `vi.mock('leaflet')`, as
`CellMap3D.test.ts` mocks three.js) and `CreateFromMap.test.ts`. Playwright:
new `store-trip-map.spec.ts` with the real bundled Leaflet (pointer and
keyboard selection, reorder, assignment, native confirms for delete and
delivered, Arabic RTL with an LTR map, dark-mode contrast, enforced CSP); the
two Blade map cases in `store-trips.spec.ts` and `store-trip-orders.spec.ts`
(with their unpkg stand-in) moved there. The first browser run found a real
defect: Leaflet adds a marker only once the map has a view, so the markers'
`aria-pressed` was never set; the view is now set first, with a Vitest
ordering assertion.

Current inventory: **30** backend Blade files, **16** direct literal consumers
of `shop.backend.layouts.app` (counted as before, including
`StoreBackOfficeShellTest`), **3** of `shop.backend.layouts.layout`.
Registrations are unchanged at **1,047** under `/temp` and **435** under
`/temp/admin`: two replace two (from `route:list`). Blade editor: **8** fields
in **8** views. Verification is recorded in the trip map picker section of
[the reconciliation ledger](../store-backoffice-reconciliation-20261011.md).
No deployment or production-data mutation is authorized.


## 31. Representative visit details and day maps (2026-10-18)

Work on `codex/store-visit-details-step-31` from fetched `origin/develop`
`57e1e51f` (PR #438). PR #428 changed the pre-push default to attestation;
this run uses explicit `--full` before committing and never writes an
attestation trailer. The owner authorized one PR into `develop`, its merge,
and `[skip ci]` on every commit, the PR title and the merge commit.
[PR #440](https://github.com/arb-rajab/warehouse_management/pull/440) is the single PR; its number is recorded in a follow-up
`[skip ci]` commit before merging.
Model: GPT-6 Codex; the exact variant and configured effort are not exposed
to this session. The reconciliation ledger records verification and shipping.

**Controller and routes.** `show`, `show_all_visits_in_a_day`, `mapMarkers`
and `update_note` moved into the existing `Store\Admin\VisitController`.
The two GET names and paths stay; `{visit}` is bound on detail, `{user}`
on the day route. `ShowRepresentativeVisitsRequest` validates the bound
user id (query input cannot replace it) and optional `date` (`Y-m-d`). A
missing visit/user and a user without `is_rep` return 404. With no date the
day remains the first visit by id, else today. Visits stay scoped to that
representative and day, rank descending, with id ascending for tied ranks.
`VisitDetailResource` exposes only the displayed customer, representative,
order and note fields; missing related rows render safely.

Both GETs keep `view_representative_visits`. The retained POST `visits.note`
keeps its URI and `customer`, `verified`, `unbanned` middleware. The new PUT
`admin.rep.visits.note` at `/representatives/visits/{visit}/note` is the
permission-gated admin alias to the same writer, needed for the inline editor
without changing the customer-only perimeter. It uses the bound visit in
preference to a forged body id. Both use `UpdateRepresentativeVisitNoteRequest`;
only the current store actor's own visit is saved, with no Super Admin
bypass. A foreign visit remains unchanged. Both now redirect back instead of
answering `1`/`0`; a missing id is 404. The retained storefront customer
chooser, the only old UI caller, now posts a native CSRF form and no longer
interprets numeric AJAX responses. The mobile API is unchanged.

The root controller is empty and deleted; its Pint, PHPStan and redirect-test
path exclusions are removed. `ControllerRedirectsTest` exempts only this
writer's method from its back-redirect scan, because step 31 explicitly
requires a redirect back for these two consumers. No permission definitions
or grants, departure, tax or logout policy changed. No Legacy ratchet list
changed: the existing gates remain and the new alias has its read permission.

**Vue and maps.** `Show.vue` and `RepShow.vue` use `StoreAdminLayout`,
Wayfinder, English/Arabic `store.representatives.visits.*` labels, native
constraints matching the nullable note/date rules, RTL and dark styles.
The editor requires both `useStoreAccess().can('view_representative_visits')`
and server-provided ownership. Customer/order links follow their destination
permissions. Visit-list detail/day links are now Inertia Links. The sidebar
still targets the same Inertia visit index, so `storeAdminMenu.ts` is unchanged;
its test runs.

`StoreLeafletMap` extracts the npm Leaflet/tile/container lifecycle from
step 30's `StoreTripMap`, now reused by `StoreVisitMap`. The trip picker's
selection behaviour stays covered. Visit markers keep the controller's safe
coordinate checks, including zero and boundaries, one-based rank, red without
an order and blue with an order, customer-name text popups and the day polyline
in supplied order. Empty bounds keep a world view; one point uses zoom 14;
multiple points fit with 24px padding and maximum zoom 14. Marker colours use
darker red/blue for legible white ranks. The map stays LTR inside Arabic and
isolated under layout menus. Leaflet 1.9.4 and CSS come from npm through Vite.
The existing OpenStreetMap `img-src` allowance and all other CSP directives
are unchanged; no unpkg imports remain in these screens.

**Consumers and removals (`git rm`).** The root handler was the only renderer
of `show.blade.php` and `rep-show.blade.php`; each included `map.blade.php`.
Those are its only includers. The backend app shell includes only the shared
admin nav/sidenav and has no visit-map include. Searches of `app`, `routes`,
`resources`, `tests` and `public/assets/js` found no other map consumer or
visit-specific public map helper to remove. The three Blade files and the
old `storeVisitBladeMap.test.ts` are deleted. Protected shells and shared
public assets remain for their actual consumers.

**Tests and inventory.** The existing detail tests were converted to Inertia;
the web note cases moved out of `LegacyRepresentativePaymentLinksTest` and
`ReconstructedMigrationAuditTest`, leaving the API note cases in place.
Tests cover the full payload, ownership, no-op foreign writes with unchanged
DB attributes, validation, missing ids, refused callers, scoping, coordinate
edge cases and strict CSP. Vitest full-mounts both pages and all maps;
Playwright drives real npm Leaflet, safe popups, Inertia navigation, notes,
ranked markers/polyline, date filtering, RTL/LTR and dark contrast under
enforced CSP, with locally answered tile images. Verification results are
recorded in [the ledger](../store-backoffice-reconciliation-20261011.md#representative-visit-detail-migration-2026-10-18), including failed setup attempts and skipped
checks; none is inferred from a prior run.

Current inventory: **27** backend Blade files (30 minus these three), **14**
direct literal app-shell consumers counting `StoreBackOfficeShellTest`,
**3** actual layout-shell extending views. Route registrations: **1,048**
under `/temp`, **436** under `/temp/admin` (one new note alias). The retained
Blade editor inventory is unchanged from §30. No production-data mutation
or deployment was performed.


## 32. Google Map settings (2026-10-18)

Drafted by a Codex session on `codex/step-32-google-map` from `develop` at
`4cb3c44a` (PR #440), then verified, fixed and shipped from a Claude session
that had PHP 8.5.11, `vendor/` and `node_modules`. The owner authorized one PR
into `develop` (PR #442), its merge, `[skip ci]` on every commit, the PR title
and the merge message, and a follow-up commit recording the PR number. So no PR CI, PR
or merge SAST and no `develop` deploy ran; the local checks in the ledger are
the verification.

**Owner map decision.** The §17 hold is lifted for this screen: Leaflet from
npm with OpenStreetMap tiles replaces the admin Google script. The storefront's
Google map still follows `google_map`; its script partial and consumers are
unchanged. No dependency or CSP configuration change is needed or made.

**Backend.** GET `google-map.index` (`/temp/admin/google-map`) moves to
`Store\Admin\GoogleMapController::index`, rendering
`Store/Admin/Setup/GoogleMap/Index` with an exhaustive `settings` object:
`google_map` (boolean), `google_map_latitude` and `google_map_longtitude`
(stored strings, empty if absent). The existing spelling is preserved.
POST `google-map.update`, its URI, `google_map_setting`, typed request,
allowlist, transaction, cache invalidation and redirect remain unchanged.
Range rules already existed (-90..90 latitude, -180..180 longitude); this
step adds coverage of both sides and positive boundaries, with no new
validation behavior. Root `google_map()` and its middleware entry are removed;
`BusinessSettingsController` stays in Pint/PHPStan exclusions for the seller
commission/verification screens. No permission gate or ratchet changed.

**Vue.** `ResourceFormPage` uses `StoreAdminLayout`, Wayfinder and the module's
`StoreGoogleMapFormFields`. `StoreBooleanField` retains its enabled/disabled
select API; its label explicitly says it controls the storefront's Google map.
Enabling reveals the shared `StoreLeafletMap`, an accessible draggable marker,
click selection and two editable numeric fields with native min/max/step and
paired-required constraints. Manual edits move the marker; clicks/drags update
the inputs. Blank/invalid coordinates do not create a marker. The world view
makes an unset location selectable. Disabling omits coordinates from the POST,
preserving the stored location. The map stays LTR and isolated inside RTL.
English/Arabic keys are `store.googleMap.*`; the sidebar is an Inertia visit.

**Consumers/removal.** Searches of app/routes/resources/tests/public JS found
only the removed root action rendered the admin `google_map.blade.php`; it is
removed with `git rm`. `shop/frontend/partials/google_map.blade.php` stays:
its consumers include the frontend address modal, user profile, customer
dashboard and seller profile. Seller shop also reads the stored default
latitude. None of those files or any storefront behavior changed.

**Tests and verification.** Pest covers render, exhaustive enabled/disabled/
missing settings, route action/gate, existing write/refusal/cache/allowlist
behavior and expanded coordinate boundaries. The root middleware test drops
only the migrated action. Full-mounted Vitest covers the page shell, module
fields and real input synchronization with a mocked Leaflet geometry engine.
`store-google-map.spec.ts` drives real npm Leaflet clicks/drags/manual edits,
native range rejection, save/reload, disabled preservation, RTL/LTR and dark
contrast under enforced CSP, with tile images answered locally. Verification
found and fixed two defects in the draft: its `store.googleMap.*` strings sat
under `cells.palletActions.store` in both locale files (so the page showed raw
keys), and the spec's fixture mass-assigned `BusinessSetting`, whose `type` is
guarded. The spec also now waits for the map's pan to settle before dragging
the marker and switches back to English by its Arabic label. See
[the ledger](../store-backoffice-reconciliation-20261011.md#google-map-settings-step-32-2026-10-18)
for exactly which checks ran.

Inventory: **26** backend Blade files (27 minus this screen),
**13** direct literal app-shell consumers including the shell test, **3**
layout-shell extending views. Routes replace one GET with one GET; no route
is added or removed (`route:list` still shows 1,048 under `/temp` and 436
under `/temp/admin`).
No production data, CI, SAST or deployment was run.


## 33. Admin dashboard (2026-10-18)

Implemented on `codex/step-33-dashboard`, from fetched `origin/develop` at
`63e676f4` (PR #442). Model: GPT-6 Codex; the exact model variant and configured
reasoning-effort value are not exposed to this session. The owner authorized
Chart.js and one PR into develop and its merge only after the requested checks
could run, with `[skip ci]` in every commit, PR title and merge message.
Verification and shipping are recorded in the reconciliation ledger.

**Backend and gate.** `Store\Admin\DashboardController::index` replaces root
`AdminController::admin_dashboard`, keeping `shop.admin.dashboard`, GET
`/temp/admin` and `auth` + `admin`. `HasMiddleware` adds the explicit
`permission:admin_dashboard` gate requested for the whole response. The Legacy
read ratchet drops only this formerly ungated action. Scoped `cache.clear`
keeps its separate `RaizerSuperAdmin` route gate; its index-only permission
does not restrict cache clear. The root controller has no remaining consumer:
its obsolete, unrouted `clearCache` method is removed with the file and both
Pint/PHPStan exclusions. No storefront file changes.

The response contains typed stats, real numeric chart arrays, and
`DashboardProductResource` rows. Pie 1 preserves the legacy **all products vs
published products** series, not active vs inactive; pie 2 preserves
`from_api = 1` vs `from_api = 0`. Category sales sum every descendant product's
`num_of_sale`, and stock sums all its variant quantities, including unpublished
and seller products as before. Separate SQL aggregates avoid multiplying sales
by the stock-row count; category traversal is in memory with a cycle guard.
Equality is tested against the former `CategoryUtility`/product/stock loop with
multiple roots, nested children, variants, stockless and unrelated products.

Only category totals and visits are cached for 86,400 seconds, under the new
`store.admin.dashboard.graphs.v2` key. Old comma-string `cached_graph_data` is
never read. Category totals are keyed by root id, so current translated labels
cannot shift onto another root's totals; visits labels and values share a
snapshot. Missing `total_visits` yields empty arrays. Stats and Top 12 stay
fresh. Cache clear forgets the new key and the legacy key. The SMTP flag retains
`can('smtp_settings') && username == null && password == null`, including the
legacy loose comparison for empty strings.

Top products keep `filter_products`, published ordering by sales, existing
tax/discount/currency helpers and storefront URLs. Explicit selections and
scoped eager loads cover product translations (the §7 defect), taxes and
thumbnails, with no per-product image lookup. The grid uses escaped names,
prices and numeric ratings with plain storefront anchors. Products without
a storefront slug remain readable without an invented link (the same null-URL
contract as the product lists); browser verification found this in seeded WMS
products.

**Frontend and dependency.** `Store/Admin/Dashboard/Index.vue` uses
`StoreAdminLayout`, English/Arabic `store.dashboard.*` and an Inertia sidebar
entry. `StoreDashboardChart` bundles exactly pinned npm Chart.js 4.5.1 (§5),
registers only its used pieces, destroys charts/listeners on unmount and
rebuilds for data, locale and system dark-mode changes. Five labelled canvases
have visually hidden data-table fallbacks. Chart geometry stays LTR; Arabic
legend and tooltip text use RTL. Light/dark palettes have contrasting labels
and fills. No CSP directive or Composer dependency changes. The audit adds
zero findings; the same six pre-existing dev-tool findings remain (ledger).

**Consumers and inventory.** `git rm` removes `dashboard.blade.php` after
tracing its sole renderer and all action/menu/test imports. AIZ's chart helper
in `public/assets/js/aiz-core.js` remains because
`shop/seller/dashboard.blade.php` still calls it; shared vendor assets and
protected Blade shells remain. Current counts: **25** backend Blade files,
**12** literal app-shell consumer files including the shell test (11 actual
extending views), **3** layout-shell extending views, **1,048** `/temp` and
**436** `/temp/admin` registrations. One GET replaces one GET.

Pest covers render/full payload, unchanged refused-callers DB, SMTP branches,
24-hour cache expiry, aggregate equality, top-12 filtering/translations, route
and shell contracts. Vitest full-mounts the page/chart with Chart.js mocked;
Playwright exercises real bundled charts under enforced CSP, Inertia menu
visits, Arabic RTL/LTR geometry and dark label/fill contrast. Exact results,
failed attempts and skipped checks are in the
[step 33 ledger](../store-backoffice-reconciliation-20261011.md#admin-dashboard-step-33-2026-10-18),
not inferred from earlier steps. PR CI, PR/merge SAST and deploy do not run under `[skip ci]`.

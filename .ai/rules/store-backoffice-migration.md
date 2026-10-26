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
  - 'resources/js/components/StoreSeller*'
  - 'resources/js/components/StoreTripStatusDialog.vue,resources/js/components/StoreTripFormFields.vue,resources/js/components/StoreTripMap.vue,resources/js/components/StoreLeafletMap.vue,resources/js/components/StoreVisitMap.vue,resources/js/lib/storeTrip.ts'
  - 'app/Http/Controllers/Store/**,app/Http/Requests/Store/**,app/Http/Resources/Store/**'
  - 'app/Http/Middleware/HandleInertiaRequests.php,app/Http/Middleware/HandleStoreBladePages.php,app/Http/Middleware/SendSecureHeaders.php'
  - 'routes/shop-admin.php'
  - 'tests/Feature/Store/**,tests/e2e/store-*.spec.ts'
---

# Moving the store back office from Blade to Inertia

Seller catalogue follow-up: [digital validation and transactional persistence, plus seller row limits](#55-seller-digital-catalogue-and-remaining-seller-row-rules-pr-c-2026-10-23).

Reference-data follow-up: [legacy countries, states, currencies, languages and CMS pages as a migration](#46-legacy-reference-data-as-a-migration-2026-10-21).
Required-field follow-up: [Trips, Sales and Languages complete](#57-back-office-required-fields-phase-2b-batch-2b-trips-sales-and-languages-2026-10-24).
Forms follow-up: [required and optional form fields](#44-required-and-optional-form-fields-2026-10-21), then [the public address forms and the checkout address pick](#45-public-forms-phase-2a-address-forms-and-the-checkout-address-pick-2026-10-21), then [the longitude columns, seller registration and create customer](#47-public-forms-phase-2a-continued-the-longitude-columns-seller-registration-create-customer-2026-10-21).
Latest follow-up: [editor keyboard-selection readiness](#68-editor-keyboard-selection-readiness-2026-10-26).
Admin-shell retirement: [retire the unused admin shells](#39-retire-the-unused-admin-shells-2026-10-20).
Catalogue follow-up: [admin seller product list](#38-admin-seller-product-list-2026-10-20).
Classified-products follow-up: [admin classified products](#37-admin-classified-products-2026-10-19).
Seller-settings follow-up: [seller commission and verification form](#36-seller-commission-and-verification-form-2026-10-19).
Payout safety follow-up: [D1, D2, D3 and D5](#60-seller-payout-defects-d1-d2-d3-and-d5-2026-10-24).
Payment follow-up: [seller payments and withdrawals](#35-seller-payments-and-withdrawals-2026-10-19).
Seller-account follow-up: [seller list and accounts](#34-seller-list-and-accounts-2026-10-19).
Dashboard follow-up: [admin dashboard](#33-admin-dashboard-2026-10-18).
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
historical evidence; §10 records reconciliation results and §§11–39 record
subsequent module verification and limits.

## 1. Decision and scope

The current reconciliation request authorizes integration of independently developed
store back-office modules into Inertia v3 + Vue 3 + TypeScript. Historical
checked-in approval claims are context, not independently verified evidence for
new dependencies, feature usage, removal or changes in business behavior.

- **In scope:** `resources/views/shop/backend/**`, served under `/temp/admin`.
- **Not in scope:** the storefront, the seller area, the store's auth pages,
  its emails and its PDFs. They stay Blade.

The back-office Blade to Inertia conversion is complete (§39). The storefront,
seller panel, auth pages, emails and PDFs remain outside it. They share `/temp`
with the Inertia back office through the coexistence mechanism (§2.6).

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

**Gates.** The menu data is `lib/storeAdminMenu.ts`, now the only back-office
sidebar. It preserves the established gates of the retired Blade sidebar (§39):

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
   - Delete the root-namespace legacy controller once its last consumer moves.
     Through seller stage 34, root `SellerController::payment_modal` retained
     its exclusions while payout administration remained Blade; §35 removes it.
   - Since §35 the three root seller/payment/withdrawal controllers are removed.
     Root `CommissionController` remains only for `calculateCommission`, called
     by `Helpers.php` checkout; its exclusion pair stays.
   - When deleted, remove it from both `pint.json` and `phpstan.neon` in the same change
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
   - Mark every field the request requires (`required` on the field component,
     or `<RequiredMark />` in a hand-written label) and label optional ones
     "(optional)" only where most fields are required; `ResourceFormPage`
     shows the legend (.ai/rules/form-required-fields.md, §44).
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
      - the `confirmDelete` wiring;
      - the required marks match the FormRequest: marked labels equal the
        `required` controls, which equal the request's required fields, and
        the legend shows (`StoreStaffFormFields.test.ts`). Keep
        `RequiredFieldsRatchetTest` green; never add to its baseline for a new
        form.
    - Add fixtures to `testing/factories.ts`.
11. **Playwright**
    - Write a `tests/e2e/store-*.spec.ts` for browser-only behaviour: native
      confirms, RTL arrows, dark-mode contrast.
    - `loginAsStoreAdmin()` signs in as the seeded
      `store-admin@example.com` / `password` (`StoreUserSeeder`, which refuses
      production since §41; Playwright seeds with `APP_ENV=testing`).
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


## 4. Integrated status (2026-10-20)

This reflects integrated code and the carrier, customer-package, category,
brand, digital-product, in-house/all-products list, general-settings,
blog-editor, newsletter/ticket-detail, website-settings, digital-product
form, physical product create and edit form, trip list, trip form, trip
orders, trip detail, legacy reviews retirement, server status deletion and trip map
picker, representative visit detail, Google Map settings and dashboard
follow-ups in §§11–39, including all three seller administration stages, admin classified products and the admin seller product list. The original
reconciliation was based on `8e3f82b60b2b4dc951b97ccfed985a75e9e6be69` and
merged through PR #377. Carrier administration was then implemented separately
from latest `origin/develop` at `018dd1802c0b2fc99ffbf0e097594fe0f0f3dfef`.
Verification below distinguishes the original reconciliation from each follow-up.
The back-office Blade to Inertia conversion is complete; §39 retires its dead shells.

| Module/subset | State | Actual behavior / remaining work |
| --- | --- | --- |
| Staff and roles | Complete pilot | Existing six Inertia pages retained; action gates unchanged |
| Reference/shipping/tax/currency | Complete | Canonical `35ae3133` controllers/forms/routes; duplicate `e5523338` implementation superseded; Setup required-field follow-up complete (§56), with conditional marks and retained scanner limitations |
| Carrier administration | Complete | List/create/edit and CRUD/status use Inertia; existing `manage_carriers` gate, tariff contract and public/API consumers preserved (§11); required tariff/group marks follow free shipping (§56) |
| Language/settings | Complete | Language CRUD and translation-selector validation separated; dedicated settings endpoints with reserved-key protection; general settings use Inertia with a typed writer and a config-managed timezone (§17); the generic `business_settings.update` writer is retired (§20); the Google Map settings GET uses Inertia and the approved Leaflet picker (§32); language required-field legends verified (§57), nullable translation values remain unmarked |
| Reports/notifications | Complete | Inertia report/admin notification routes; public/customer/seller notification consumers remain; required customer selector mark verified (§58) |
| Uploads/profile | Complete | Canonical `944e2e1e` backend/picker; per-action authorization and authenticated-user profile |
| Customers/representatives | Complete | Customers, addresses, representatives/packages/payments, customer package administration (§12), visit list/detail/day maps (§31) and the admin classified-products list/publish/delete (§37) use Inertia; customer-facing classified pages and the mobile API remain separate; required fields/legends verified (§58), with route-dependent customer and visit scanner limitations retained |
| Catalogue | Complete | Attributes/values, colors, brand discount, reviews (the legacy Blade list retired, §28), bulk import/export and offers catalogue implemented; category (§13) and brand (§14) administration complete; digital product list, deletion, download and switches converted (§15); digital product create/edit converted on the shared editor and canonical uploader (§21); in-house and all-products lists with their switches, duplicate, delete and bulk delete converted (§16); physical product create converted with its typed variant/SKU endpoint (§22); physical product edit converted with translation tabs and stored variant rows (§23); the admin seller product list, its approval and remaining row actions use the same Inertia list and shared writer (§38) |
| Website/support | Complete | Header/home, footer, appearance, custom pages (§20), conversations, product queries, the ticket list and the ticket detail with its typed reply use Inertia; footer, appearance and page rich text use the shared editor, cleaned on write (§20); the header/footer scripts stay on their compatibility endpoint as plain code fields; script policy, `manage_website_scripts` and rich-text WAF exceptions stay held; Website required-field follow-up complete (§56), with scanner limitations retained; support reply/status marks and per-form legends verified (§58) |
| Marketing/blog | Complete | Coupons, flash/offer deals, campaign products, popups, subscribers and blog categories/list/actions integrated; blog post create/edit (§18) and the newsletter form (§19) use Inertia with the shared Tiptap editor; the typed newsletter send and bounded recipient search are unchanged; blog category required marks verified (§58) |
| Logistics | Complete | Drivers/trucks converted; the all-trips and today's-trips lists with their status dialog, notes, delete and bulk delete converted (§24); the trip create and edit forms converted without their map (§25); the trip orders list with its bulk status, invoice number and delivered actions converted (§26); the trip detail with its checkpoint notes, status and CMR download converted without its map (§27); the trip map picker converted on Leaflet from npm (§30); visit list/detail/day maps use Inertia and bundled Leaflet (§31); Trips required-field marks and legends verified, with scanner limitations retained (§57) |
| Orders/dashboard | Complete | Orders and entry forms converted; stored tax metadata and line-price-only grand total preserved; shell utilities integrated; dashboard converted to Inertia with bundled Chart.js (§33); Sales required-field marks, bulk selection and per-action legends verified (§57) |
| Seller administration | Complete: stages 34–36 | Seller accounts/verification/profile (§34), payments/withdrawals (§35), commission and verification form configuration (§36) use Inertia. Seller administration conversion is complete. The admin seller product list is converted with the catalogue in §38; no checkout, seller-area, storefront or role-grant policy changes |
| Back-office shells | Retired | No application consumer remains; three dead files removed, seven protected backend templates retained (§39) |
| Seller catalogue validation | Implemented; verification in §55 | Physical panel (§§53–54), digital panel and seller import row limits (§55); seller UI stays Blade |
| Public/seller/auth/email/PDF consumers | Retained | Blade coexistence, PDF layouts, Inertia root and CSP middleware remain |

### 4.1 Seller prerequisite and role grants

`1c005f6efdddee958ba8d739afd35eb0935007bd` records the prerequisite review.
Feature flags and checked-in owner-approval claims did not establish seller or
classified feature-use approval; the owner has since decided (2026-10-17, §5)
that the seller administration and the customer classified products are
converted, not deleted. The owner reaffirmed this for step 34 (2026-10-19),
overriding the earlier decision-blocked status. Seller list/account screens
are converted in §34, payments/withdrawals in §35, and commission/verification
configuration in §36. Seller administration conversion is complete; admin
classified products use Inertia in §37. The admin seller catalogue list uses Inertia since §38. This decision
grants no roles and changes no business policy. The seller area
remains outside this conversion, with its controllers/routes/views retained.
Customer-facing classified-product handlers stay; only the admin list and writes use the §37 controller.
Customer package administration is converted in §12; its purchase, payment and
mobile API consumers remain separate.

Union permission definitions while retaining per-action gates. Idempotent
definition migrations do not assign permissions to existing roles and do not
delete pre-existing grants on rollback. Production role assignments must be
reviewed explicitly; creating a permission does not authorize staff. The seller
commission activation controls retain their `{type,value}` contract through a
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
  steps, keeping their routes and gates; none is deleted as a feature. Step 34
  converts the seller list and accounts, replacing five Blade views with Vue.
  Step 35 converts payments and withdrawals, replacing six more views.
  Step 36 completes seller administration with commission and verification form
  configuration, replacing two views. Its numeric writer accepts only the two
  existing commission/withdrawal keys; verification stores the legacy ordered
  JSON with separately JSON-encoded options. The RaizerSuperAdmin/features_activation
  compatibility switch endpoint remains restricted and returns literal `1`.
  Both settings writers forget only `business_settings` through the shared writer;
  consumers read these values directly or through `get_setting`, with no derived
  cache depending on a full cache clear. Root `BusinessSettingsController` stays
  only for that switch endpoint and the refusing `env_key_update` action.
  Payout/commission/withdrawal behavior, balances, seller-facing Blade and
  storefront behavior stay established; no permission or role grant is implied.
- **`products` writers: Option A, owner decision 2026-10-22 (§52).**
  `products:sync` owns exactly `name`, `ar_name`, `published` on feed-origin
  rows (non-null `synced_at`). The store owns every other column
  and may write those three only on its own rows (null marker). All pre-marker
  rows default to unclassified/null; only reviewed IDs may be reclassified.
  Product's shared ownership guard covers store writers, including legacy
  services and imports; duplicates start store-origin. Brand/product/digital
  deletion and their foreign-key outcomes (§§14–16) remain unchanged.
- Product list switches share one write path. The digital-product and
  in-house/all-products lists publish and feature through
  `Concerns\UpdatesStoreProductFlags`, scoped to the products each list shows.
  The admin seller product list joins that shared path in §38, including
  approval and today's deal. Root `ProductController` remains for the JSON
  cron trigger and legacy read/SKU compatibility endpoints, with both analysis
  exclusions retained.
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

The current backend inventory contains **7 Blade files**, down from 10 in §38
and 73 at reconciliation. Step 39 removes the unused `layouts/app`,
`inc/admin_nav` and `inc/admin_sidenav`, including the now-empty `inc/` directory.
There are **0** admin app-shell extenders and **3** plain-layout extenders.
Every retained file has a protected consumer; none is an unconverted back-office screen.

- **downloads:** `downloads/brand.blade.php`, `downloads/category.blade.php`, `downloads/user.blade.php` (PDF bulk-upload reference downloads).
- **invoices:** `invoices/invoice.blade.php` (admin/seller invoice PDF rendering).
- **layouts:** `layouts/layout.blade.php` (seller login, delivery-boy login and 503).
- **product:** `product/products/sku_combinations.blade.php`, `product/products/sku_combinations_edit.blade.php` (seller create/edit AJAX fragments and retained compatibility tests).

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
| `ClassifiedProductController` | `classified_products`, `classified_products.published`, `classified_products.destroy` | `Store/Admin/Customers/ClassifiedProducts/Index.vue`; bound PUT publish and DELETE delete, existing per-action gates; customer-facing root handler retained (§37) |
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
| `ProductController` (Store/Admin) | `products.admin`, `products.all`, `products.seller`, `products.list.published`, `products.list.featured`, `products.list.for_sale`, `products.list.duplicate`, `products.list.destroy`, `products.list.bulk_destroy`, `products.create`, `products.store`, `products.variant_combinations`, `products.admin.edit`, `products.seller.edit`, `products.update`, `products.edit_variant_combinations` | `Store/Admin/Product/Products/Index.vue` (all three lists, `listType` `admin`/`all`/`seller`), `Create.vue` (§22; store → in-house list, `products.variant_combinations` → JSON variant rows), `Edit.vue` (§23; both edit routes, `listType` `admin`/`seller`; update → the edit form in the same language; `products.edit_variant_combinations` → JSON rows keeping stored stock values); `products.admin.edit`/`seller.edit` send a digital product to `digitalproducts.edit` (§21); `products.published`, `products.featured`, `products.update_sale`, `products.todays_deal`, `products.approved`, `products.duplicate`, `products.destroy` and `bulk-product-delete` now use scoped seller action adapters (§38); only `products.fetch` (JSON), the legacy SKU helper `products.sku_combination` and other read compatibility routes stay on root `ProductController` |
| `ProductBulkUploadController` | `bulk_product_upload`, `pdf.download_brand`, `pdf.download_category`, `pdf.download_seller`, `product_bulk_export.index`, `product_bulk_upload.index` | `Store/Admin/Product/BulkUpload/Index.vue` |
| `ProductQueryController` | `product_query.index`, `product_query.show`, `product_query.reply` | `Store/Admin/Support/ProductQueries/Index.vue`, `Store/Admin/Support/ProductQueries/Show.vue` |
| `ProductSearchController` | `order_configuration.product-search`, `products.add-more-choice-option` | JSON / redirects / PDF as applicable |
| `ProfileController` | `profile.index`, `profile.update` | `Store/Admin/Profile/Index.vue` |
| `ReportController` | `commission-log.index`, `in_house_sale_report.index`, `seller_sale_report.index`, `stock_report.index`, `user_search_report.index`, `wallet-history.index`, `wish_report.index` | `Store/Admin/Reports/CommissionHistory.vue`, `Store/Admin/Reports/InHouseSales.vue`, `Store/Admin/Reports/Searches.vue`, `Store/Admin/Reports/SellerSales.vue`, `Store/Admin/Reports/Stock.vue`, `Store/Admin/Reports/WalletHistory.vue`, `Store/Admin/Reports/Wishlist.vue` |
| `RepresentativePackageController` | `rep.packages.create`, `rep.packages.destroy`, `rep.packages.index`, `rep.set-package`, `rep.packages.store`, `rep.packages.update`, `rep.packages.edit` | `Store/Admin/Representatives/Packages/Index.vue`, `Store/Admin/Representatives/Packages/Create.vue`, `Store/Admin/Representatives/Packages/Edit.vue` |
| `RepresentativePaymentController` | `admin.rep.payments.accept`, `admin.rep.payments.export`, `admin.rep.payments.index`, `admin.rep.payments.reject` | `Store/Admin/Representatives/Payments/Index.vue` |
| `ReviewController` | `admin.reviews.index`, `reviews.published`, `reviews.index` | `Store/Admin/Product/Reviews/Index.vue`; `reviews.index` (GET `/temp/reviews`, `view_product_reviews`) redirects to `admin.reviews.index` keeping the query (§28); root `ReviewController` keeps the customer `store` and `product_review_modal` |
| `RoleController` | `roles.index`, `roles.store`, `roles.create`, `roles.update`, `roles.destroy`, `roles.edit` | `Store/Admin/Staff/Roles/Index.vue`, `Store/Admin/Staff/Roles/Create.vue`, `Store/Admin/Staff/Roles/Edit.vue` |
| `SellerController` (Store/Admin) | `sellers.index`, `sellers.create`, `sellers.store`, `sellers.edit`, `sellers.update`, `sellers.ban`, `sellers.destroy`, `bulk-seller-delete`, `sellers.show_verification_request`, `sellers.verification_file`, `sellers.approve`, `sellers.reject`, `sellers.login`, `sellers.profile_modal`, `sellers.approved` | `Store/Admin/Sellers/Sellers/Index.vue`, `Create.vue`, `Edit.vue`, `Verification.vue`; profile JSON feeds `StoreSellerProfileDialog`; private verification downloads keep storage rules; login switches only the store session. Payout dialogs use `SellerPaymentController` since §35; root `SellerController` is removed |
| `SellerCommissionController` | `business_settings.vendor_commission`, `business_settings.vendor_commission.update` | `Store/Admin/Sellers/Commission/Index.vue`; typed numeric settings writer, existing permissions/URIs; switches retain the root compatibility endpoint (§36) |
| `SellerVerificationFormController` | `seller_verification_form.index`, `seller_verification_form.update` | `Store/Admin/Sellers/VerificationForm/Index.vue`; ordered builder, legacy stored JSON, deliberate missing-row/empty-list fixes (§36) |
| `SellerPaymentController` | `sellers.payment_modal`, `sellers.payment_histories`, `sellers.payment_history`, `commissions.pay_to_seller` | `Store/Admin/Sellers/Payments/Index.vue` (global/scoped history); modal JSON feeds `StoreSellerPaymentDialog` on seller/withdrawal lists; store retains the locked money transaction (§35) |
| `SellerWithdrawRequestController` | `withdraw_requests.index`, `withdraw_requests.create`, `withdraw_requests.show`, `withdraw_requests.edit`, `withdraw_requests.update`, `withdraw_requests.destroy`, `withdraw_requests_all`, `withdraw_request.payment_modal`, `withdraw_request.message_modal` | `Store/Admin/Sellers/Withdrawals/Index.vue`; typed payment/message JSON, shared payment dialog and readonly `StoreSellerWithdrawMessageDialog`; former empty resource actions redirect without writes (§35) |
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
`BusinessSettingsController` keeps only the seller activation compatibility endpoint and refusing `env_key_update` action; commission/verification screens use their Store/Admin controllers since §36 (the generic settings
writer and its refusing `.env` stub are gone, §20), so it stays in both
analysis exclusion lists. Seller list/account handlers use `Store\Admin\SellerController` since §34; payments/withdrawals use their admin controllers since §35; root `SellerController`, `PaymentController` and `SellerWithdrawRequestController` and both exclusions are removed. Admin classified products use `ClassifiedProductController` since §37. Root `CustomerProductController` and both exclusions remain for the customer area, including its shared `updatePublished` compatibility action. The newsletter and
the ticket detail use their typed admin controllers and Inertia pages (§19). Explicit approval and implementation gaps are tracked
by source/path in the reconciliation ledger. The unavailable audit commit
`aed3feab5b6edd95fb941ee92a455e058403def5` is excluded and was not recovered or included.


**Completion (§39).** All back-office screens use Inertia. The seven backend
Blade templates in §5.1 serve downloads/PDFs, protected login/error pages and
seller SKU fragments. `StoreAdminLayout` and `storeAdminMenu.ts` are the only
admin shell/sidebar. No route or controller changes in step 39: **1,046**
`/temp` and **434** `/temp/admin` registrations remain. `HandleStoreBladePages`
and `store.blade_csp` stay for the storefront, seller panel, auth and 503 pages.


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
one transaction. Option A (§52, shared-database.md) supersedes the old
single-writer conflict; this deletion policy is retained. A product referenced by a pallet, store product barcode or
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

**Deletion retained under Option A (§52).** Deleting still
removes the product's translations and stock rows and hard-deletes the
`products` row, now in one transaction with the row locked, and only for a
digital product. Option A (shared-database.md) retains the brand/product
and digital deletion rules. A pallet referencing
the product (`pallets.product_id` restrict) or a store barcode/variation
(no-action FKs) makes the delete fail atomically with a server error; WMS
settings/barcodes cascade and cell-log/verification references null. Tests pin
both outcomes. Soft delete, a refusal with a validation error, or any other
policy needs owner approval. Under Option A (§52), the published switch
refuses a change on a feed-origin product; it remains available for store-origin
products. The feed owns publication only on its marked rows.

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

**Historical at step 16 (superseded by §§21–23 and §38).** The seller list (`products.seller`) stays Blade with seller
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
    `import:all-products-v2`. Under Option A (§52) this store import also
    passes Product's ownership guard and cannot rename or publish feed-origin
    rows. It owns other columns, without sync privileges.
- **Duplicate (store writer under Option A, §52).** It still copies the
  `products` row with a random slug suffix, plus its stock and tax rows, but
  not its translations, variations or barcodes. It now runs in a transaction,
  only on a product the list shows. The copied row starts with null `synced_at`,
  including when its source was feed-origin; it is a store-created product.

**Deletion retained under Option A (§52).** Single and bulk
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
- **Store writer under Option A (§52).** New rows have null `synced_at`.
  Updates may change `name`, `ar_name`, `published` only on store-origin rows;
  Product's shared guard refuses feed-owned changes. The store owns all other
  columns. Existing transaction/rollback behavior remains.
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

**Store writer under Option A (§52).** Every newly created product has null
`synced_at`; the store owns all its columns. Feed-origin products
reserve exactly `name`, `ar_name`, `published` for `products:sync`. Product's
shared guard enforces the boundary (shared-database.md).

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
- **Store writer under Option A (§52).** The shared Product guard refuses
  changes to `name`, `ar_name`, `published` on feed-origin rows; every other
  business column remains store-owned. Null-origin rows remain fully editable.
  Approval authority and deletion rules are unchanged.

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
  delivery, whatever it had, saved per order in one transaction. D10 (§66)
  now requires every selected order to be attached to the supplied open trip.
- **Invoice number:** stored as typed (an empty field clears it) and the
  order goes out for delivery (`on_delivery`) whatever its status, saved on
  change as before. D10 (§66) now requires an attachment to the supplied open trip.
- **Mark as delivered:** sets `delivered`; D10 (§66) now permits only confirmed,
  ready-for-delivery and out-for-delivery orders attached to the supplied open trip.
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
  untouched: a checkpoint status never changes the trip. D10 (§66) now scopes
  notes and status through the supplied open trip before writing.
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
[PR #444](https://github.com/arb-rajab/warehouse_management/pull/444) is the single PR into `develop`;
its number is recorded in a follow-up `[skip ci]` commit before merging.

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


## 34. Seller list and accounts (2026-10-19)

[PR #445](https://github.com/arb-rajab/warehouse_management/pull/445) is the single
PR into `develop`. Its number is recorded in a documentation-only `[skip ci]`
follow-up commit before the authorized merge. Implementation commit: `c98dff89`.

Stage 1 of 3, implemented on `codex/step-34-sellers` from freshly fetched
`origin/develop` **fc8e73de** (PR #444). Model: **GPT-6 Codex**; the exact
variant and configured effort are not exposed by this runtime. The owner
explicitly authorizes conversion of sellers and classified products, overriding
the historical decision-blocked status. This grants no roles and changes no
commission, payment, withdrawal, balance, seller-area or storefront policy.
See [the step 34 ledger](../store-backoffice-reconciliation-20261011.md#seller-list-and-account-screens-step-34-2026-10-19)
for actual checks and shipping; no historical result substitutes for this run.

**Controller and permission boundaries.** `Store\Admin\SellerController`
implements `HasMiddleware`. `view_all_seller` gates index, verification and
private file download; `view_seller_profile` gates the profile JSON;
`login_as_seller` gates impersonation; `edit_seller` gates create/store/edit/
update, accept/reject and the approved switch; `ban_seller` gates ban/unban;
`delete_seller` gates single/bulk deletion. These are the root controller's
existing gates. The old approval UI checked `approve_seller`, but the route
always required `edit_seller`; the new switch follows the route. No permission
definition or grant changes; `LegacyAdminPermissionsTest` lists stay unchanged.

Routes retain names and bind `{seller}` to **Shop**, because the legacy list
and account actions address shop IDs, not the separate `sellers` table. Edit
and impersonation now accept the bound numeric ID in place of ciphertext.
Deletes become DELETE; the empty `sellers.show` and unnamed duplicate resource
destroy route are removed. `RouteServiceProviderTest` drops only that obsolete
seller duplicate example; its real duplicate-name cases remain. The state-write
ratchet's route list stays; its removed Blade ban-modal scan is replaced by
seller Vue/Chromium confirmation tests. Missing bound records are 404. Invalid
body references (`id`, bulk members) are 422 before any write.

`FilterSellerRequest` composes `FiltersPerPage`; the list selects only its
columns, scopes the account eager load, counts products in SQL, preserves search
and approval behavior, and paginates **20** with query strings and an allowed
per-page selector. `SellerResource` provides the narrow list payload. Create/
update share typed validation for required name/email, store-wide unique email,
required create/optional edit password and schema-length limits. No new password
strength or confirmation policy is introduced. Creating the account, seller
row and demo shop is transactional; verification email/auto-verification follow
the existing `email_verification` setting. Account updates do not change shop
or wallet fields and send no verification email.

**Established action behavior.** Ban toggles the account flag. Accept sets
verification status to 1, retaining info; reject sets 0 and clears info; the
approved switch sets the boolean without clearing info. These three forget
`verified_sellers_id`; none sends email or notification. Deletion removes the
shop, store account, products owned by that account, orders whose **buyer**
`user_id` is that account, and those orders' lines. It retains the separate
seller row, payments, withdrawals, and other buyers' orders/lines even when
those lines belong to the deleted seller. No additional product-related cleanup
is introduced. Single/bulk deletes are transactional; all bulk members validate
before deletion. Impersonation keeps the store session's remembered login,
session regeneration and standard Login event, leaving the WMS guard alone.
No new custom audit behavior is invented. Private files stay on
`ShopVerificationStorage` and the existing private disk; traversal cannot escape
its directory (the WAF also refuses malformed paths).

**Vue and coexistence.** Four pages under `Store/Admin/Sellers/Sellers` use
`StoreAdminLayout`, shared forms/table/pagination/page header, Wayfinder,
permission-gated actions, native constraints, `lib/confirm.ts`, English/Arabic
`store.sellers.*`, RTL and dark styling. `StoreSellerFormFields` serves both
account forms. `StoreSellerProfileDialog` uses the shared accessible dialog and
Inertia 3 `useHttp`; it loads only on opening, cancels stale requests and handles
failure. All seller text is escaped, including verification labels and bank
fields; social and managed avatar URLs are restricted to HTTP(S). Profile
statistics retain all product/line counts and sum **price only** for lines whose
parent order is paid, with no quantity/tax/money recalculation.

Only the all-sellers sidebar entry becomes an Inertia visit. Payment/history
links stay full loads. Consumer searches across app/routes/resources/public JS/
tests found only the old list called `sellers/payment_modal`. It remains Blade
on the root `pay_to_seller` POST and gains the existing admin shell; the list
uses a native CSRF POST to open it as a full page. Its payment form, fields,
commission writer and money behavior stay unchanged for stage 35. The list
supplies a session CSRF token only to permitted payment callers. The root
controller and both Pint/PHPStan exclusions remain. Payment histories,
withdrawals, commission, verification configuration, seller products and all
`resources/views/shop/seller` and storefront files remain for later stages.

**Consumers, tests and inventory.** `git rm` removes exactly
`sellers/index.blade.php`, `create.blade.php`, `edit.blade.php`,
`profile_modal.blade.php`, `verification.blade.php`. Their only renderer was the
moved root controller. Existing Blade links keep their route names. Admin file
access cases move out of `LegacyShopVerificationTest`; its seller-upload cases
remain. Compatibility tests that used the seller list as a Blade example now use
the retained payment-history screen: CSP/fallback, staff coexistence, general
settings shell logo and legacy uploader navigation. The visit sidebar test uses
that screen’s existing permission. These preserve their original assertions
while the seller list becomes Inertia.
Pest covers every action, exhaustive payloads, validation, pagination, refused
callers with unchanged DB/session, missing records, notifications/cache,
transaction rollback and the precise deletion boundary. Vitest fully mounts all
four pages and both new components; Chromium covers dialog focus, native
confirms, RTL and dark contrast under enforced CSP.

Current inventory: **20** backend Blade files, **9** direct literal app-shell
consumer files including the shell test (**8** actual extending views), **3**
layout-shell extending views; **1,046** `/temp` and **434** `/temp/admin` route
registrations (two removed dead/duplicate registrations). Retained Blade editor
inventory remains 8 fields in 8 views. Verification results, failed setup
attempts, skipped checks and PR state are recorded in the ledger. Every shipping
commit, PR title and merge message must contain `[skip ci]`; PR CI, PR/merge
SAST and deploy do not run. No attestation trailer is written by hand or invoked
through the bare pre-push mode.


## 35. Seller payments and withdrawals (2026-10-19)

Historical conversion record. [§60](#60-seller-payout-defects-d1-d2-d3-and-d5-2026-10-24) supersedes the retained D1/D2/D3/D5 defects and limits below; the money directions and transaction boundaries stay unchanged.

[PR #446](https://github.com/arb-rajab/warehouse_management/pull/446) is the
single PR into `develop`. Implementation commit **f4ca952d** passed the explicit
full gate before pushing. This documentation-only `[skip ci]` follow-up records
the PR number before the authorized merge.

Stage 2 of 3 on `codex/step-35-seller-payments`, branched from freshly fetched
`origin/develop` **cbbb3c3c** (PR #445). Model: **GPT-6 Codex**; the exact
variant and configured reasoning effort are not exposed by this runtime.
The owner authorizes conversion, not feature deletion. No role grants,
commission calculation, wallet, storefront or seller-area policy changes.
[The step 35 ledger](../store-backoffice-reconciliation-20261011.md#seller-payments-and-withdrawals-step-35-2026-10-19)
records actual checks, failures, skips and shipping; earlier results are historical.

**Routes and controllers.** `Store\Admin\SellerPaymentController` uses
`HasMiddleware`: `pay_to_seller` for modal/store and `seller_payment_history`
for both histories. `Store\Admin\SellerWithdrawRequestController` keeps
`view_seller_payout_requests` for every action, including both JSON modals.
Names and HTTP verbs remain, including `commissions.pay_to_seller` and every
resource name except the already-absent store. History binds `{seller}` to
StoreUser (numeric account ID replaces ciphertext); withdrawal resource routes
bind `{withdrawRequest}`. POST body references validate as 422, including a
withdrawal belonging to another seller. Already-paid/nonpending requests remain
404 state conflicts inside the existing locked transaction.

The three moved root controllers have no remaining renderer/caller and are
removed with both Pint/PHPStan exclusions. Root `CommissionController` retains
only `calculateCommission`, still called by `Helpers.php` during checkout;
its exclusions remain. `Payment::seller` is a typed relation, with its own
noise-data test, and this cleaned model leaves both exclusion lists.
StoreUser's existing shop and SellerWithdrawRequest's existing user relation gain
types/documentation only; other legacy model behavior/exclusions remain.

**Money pinned before refactoring.** Both payout branches already used a
transaction and shop row lock; withdrawal payouts also locked their pending
request. The migration preserves those boundaries, adding no transaction.
Amounts remain numeric and greater than zero, with **no backend maximum** based
on balance or requested amount. `cash` and `bank_payment` debit only
`shops.admin_to_pay`; every other nonempty method (up to 100 characters) credits
it and records `Seller paid to admin`. The seller account wallet never changes.
The payment row retains the account ID, amount, method, nullable 191-character
transaction code and null details. Temporary payment session keys are cleared
in `finally`. No email/notification was sent by either payout branch; none is
added. Seller withdrawal creation/API notifications are untouched.

A cash/bank withdrawal payout changes pending status (0/null) to 1 and viewed
to 1, then debits the submitted amount and inserts the payment once. A partial
payment still closes the **whole** request; its amount and seller message stay
unchanged. Repeated/nonpending payment and record-save failure leave all money
and request data unchanged. Arbitrary-method crediting does not close a request.
These established semantics are deliberately retained, not new approval policy.

**Existing defects recorded, not silently fixed.** The root resource's
create/show/edit/update/destroy methods were empty. There is no functioning
approve, reject, admin-message-write or delete workflow to migrate. Those route
names/gates remain; now they redirect to the list with no mutation, rather than
returning an empty 200. Tests submit approval/rejection statuses and messages and
prove no data or notifications change. The legacy negative/zero-due form omitted
`payment_option` even though the writer required it; its Clear due submission
still receives that validation error. The withdrawal form still has no txn-code
field. No new method choice or withdrawal policy is invented to fix either.

**Lists, dialogs and deliberate presentation changes.** Histories and both
withdrawal-list aliases use `FilterSellerPayoutRequest`, `FiltersPerPage`,
`resolvePerPage(..., 20)`, explicit selections, scoped eager loads, stable newest
ordering, `paginate()->withQueryString()` and narrow Resources. Global lists
retain Blade's orphan handling: hide rows without a live account/shop **after**
pagination, preserving the original raw total/window. Seller history now also
paginates, and an empty history renders an empty state instead of redirecting
with a flash. No new monetary total or recalculation is introduced. Existing
amount/date/method/txn contents are retained; the number column shows record IDs.

The seller list's native Blade POST is replaced by `StoreSellerPaymentDialog`;
it and the withdrawal list load typed JSON with Inertia 3 `useHttp`, cancel stale
requests, and use the shared accessible dialog. The payment form uses Inertia
Form, native amount min/step, conditional shop-enabled payment methods, escaped
bank text, and `lib/confirm.ts` before recording money. Default withdrawal amount
remains min(requested, balance); that is not a new server maximum. Messages are
readonly escaped text. The old message action omitted an accountant response
despite the admin group's permission; the new read uses that same permission
and the message already exposed by the list. Vue action gates now match actual routes: message viewing
uses `view_seller_payout_requests`; submitting money requires `pay_to_seller`.
Both sidebar entries and history links use Inertia. Both locales add
`store.sellers.*`; logical styling, dark mode and RTL are retained. Success
flashes are removed as in the per-module pattern.

**Consumers and inventory.** Searches covered app, routes, resources,
public/assets/js and tests before deleting all six replaced Blade files.
In this checkout only `shop/seller/money_withdraw_requests/index.blade.php`
references the admin `withdraw_request.message_modal`; its JS helper has no
trigger, and the seller caller is already refused by the admin group. That
route name and refusal stay. The seller's payment history is its own
`seller.payments.index`/`Seller\PaymentController`, not the admin history/modal.
No seller Blade or seller-route file changes. No second seller-area view calling
these admin modals was found. Shared assets, the frontend message partial,
Blade shells/CSP and stage-36 commission/verification views remain.

Existing Legacy commission-payout cases move to the new payment controller test;
the admin no-store case moves out of LegacySellerWithdrawRequestTest while all
seller/API cases remain. LegacyAdminPermissionsTest and state/route ratchet lists
are unchanged: no gate or registration is added/removed. Former payment-history
Blade samples in shell/CSP/logo/uploader/sidebar tests now use retained commission
settings and its existing permission. Current inventory: **14** backend Blade
files; **5** literal app-shell consumer files including the shell test (**4**
actual extending views); **3** layout-shell extending views; **1,046** `/temp`
and **434** `/temp/admin` registrations. The retained rich-editor inventory stays
8 fields in 8 views. Exact final verification and shipping are in the ledger.
PR CI, PR/merge SAST and deploy do not run under `[skip ci]`.


## 36. Seller commission and verification form (2026-10-19)

Stage 3 of 3 on `codex/step-36-seller-settings`, from freshly fetched
`origin/develop` **5ae9eba0** (PR #446). Model: **GPT-6 Codex**; the exact variant
and configured reasoning effort are not exposed by this runtime.
Seller administration conversion is complete. The owner authorizes
conversion, not feature deletion, role grants or business-policy changes.
Checkout's root `CommissionController`, seller-area controllers/views, seller
registration, storefront, commission calculation and withdrawal service are
unchanged. The remaining Blade seller product listing is catalogue work.
See [the step 36 ledger](../store-backoffice-reconciliation-20261011.md#seller-commission-and-verification-form-step-36-2026-10-19)
for exact checks, setup failures, skips and shipping.

**Routes and writers.** `Store\Admin\SellerCommissionController` and
`SellerVerificationFormController` implement `HasMiddleware` with the existing
`seller_commission_configuration` and `seller_verification_form_configuration`
gates. Their GET/POST names and URIs stay unchanged. Typed requests replace
inline request reads. Commission retains legacy `types[]` subform support and
403 rejection of foreign/localized types; foreign top-level keys are validation
errors. Only `vendor_commission` (numeric 0–100, at most two decimals) and
`minimum_seller_amount_withdraw` (numeric >= 0) can be written.

The two activation controls still send exactly `{type,value}` to
`business_settings.seller_commission.activation` and receive literal `1`. Its
two-key type allowlist, foreign-input rejection, `features_activation` permission
and `RaizerSuperAdmin` middleware remain unchanged. Vue gates them using those
permissions and the vendor flag, handles failures, restores a refused selection
and cancels pending HTTP on unmount. Root `BusinessSettingsController` remains
for this endpoint and refusing `env_key_update`; both analysis exclusions stay.

Both new settings writers reuse `UpdatesStoreSettings` and its transaction and
`Cache::forget('business_settings')`. The removed full `cache:clear` had no
derived-cache consumer: checkout and withdrawal read `get_setting`, verification
reads the row/`get_setting`, and the public settings API queries rows directly.
Tests prove fresh reads and preservation of unrelated WMS and seller caches.
The activation endpoint already used this narrow invalidation.

**Stored verification contract and deliberate fixes.** Supported types remain
`text`, `file`, `select`, `multi_select`, `radio`. Fields retain order and store
exactly `{type,label,options?}`; select/multi-select/radio options are themselves
a JSON-encoded string, with PHP's legacy escaping and key order. Other fields
have no stale options from a previous row. The editable Inertia payload decodes
options to lists, and the writer re-encodes them. Labels/options are required
strings bounded at 255 characters; select types require 1–100 options; at most
100 fields are accepted. Native constraints and add limits mirror these bounds.
A missing setting row is deliberately created on save; an empty field list
deliberately saves `[]`. These fix the former missing-row/count-null crashes.
The seller verification Blade and its submission handler retain their double
decoding/answer shape; tests exercise the real reader and answer writer plus
the public/mobile settings response. No registration-page reader of this
setting exists in this checkout; registration behavior is unchanged.

**UI, consumers and inventory.** Both Vue pages use `StoreAdminLayout`,
`ResourceFormPage`, Wayfinder, `useStoreAccess`, English/Arabic `store.sellers.*`,
logical styling and dark variants. Commission reuses `StoreBooleanField`.
The builder adds/removes fields/options, changes each type, and reorders by
accessible up/down buttons with stable row/option identities; no drag library.
Both sidebar entries become Inertia visits. Existing Blade sidebar links retain
their route names and need no URL change.

Consumer searches covered app/routes/resources/public JS/tests before `git rm`
removed the two replaced Blade views. The moved legacy middleware gate cases
now accompany their controllers; existing activation cases move from settings
reconciliation into commission coverage. The root middleware test retains its
remaining compatibility gate. `LegacyAdminPermissionsTest` and route/state
ratchet lists stay unchanged because no gate, route name, URI or verb changed.
Retained-Blade CSP/uploader/logo/sidebar samples now use classified products
and that page's existing permission, preserving the original assertions.
CSP helpers move into `tests/Pest.php` for independent workers. Full mounts
auto-unmount after each test; the full gate also required that cleanup in the
existing Products index test file to prevent locale-change timeouts. Test
assertions are unchanged. The ledger records the session's bounded execution
budgets and exact verification commands.

Current inventory: **12** backend Blade files; **3** literal app-shell consumer
files including the shell test (**2** actual extending views); **3** layout-shell
extending views. Route registrations stay **1,046** `/temp`, **434** `/temp/admin`.
The rich-editor inventory remains 8 fields in 8 views. No shared asset, shell,
CSP or protected Blade file is removed. Pest covers both actions, full payloads,
validation/boundaries, unchanged refused-caller DB, legacy JSON round trips and
cache behavior. These singleton pages have no bound record IDs, so record-ID
404 cases do not apply. Full-mount Vitest and real Chromium cover the builder,
permissions, switch contract, reload persistence, RTL and dark contrast.
Exact results remain in the ledger; no historical check is claimed for this run.
The explicit full pre-commit gate passed: **332 Vitest files / 2,561 tests**,
Pint, PHPStan (**0 errors**) and **6,234 Pest tests / 36,018 assertions**, plus
Wayfinder/lint/format/types/config clearing. The normal production build and
all **9 distinct affected Chromium cases** passed. The ledger records the
bounded session budgets and skipped CI-only groups. No attestation was used.
Shipping is through [PR #447](https://github.com/arb-rajab/warehouse_management/pull/447)
into `develop`; this documentation-only follow-up records its number before
merging. Implementation commit: **90a3e973**. Both commits, the PR title and
the authorized merge message carry `[skip ci]`.
PR CI, PR/merge SAST and deploy do not run under `[skip ci]`.


## 37. Admin classified products (2026-10-19)

[PR #448](https://github.com/arb-rajab/warehouse_management/pull/448) is the single
PR into `develop`. Implementation commit **4b8e6249** passed the explicit full
gate before pushing. This documentation-only `[skip ci]` follow-up records the
PR number before the authorized merge.

Implemented on `codex/step-37-classified-products` from freshly fetched
`origin/develop` **c6f8d0fd** (PR #447). Model: **GPT-6 Codex**; this runtime
does not expose the exact model variant or configured reasoning effort.
The owner authorizes conversion, not deletion of the feature or a business
policy change. [The step 37 ledger](../store-backoffice-reconciliation-20261011.md#admin-classified-products-step-37-2026-10-19)
records this run's checks, failures, skips and shipping state.

`Store\Admin\ClassifiedProductController` implements `HasMiddleware` and owns
`customer_product_index`, `updatePublished` and `destroy_by_admin` for the three
existing admin route names. The existing `view_classified_products`,
`publish_classified_product` and `delete_classified_product` gates remain.
Publishing is now PUT `/classified_products/{classifiedProduct}/published`,
validated by `UpdateClassifiedProductPublishedRequest` (`status` is a required
boolean); delete is DELETE `/classified_products/{classifiedProduct}`. Both bind
`CustomerProduct`, return 404 for a missing ID, and redirect to the named index
with its query. Publishing changes only `published`; customer `status`, upload
limits and notifications are unchanged. Delete transactionally removes the
product's translations and product, retaining upload rows/files and other
owners' products, exactly as the previous handler did.

**Shared customer action found during tracing.** `routes/shop-web.php` also
registers `customer_products.published` on root
`CustomerProductController::updatePublished`. To obey the explicit requirement
that customer routes and behavior stay unchanged, this root method and its
`publish_classified_product` gate remain byte-for-byte; only the admin route
uses the new method. The root admin index/delete methods and their middleware
entries are removed. The root controller and both Pint/PHPStan exclusions stay.
No customer-facing route/view, upload/edit form, search, activation setting or
mobile API is changed. The customer compatibility route retains its response
and authorization, pinned in the new controller test.

`FilterClassifiedProductRequest` uses `FiltersPerPage`; the list defaults to
20 rows with the selector. An explicit selection and scoped translation/thumbnail
eager loads avoid per-row queries; the displayed uploader is the stored
`added_by` string, so no account lookup is needed. Legacy `created_at desc` is
preserved with an `id desc` tie break. Pagination keeps query strings.
`ClassifiedProductResource` documents its narrow row with `@property-read`;
thumbnail URL resolution preserves `uploaded_asset` precedence and placeholder
behavior using the eager-loaded upload. `CustomerProduct` gains only the typed
thumbnail relation and a return type on its existing translation relation.

The Vue page uses `StoreAdminLayout`, `DataTable`, `PageHeader`, `Pagination`,
`TableActionLink`, Wayfinder, `useStoreAccess` and `lib/confirm.ts`. Rows retain
page-relative numbers, escaped translated names/uploader text, thumbnail,
customer PUBLISHED/UNPUBLISHED badge, independently gated publishing switch and
delete. Both storefront links are plain anchors with `target="_blank"` and
`rel="noopener"`. Delete passes `{ mergeQuery: {} }`. English/Arabic
`store.classifiedProducts.*`, logical switch positioning and dark styling are
included. The sidebar entry becomes an Inertia visit; its setting and permission
gates stay. The replacement removes the old Blade view's malformed delete
`data-href` (extra braces) and stray JavaScript `s`; these existing template
defects are recorded rather than reproduced in Vue.

Consumer searches covered app/routes/resources/public JS/tests before `git rm`
removed exactly `customer/classified_products/index.blade.php`. Ten remaining
views still include the shared delete modal, so it stays. Retained-Blade
CSP/uploader/logo/sidebar test samples move to `products.seller` and its existing
`show_seller_products` permission, retaining their assertions. No Legacy*
classified action cases existed to move; the only classified-specific Legacy*
entry is the existing destroy route ratchet. `LegacyAdminPermissionsTest` and
route/state ratchet lists stay unchanged because names and permission gates
stay, and the state-write ratchet already accepts DELETE. New Pest tests cover
render/whole-row closures, both flags, translation fallback, bounded queries,
pagination/validation, both publish directions, missing IDs, every refused
caller and exact deletion boundaries. Vitest fully mounts the page; Chromium
covers the native confirm and real publish/menu/RTL/dark/CSP behavior.

Current inventory: **11** backend Blade files; **2** literal app-shell consumer
files including the shell test (**1** actual extending view); **3** layout-shell
extending views; **1,046** `/temp` and **434** `/temp/admin` registrations.
The editor inventory stays 8 fields in 8 views. Shared shells, assets, CSP
coexistence and protected downloads/invoices stay. Actual verification and the
shipping record are in the ledger. PR CI, PR/merge SAST and deploy do not run
under the required `[skip ci]` messages. No attestation is claimed.

The explicit full pre-commit gate passed: **333 Vitest files / 2,568 tests**,
Pint, PHPStan (**0 errors**) and **6,264 Pest tests / 36,230 assertions**, plus
Wayfinder, whole-repo lint/format/types and config clearing. The normal production
build and all **8 affected Chromium cases** passed. Baseline: **102 Pest tests /
563 assertions**. The ledger distinguishes initial failures, runtime repairs and
skipped CI-only groups. The root customer publishing compatibility action stays.


## 38. Admin seller product list (2026-10-20)

The inventory below records the step 38 checkpoint; §39 supersedes its shell-retention notes.

[PR #449](https://github.com/arb-rajab/warehouse_management/pull/449) is the single
PR into `develop`. Implementation commit **c41f0a79** passed the explicit full
gate and all ten affected Chromium cases before pushing. This documentation-only
[skip ci] follow-up records the PR number before the authorized merge.

Implemented on `codex/step-38-seller-product-list`, based on freshly fetched
`origin/develop` **47aec0d6** (PR #448). Model: **GPT-6 Codex**; the exact
variant and configured reasoning effort are not exposed by this runtime.
The owner authorizes this migration and shipping only after the required
checks can run. No permission grants or WMS product-write policy changes.
[The step 38 ledger](../store-backoffice-reconciliation-20261011.md#admin-seller-product-list-step-38-2026-10-20)
records actual checks, failures, skips and shipping.

**List and gates.** `products.seller` keeps its URI and `show_seller_products`
gate on `Store\Admin\ProductController::seller`. It uses the existing
`Store/Admin/Product/Products/Index`, `StoreProductList::Seller`, shared
`ProductListResource`, filter concern and pagination pattern. Its base scope
is seller-added, physical, non-auction and non-wholesale products. Name-only
phrase search, owner filtering and the exact published/for-sale/featured
matches (including all-off defaults) are preserved. GET booleans follow §16:
literal `0`/`false` means off, replacing the old presence-only interpretation. The old seller handler had no default order; it now orders newest first
with an ID tie break. Six allowlisted sorts, narrow selections/eager loads and validated 20-row
pagination with a per-page selector follow the existing list pattern.

**Legacy filter defects and requested presentation changes.** The old category
select posted a value the handler ignored; the seller select was commented out;
there was no approval-status filter. Step 38 explicitly requests these filters,
so seller/category/approval filtering now works and approval `0` is retained on
pagination. Today's-deal markup was commented out; its existing action is now
offered on the seller list as requested. The old deleted checkbox had no effect
and is omitted, as in-house §16 did. Search still matches only `name`, not Arabic
name/barcodes. These changes are recorded rather than presented as working
legacy behavior. The approval control/filter appears under the existing
`product_approve_by_admin` setting; the endpoint keeps its existing edit gate.

**Actions and established effects.** All existing route names remain.
`products.published`, `.featured`, `.update_sale`, `.todays_deal`, `.approved`,
`.duplicate`, `.destroy` and `bulk-product-delete` now bind `{product}` where
applicable and run on the store admin controller. The seller adapters fix the
scope in route defaults, so forged `list=all` cannot widen it. Shared
`products.list.*` actions also accept the seller list type. Publication,
featuring, sale, today's deal and approval require `product_edit`; duplication
requires `product_duplicate`; single/bulk deletion requires `product_delete`.
A permitted staff member may delete another account's seller product; a seller
account cannot call these admin routes. Deletes are DELETE and writes redirect
to the named list with its query. Typed requests reject invalid states/IDs.

`UpdatesStoreProductFlags` is the single writer for product flags. Unpublishing
or taking off sale unfeatures and sets every stock quantity to 0; switching on
restocks to 1,000. Featured writes only its flag. Approval writes only `approved`,
with the legacy package check in **both** directions, including unapproval.
These actions clear views/cache after commit; today's deal changes only its
flag and forgets `todays_deal_products`. None sends notifications or adds audit
records. Subscription eligibility and published-product counting stay unchanged.
The removed root publishing implementation restocked before checking a package
and could refuse after changing stock; the existing §16 shared writer refuses
before any write, and the seller list now follows that required shared path.
The old orphaned owner/shop dereference could also fail; the shared helper
refuses a missing package/shop before writing. No new package policy is introduced.

Duplicate still copies the product row (owner/flags included), random slug
suffix, stocks and taxes, without translations/variations/barcodes. Single/bulk
delete removes translations, stocks, taxes, variations and store barcodes,
hard-deletes products and soft-deletes cart lines. Shared §16 transactions,
locks, all-members-before-delete validation, warehouse-FK rollback/cascade/null
outcomes and once-per-request cache clearing apply. These remain second writers
of WMS-owned `products` beside `products:sync`; the owner's policy decision
remains pending (§5), with no new policy inferred.

**Consumers and root controller.** Trace searches covered app/routes/resources,
public JS and tests, including `routes/shop-seller.php` and all seller views.
Seller product/digital-product pages use their own `Seller\ProductController`
actions, not these admin routes. Seller create/edit render backend
`sku_combinations.blade.php` and `sku_combinations_edit.blade.php`; both stay.
The admin SKU compatibility route also stays, used by variant-parity tests.
`products.fetch` remains the JSON cron trigger called by the in-house/all lists;
its ungated admin-group contract, 30-minute command/status checks and queued
import are unchanged. Root `ProductController` retains fetch/SKU/search/detail
compatibility methods and both Pint/PHPStan exclusions. The unconsumed `show`
and brand-selector methods are removed. The existing missing
`get_products_by_subcategory` method and non-returning search route are reported
defects; their registered names are kept, without inventing new behavior.

**Vue, tests and inventory.** The shared page uses seller Wayfinder actions,
seller edit links, permission-gated controls, escaped labels/stock text,
English/Arabic strings, RTL and dark styles. The sidebar entry is an Inertia
visit. `StoreStatusButton` adds a typed `approved` field-name option; existing
callers retain `status`. No dependency or lockfile change. Ten pre-refactor
legacy behavior cases pin flags/cache/notifications, copying and deletion.
New Pest covers render/full row, noise/scopes, filters, pagination/validation,
every refused caller and action, missing models, forged lists and rollback.
The middleware case moves from `LegacyControllerMiddlewareConversionTest`
to the migrated controller coverage: the root no longer has gated writes or
injected stock/tax services. `LegacyAdminPermissionsTest` and state/route
ratchet lists are unchanged because no permission or route name changed.

Only `product/products/index.blade.php` is removed with `git rm`. The backend
inventory is **10** files; there are **0** app-shell extending views and **3**
plain-layout extending views. `layouts/app`, `inc/admin_nav` and
`inc/admin_sidenav` have no remaining extender; the unused shell still includes
both partials and all three remain for the next step. Downloads, invoice and
`layouts/layout` stay. Route registrations remain **1,046** `/temp`, **434**
`/temp/admin`; the retained editor inventory remains 8 fields in 8 views.
Coexistence tests now use actual retained public/seller/auth Blade pages;
unused-shell logo/sidebar readers remain tested directly until shell deletion.
The explicit full gate and affected Chromium results are recorded in the ledger.
Every shipping commit, PR title and merge message uses `[skip ci]`; PR CI,
PR/merge SAST and deploy do not run. No attestation trailer is written by hand.


The explicit full pre-commit gate passed: **333 Vitest files / 2,575 tests**
(**299.54s**), Pint, PHPStan (**0 errors**) and **6,322 Pest tests / 36,783
assertions** (**348.82s**, five processes), plus Wayfinder, whole-repo
lint/format/types and config clearing. The normal production build and all
**10 affected Chromium cases** passed. Develop baseline: **326 Pest tests /
2,816 assertions**. The ledger separately records failed attempts, runtime
repairs and skipped CI-only groups. No attestation was used.

## 39. Retire the unused admin shells (2026-10-20)

[PR #450](https://github.com/arb-rajab/warehouse_management/pull/450) is the single
PR into `develop`. Implementation commit **630b2a49** passed the explicit full
pre-commit gate and all seven affected Chromium cases. This documentation-only
`[skip ci]` follow-up records the PR number before the owner-authorized merge.

Implemented on `codex/step-39-retire-shells` from freshly fetched `origin/develop`
**ee668d6e** (PR #449). Model: **GPT-6 Codex**; the exact model variant and
configured reasoning effort are not exposed by this runtime. The
[step 39 ledger](../store-backoffice-reconciliation-20261011.md#retire-the-unused-admin-shells-step-39-2026-10-20)
records actual verification and shipping, including skipped checks.

**Completion and deletion proof.** The back-office Blade to Inertia conversion
is complete. Searches covered `app`, `routes`, all `resources/views` (including
seller, frontend, auth, emails and PDFs), `config`, `tests`, `public/assets/js`
and `.ai`. No application extends, includes or renders the three dead shells.
Before deletion, only the dead layout itself included its two navigation
partials; the two explicitly identified tests rendered them directly. Other
matches in `.ai` are historical evidence, preserved as such. Those test pins
are rewritten against live surfaces. `git rm` removes exactly:

- `resources/views/shop/backend/layouts/app.blade.php`;
- `resources/views/shop/backend/inc/admin_nav.blade.php`;
- `resources/views/shop/backend/inc/admin_sidenav.blade.php`.

The empty `inc/` directory disappears. Inventory is **10 → 7** backend Blade
files, **0** removed-shell consumers and **3** plain-layout extenders. No menu
data, gates, routes, business behavior, dependency or lockfile changes.

**Test contracts.** `StoreBackOfficeShellTest` now rejects resurrected shells
and dot/slash references anywhere in executable app/route/resource/config/test
sources or public JS, and retains the plain-layout/PDF consumer assertions.
It also explicitly protects both seller SKU fragments. The settings test was
actually pinning saved logo/site-name readers, not timezone/CSP/head behavior:
it now reads the saved settings and both preview URLs from the Inertia settings
screen. Mobile API and protected login readers remain checked. Only the removed
sidebar's `alt="Holland Store"` and shell's `&copy; Holland Store` markup
assertions are dropped; Inertia uses the established translated brand and
configured application title. Existing timezone and strict-CSP cases remain.
The visit test now pins `HandleInertiaRequests` permissions for ordinary staff
with/without only `view_representative_visits`. Vitest checks the actual
`visibleStoreMenu` result in both cases, including the visit URL, label,
permission and Inertia-link flag. No unrelated product permission is required.

**Asset audit: what the deleted layout loaded first.** Its head loaded external
Poppins CSS from Google Fonts, then `assets/css/vendors.css`, conditional
`bootstrap-rtl.min.css`, and `aiz-core.css`. Its body loaded
`assets/js/vendors.js`, then `aiz-core.js`, followed by `@yield('script')`.
It defined inline body font sizing, `AIZ.local` uploader translations, flash
notifications, a language-change POST handler and `menuSearch()`, and yielded
`content`/`modal`. Its navigation partials loaded no additional JS/CSS files.
It had no `@push`/`@stack`; no retained backend view defines a script section.

Each asset name was searched across seller, frontend, auth, emails and PDF
views, then all views/public JS for indirect users. **No public asset is proven
admin-shell-only, so no asset file is deleted.** Remaining consumers:

| Asset | Retained consumers / reason it stays |
| --- | --- |
| `js/vendors.js`, `js/aiz-core.js` | Seller layout; frontend layout; plain backend layout used by seller login, delivery-boy login and 503. The standalone bKash app also loads vendors.js. jQuery/Bootstrap, uploader, widgets and notification behavior remain needed. |
| `css/vendors.css`, `css/bootstrap-rtl.min.css` | Seller, frontend and plain backend layouts (RTL CSS conditional); standalone bKash/mobile custom-page views also use vendors.css. |
| `css/aiz-core.css` | Frontend and plain backend layouts, standalone bKash app and mobile custom pages. Seller uses its own aiz-seller.css. |
| `js/intlTelutils.js` (not loaded by the removed shell) | Auth login/register/password email, frontend login/registration/create-user and frontend layout telephone widgets. |
| `css/aiz-seller.css` (not loaded by the removed shell) | Seller layout. |
| `css/custom-style.css` (not loaded by the removed shell) | Frontend layout, bKash app and mobile custom page. |
| Summernote in `js/vendors.js`, initialized by `aiz-core.js` `textEditor()`; CSS/fonts in `css/vendors.css` and `css/font/summernote.*` | Eight editor fields in eight seller/frontend views: seller physical/digital product create/edit, seller ticket reply, frontend customer product create/edit and customer ticket reply. Bundled Chart.js/AIZ chart helper also stays for `shop/seller/dashboard.blade.php`. |

Emails and PDF views have no direct consumers of those bundles; invoice and
reference-download PDF styling remains self-contained. Auth views inherit the
frontend layout; seller/delivery-boy login and 503 inherit the plain layout.
The shared font/image references inside retained CSS stay with it.

**Blade helpers.** Deleting the shell removes its own inline helpers and script
outlet. Seller `menuSearch()` and its sidenav caller remain; seller/frontend
`AIZ.local`, flash notifications and language handlers remain. Script sections
in seller, frontend and auth views are consumed by their retained layouts;
login script sections are consumed by `layouts/layout`. None is removed.
`HandleStoreBladePages` and `store.blade_csp` are still required for their
inline scripts and full-load Inertia handoffs (§§2.5–2.6).

**What stays Blade on purpose and why:**

- Storefront and customer area: public commerce/customer UI, outside this migration.
- Seller panel: independent seller workflows, including both backend SKU fragments.
- Auth pages and 503: login/recovery/registration and error flows; retain the plain backend layout and Blade CSP/coexistence handling.
- Emails: server-rendered mail templates.
- PDFs: invoices and bulk-upload reference downloads; retain all four backend PDF templates.
- `resources/views/app.blade.php`: the Inertia root document, required to boot Vue.

Verification and the final PR reference are recorded in the ledger. Every
shipping commit, PR title and merge message uses `[skip ci]`. PR CI/SAST/deploy
do not run. No attestation trailer is written by hand.

The explicit full pre-commit gate passed with no skipped stages: **333 Vitest
files / 2,577 tests**, **6,323 Pest tests / 36,823 assertions**, whole-repo
lint/format/types, Pint, PHPStan (0 errors), Wayfinder and config clearing.
The unchanged develop baseline passed **118 Pest tests / 772 assertions**.
The normal production build passed; route listings are identical as
parsed JSON (**1,046** `/temp`, **434** `/temp/admin`). CI-only groups outside
`--full` and the affected browser results are identified separately in the ledger.
All **7 affected Chromium cases / 3 files** passed (**27.8s**), covering the
Inertia menu/settings, strict CSP, logo/timezone behavior, Arabic RTL/dark,
Blade login/logout handoffs and retained seller uploader. No timeout or test
budget was changed.

## 40. Launch with Super Admin only: permission sync, fetch gate and login landing (2026-10-20)

Model: **claude-sonnet-5-5**, effort **high**. Shipped in
[PR #452](https://github.com/arb-rajab/warehouse_management/pull/452) into
`develop`. Starts from `origin/develop` **b0384e6e** (PR #451, the
[role-grants audit](../role-grants-audit-20261020.md)). Three separately
committed changes close audit risks 3, 4 and 2. The
[step 40 ledger](../store-backoffice-reconciliation-20261011.md#permission-sync-fetch-gate-and-login-landing-step-40-2026-10-20)
records the verification and the shipping, including what was not run.

**Launch decision.** Nobody uses production yet, so the owner launches with
**only the Super Admin role**: `Gate::before()` in `AuthServiceProvider` passes a
Super Admin through every gate. Staff roles are created later in the app's own
role screen (`/temp/admin/roles`), from the permissions the sync below defines.
No migration, seeder or command in this step creates a staff role, assigns a
permission to a role or grants one to a user; `StoreRoleSeeder` still creates
exactly `Super Admin` and `Clerk` (since [§41](#41-go-live-hardening-seeders-the-first-admin-the-production-env-template-eventcache-and-migrate---pretend-2026-10-20) `Clerk` only in `local` and `testing`).

**Permission sync.** `App\Support\StorePermissions` is the one versioned list
(`name => section`). `php artisan store:sync-permissions` inserts the rows it
lacks, the migration `2026_10_20_120000_sync_store_permissions.php` calls the
same method, and so does `StoreRoleSeeder` (fresh local and e2e databases). It
only inserts missing `store`-guard rows (`guard_name`, `section`,
`created_at`/`updated_at`); it never updates, renames or deletes a row, never
reads or writes the `web` guard, grants nothing to any role or user, and
forgets spatie's permission cache after a write. Running it again creates
nothing. The migration's `down()` preserves data, like the other definition
migrations. Production, in this order:

1. `php artisan store:sync-permissions --dry-run` lists what it would create
   and writes nothing (and leaves the cache alone);
2. `php artisan store:sync-permissions` creates them and prints
   `N created, M already existed`.

The deploy script runs `artisan migrate --force`
(`.github/scripts/deploy-remote.sh:326`), so the migration defines the rows on
deploy without step 2; the command is for a later list change and for checking
first. Existing rows keep their section, so a production database that already
holds some of these names under another section name is not regrouped.

**The list: 182 names = 38 + 144.** Every name in the audit JSON whose status is
`enforced` (181) plus `delete_truck` (defined by the logistics migration,
enforced nowhere, kept). 38 were already defined by migrations and keep the
section those migrations assigned; **144** existed only in production data, if at
all. `RoleController::REMOVED_ADDON_SECTIONS` stay out, and no addon permission
was invented. A section is the sidebar module (`storeAdminMenu.ts`, the same
groups the legacy sidebar had) of the route or controller that enforces the
name, using an existing section name wherever one fits:

| Section | Names | Source of the name | Covers |
| --- | --- | --- | --- |
| `dashboard` | 1 | new | the admin dashboard |
| `product` | 33 | only named in the section migration's docblock | Products menu: product lists, digital products, bulk import/export, categories, brands, attributes, colours, reviews |
| `sales` | 14 | order-entry migration (5) | orders and their status writes, order configuration |
| `customer` | 13 | customer migration (1) | customers, notifications, classified products and packages (the Customers menu) |
| `representatives` | 10 | migrations | packages, payments, visits |
| `seller` | 11 | new | seller list and accounts, payouts, commission, verification form |
| `trips`, `trucks`, `drivers` | 4 each | migrations | logistics |
| `uploads` | 4 | migration | uploaded files |
| `offers_catalog` | 3 | migration | offers catalogue |
| `blog` | 9 | blog migration (1) | posts and categories |
| `support` | 7 | support migration (1) | tickets, conversations, product queries |
| `reports` | 7 | new | the seven report pages |
| `marketing` | 21 | new | flash deals, offer deals, pop-ups, newsletters, subscribers, coupons |
| `website_setup` | 7 | new | header, footer, pages, appearance |
| `setup_configurations` | 22 | migration (1: `google_map_setting`) | settings pages and the shipping pages nested under Setup & Configurations |
| `staffs` | 8 | new | staff and staff roles |

Six section names are new (`dashboard`, `seller`, `reports`, `marketing`,
`website_setup`, `staffs`); the other twelve already existed. The legacy
section vocabulary lives in the production database and is not in the
repository (a legacy `Permission` row took a free-text `parent`), so these
names are the sidebar's module names, not a copy of production's. **Least
certain:** the order permissions under `sales` (the order-entry migration's
section) while older test fixtures show `order` for `view_all_orders`; and the
size of `product` (33), which follows the Products menu rather than splitting
brands, categories or attributes out.

**Guard test.** `StorePermissionsTest` scans `routes/shop-admin.php`, the
`permission:` middleware, settings map, inline `can()`/`Gate::authorize()` and
`$permission = match` selectors of `app/Http/Controllers/Store`, and
`storeAdminMenu.ts` for permission names, and fails naming any that
`StorePermissions` lacks, so a new module cannot add an undefined gate. It also
requires all three sources to contribute and over 150 names, so a regex that
stops matching cannot pass. A new gate means adding its name to the list in the
module's section, in the same change. The same file, the command, migration and
seeder tests pin: a second run creates nothing, an existing row's section is
never changed, no role grant is created, the `web` guard is untouched, and
`--dry-run` writes nothing. `RoleControllerTest` also asserts that a migrated
database offers the whole list in the role form, grouped by these sections.

**`products.fetch`.** `POST /products/fetch` (root `ProductController::fetch`,
queues the catalogue import) now carries `permission:add_new_product` on its
route, keeping its name. That is the permission the Inertia products list shows
the button under (`Products/Index.vue`) and the legacy Blade list showed it
under. `LegacyAdminPermissionsTest` listed `ProductController@fetch` as an
ungated "read-only AJAX helper"; it is gated now, so that entry came off the
ratchet (the test fails on a gated route that is still listed).
`ProductControllerTest` pins refusal (guest, customer, staff without it, staff
with other product permissions) with no `cron_jobs` row created, and success for
`add_new_product` and for a Super Admin.

**Login landing.** `Auth\LoginController::authenticated()` sends admin and staff
accounts to `shop.admin.dashboard` when they can `admin_dashboard` (Super Admin
through `Gate::before()`), and to `profile.index` otherwise. The profile page is
the signed-in account's own page; `ProfileController::middleware()` is empty and
the route sits only behind `auth` and `admin`, so any admin or staff account can
open it (pinned: a staff member with no permissions gets a 200 there and a 403 on
the dashboard). Seller and customer redirects are unchanged and pinned.
Accountants, whom `IsAdmin` admits but this redirect never matched, are not
changed.

Not verified: production data (which of these rows exist, under which
sections), and the MySQL migration group, which runs in CI or an attested run
only. The command and migration ran on scratch SQLite, not MySQL.

## 41. Go-live hardening: seeders, the first admin, the production env template, event:cache and migrate --pretend (2026-10-20)

Model: **claude-sonnet-5-5**, effort **high**. Branch `claude/go-live-hardening-fdk4me`
from `origin/develop` **2e1f097** (the PR #453 merge, which added the
[go-live checklist](../go-live-checklist-20261021.md)). The back-office
migration is complete and nobody uses production yet; the checklist found five
problems that code can fix, and this PR fixes them as five separate commits plus
a documentation commit. It deliberately does **not** carry `[skip ci]`: about 40
earlier PRs merged without CI, so this is the first PR to run the full `tests`
workflow and SAST. The [ledger entry](../store-backoffice-reconciliation-20261011.md#go-live-hardening-step-41-2026-10-20)
lists the checks that ran.

**1. Seeders refuse production (checklist F1, 4.5, 8.9).** Only `DatabaseSeeder`
refused production, so `db:seed --class=StoreUserSeeder --force` created
`store-admin@example.com` / `password` as a Super Admin there.
`Database\Seeders\Concerns\RefusesProduction::refuseInProduction()` is the one
guard (`app()->isProduction()`, a `RuntimeException`, called first in `run()`), now
used by `DatabaseSeeder`, `StagingRealisticDataSeeder` (both had the same inline
check), `StoreUserSeeder`, and the two seeders that create mobile users with the
factory's known password, `CellStatusLogSeeder` and `CellVerificationRoundSeeder`.
`StoreRoleSeeder` keeps creating `Super Admin` and the permission rows in every
environment (the first-admin command and local setup need them) and creates the
`Clerk` demo role only when the environment is `local` or `testing`; nothing in
`app/`, `routes/`, `config/`, `database/migrations` or the Blade views refers to
`Clerk` (only tests and e2e fixtures do, under `testing`). Playwright seeds with
`APP_ENV=testing` (`playwright.config.ts`), so `loginAsStoreAdmin()` and the e2e
fixtures are unchanged; the Playwright seed path was run by hand under
`APP_ENV=testing` on scratch SQLite. **Not guarded, on purpose:** `ProductSeeder`,
`RowSeeder`, `PalletSeeder`, `CellVerificationReportSeeder` and `PageSeeder` write
demo data but no accounts. Calling one by name in production still works and is
still wrong (checklist 8.9); guarding them is a follow-up if the owner wants it.
`SeedersRefuseProductionTest` runs each guarded seeder in `production` against a
filled database and asserts no account, role, grant or log row changed.

**2. `store:create-admin {email} {--name=} {--promote}` (F9, 4.4).** After
`migrate` there is no `Super Admin` role and seeders must not run, so the checklist
carried a tinker recipe. The command, in one transaction: creates the `Super
Admin` role (guard `store`) if missing; creates the store user (`user_type`
`admin`, `email_verified_at` set, email lower-cased, name from `--name` or the part
before the `@`); assigns the role. Decisions:

- The password is read only from `secret()` prompts (enter, then confirm), so it is
  in no argument, option, environment variable, shell history, process list, log
  or output. Minimum 12 characters; the production `Password::defaults()` rule
  (mixed case, digit, symbol, breach list) is **not** applied, because the breach
  check calls an external service. A mismatch, a short or an empty password
  (which is what `--no-interaction` yields) writes nothing and exits 1.
- An email that already belongs to a store user (compared case-insensitively) is
  refused with nothing written, before any prompt. `--promote` then only assigns
  the role: no prompt, no change to the password or other fields, and it is a
  no-op if the user already holds `Super Admin`. This command adds one limit the
  brief did not ask for: `--promote` refuses an account that is banned or whose
  `user_type` is not `admin`, `staff` or `accountant`, because `IsAdmin` would
  answer it 404 and a customer or seller account holding Super Admin would pass
  every `Gate::before()` check elsewhere.
- Permissions: if `wms_permissions` has no `store` row at all it runs the same
  `StorePermissions::sync()` (inside the transaction); if only some are missing it
  prints the exact `store:sync-permissions` command and writes nothing; if none
  are missing it says nothing. It never gives any permission to any role or user;
  the only grant it can write is the one `Super Admin` assignment.
- Output lists what was created or already existed. It avoids `<...>` around the
  email: the console formatter reads that as a style tag and silently drops it
  (found by the test).

`CreateStoreAdminCommandTest` covers a new user, an existing email refused,
`--promote` (and its refusals), a confirmation mismatch, a short and an empty
password, an invalid email, no password argument or option, `APP_ENV=production`
while `StoreUserSeeder` refuses, a synced and an unsynced permission table, a
rollback when the user cannot be saved, and the grant tables.

**3. `.env.production.example` (F3, checklist §2).** `.env.example` is unchanged
(local development and CI depend on it). The new file holds production values and
a comment on every key checklist §2 flags: `APP_ENV=production`, `APP_DEBUG=false`,
a placeholder `APP_URL`, `SESSION_SECURE_COOKIE=true`, `CSP_REPORT_ONLY=false`
(the comment says there is no report endpoint, so the browser console is the only
signal), `TELESCOPE_ENABLED=false`, SMTP mail, MySQL, database drivers for
session, cache and queue, daily logs, the backup and health settings. It holds
only placeholders: secrets are empty or `CHANGE_ME`. Optional keys whose empty
value would override a safer default (`HEALTH_TO_ADDRESS`,
`BACKUP_NOTIFICATION_EMAIL`, `TRUSTED_PROXIES`) are commented out, because `KEY=`
sets an empty string, not "unset". `PULSE_ENABLED` is left `true` and marked as the
owner's decision (checklist 2.12). `BROADCAST_CONNECTION`, in `.env.example`, is
omitted: nothing reads it. `ProductionEnvExampleTest` pins the safe values, the
absence of anything secret-looking, that every key (active or commented as a bare
`# KEY=value`) is read by `env()`/`Env::get()`/`import.meta.env` somewhere, and
that, loaded as the process environment, the template yields production,
`debug=false`, secure cookies, an enforced CSP, Telescope off and SMTP mail in the
real config. README Deployment and checklist §2 point to it.

**4. `event:cache` in the deploy (F4, 5.4, 5.9).** The caches are in the
repo-owned `.github/scripts/deploy-remote.sh`, not only in a workflow, and the
existing `deploy-remote-test.sh` covers that script. It now runs `config:cache`,
`event:cache`, `route:cache`, `view:cache` (the order `artisan optimize` uses)
after `migrate` and before the release is published, inside the live window, so a
failure rolls back like any other step. The test stub logs every artisan call; two
new cases pin the order (before `queue:restart`, the first command after the
flip) and the rollback on `FAIL_EVENT_CACHE`. No workflow file was edited.
Production health's `OptimizedAppCheck` therefore passes from the first deploy
made by the updated script. The change under `.github/` makes CI run every group.

**5. `migrate --pretend` (F2, 3.4).** Cause: `Migrator::pretendToRun()` pretends on
the connection returned by the migration's `getConnection()`. The health
migration wrote to the `health` connection with `Schema::connection()` but did not
name it (Telescope's migration does), so the migrator pretended on the default
connection only and the health table was created for real; the real `migrate`
afterwards then failed with "table already exists". Fix: the migration now
overrides `getConnection()` (small and safe: the same pattern as Telescope's; a
real run behaves as before, now inside a transaction on the health connection).
`CreateHealthTablesTest` runs `migrate --pretend` and then `migrate` against
scratch SQLite files and asserts the pretend run printed the health SQL and
created nothing but `wms_migrations`. **Not fixed, because app code cannot:**
`migrate --pretend` still installs the empty migration table (Laravel's own
`prepareDatabase()`), so it remains unsafe before the one-time `migrations` →
`wms_migrations` rename on an existing database. The checklist records the safe
alternative, `migrate:status --pending`, which writes nothing.

**Behaviour changes, for the next person:** `db:seed --class=<account seeder>`
now throws in production; `StoreRoleSeeder` no longer creates `Clerk` outside
`local`/`testing` (staging included); `store:create-admin` is new; the deploy runs
one more artisan command and fails the deploy (with rollback) if it fails; the
health migration names its connection. No route, permission, schema or data
change. Still open for the owner (checklist §12) and not touched here: everything
that depends on systems the repository cannot show, the accepted-risk register,
the payment-gateway and fetch-button decisions, and the unguarded demo seeders
above.

## 42. Storefront SEO: canonical, robots, sitemap and JSON-LD (2026-10-20)

Model: **claude-sonnet-5-5**, effort **high**. Branch `claude/storefront-seo-bwo5h0`
from `develop` **74830af** (the PR #454 merge). Scope: the public storefront, which
stays Blade (search engines need server-rendered HTML). No visible content, URL,
route name, panel behaviour or sanitization changed. Five commits, one per item,
plus this documentation, shipped in [PR #455](https://github.com/arb-rajab/warehouse_management/pull/455);
the owner runs CI separately, so every commit, the PR title and the merge commit carry `[skip ci]`. §7 recorded the gaps; each was re-read in the code before changing it
and was right: the layout printed `robots=index, follow`, `og:url=route('home')`,
`twitter:card=product` and the `@publisher_handle` / `@author_handle` placeholders on
every generic page; there was no canonical, JSON-LD or hreflang; and `/sitemap.xml`
returned `base_path('sitemap.xml')`, which is the *string* of a path that nothing
writes (no command or job generates it), so the route never worked.

**Corrections to the brief.** (1) The storefront is under `/temp`, so the sitemap
is `/temp/sitemap.xml`; only `robots.txt` must sit at the domain root. (2)
`digital_product_details` is one of the 14 views but no route renders it
(`HomeController::product()` always renders `product_details`), so its test serves
it from a route the test registers. (3) Every price on the storefront is shown only
to a signed-in, admin-verified account (`Auth::check() && Auth::user()->admin_verified`
in `product_details/details.blade.php`, `product_box_1`, listings, cart), which
changed what the JSON-LD may say (below). (4) Several `og:url`s were not only
generic: the classified product page pointed at the *ordinary product* route, and
CMS / policy pages used `URL($slug)`, which lacks the prefix.

**1. Meta foundation (`App\Support\Seo`, `partials/seo_meta.blade.php`).** The
layout asks `Seo::head()` for everything in `<head>`; views keep the `meta_title`,
`meta_description`, `meta_keywords` and `meta` sections and may add `meta_image`,
`og_type`, `robots` and `canonical`. The 14 views no longer print `og:url`, twitter,
`itemprop` or placeholder tags themselves: the layout owns them (and still prints the
`itemprop` name / description / image once, as before, because tests assert them; they are
Google+ leftovers outside any `itemscope`), so none can name another page. They keep only
price extras (`product:price:currency`, `og:price:amount`, `twitter:data1`).

- **Canonical** = the origin of `APP_URL` + the request path (no trailing slash) +
  the content-changing query keys in a fixed order (`Seo::CONTENT_QUERY_KEYS`: `page`,
  `keyword`, `sort_by`, `min_price`, `max_price`, `brand`, `category`, `color`,
  `condition`, `rating`, `search`, `selected_attribute_values`, `selected_categories`,
  `seller_id`); `page=1` and empty values are dropped; `utm_*`, `fbclid`, `gclid`,
  referral codes and every other key are dropped. `og:url` is the same string. A
  filtered or sorted URL is its own canonical (it *is* different content) and is kept
  out of the index by robots, not by a canonical pointing elsewhere: a `noindex` page
  that canonicalizes to an indexable one tells Google to index neither. The one
  deliberate exception is a policy `Page` also served at its slug by the
  `/{slug}` catch-all: its canonical is the policy route (`Page::POLICY_ROUTES`).
- **APP_URL, not the asked host,** is the origin, so every alias of the site names
  one address (the go-live checklist, 11.4, makes the redirects). With no host in
  `APP_URL` it falls back to the request.
- **Fallbacks.** A blank section value falls back to the settings (`meta_title`,
  `meta_description`, `meta_keywords`, `meta_image`), and `<title>` to
  `website_name | site_motto`; before, a product without a `meta_title` printed an
  empty `<title>` and empty `og:title`. `og:image` is the page's own upload, else the
  settings image; the placeholder image never counts as an image. Product pages use
  `meta_img`, then the thumbnail.
- **Twitter.** `summary_large_image` when an image exists, else `summary`. **No
  `twitter:site` / `twitter:creator`**: no setting holds a handle (`twitter_link` is a
  profile URL), and none was invented. `og:type` is `website`, `product` (the product
  pages; the old `og:product` was invalid) or `article` (blog).
- All values are decoded once from the section and escaped once by the template.

**2. Robots.** `Seo::robots()`; a view's own `robots` section wins.

| Page | Directive |
| --- | --- |
| Home, product, category / brand, `?page=N` of a plain listing, brands / categories index, blog index and published posts, custom and policy pages, shops, classifieds | `index, follow` |
| **Listings rule:** internal search (`search`, with or without a keyword) and any listing carrying one of the content keys other than `page` (a sort, a price range, a brand / attribute / category / rating / condition filter, a blog search) | `noindex, follow` |
| Login, register, password reset, email verification, `users/login`, `users/registration`, seller and delivery-boy login, cart, compare, order tracking, `checkout.*`, order confirmation; every route behind `auth`, `user`, `customer`, `seller`, `admin`, `rep_or_admin`, `verified`, `unbanned` or `signed` (dashboard, profile, purchase history, wallet, ...); the 403 / 404 / 500 views; an unpublished blog post | `noindex, nofollow` |

The rule for listings: the plain listing and its pages are what we want indexed;
every filtered or sorted variant duplicates it, but its links to products are still
worth following, hence `noindex, follow`. Tracking-only or empty parameters do not
make a variant. Search results and filtered listings are **not** in `robots.txt`: a
crawler that may not fetch a page cannot see its `noindex`.

`SendNoIndexHeader` sends `X-Robots-Tag: noindex, nofollow`: always on the store
back office (`routes/shop-admin.php`), the seller area, the four store API files and the
`api` group (so also on a guest's redirect and on a 401), and on every Inertia response
(root view `app`, or an Inertia visit) through the `web` group, which is the WMS back
office and the Inertia store admin. It sits in the middleware priority list ahead of
`auth`, otherwise the 401 / redirect `auth` throws is produced outside it. The
backend and seller Blade layouts (seller login, the 503 page) print a robots meta. A
guest's redirect from a WMS page to its login carries no header (only Inertia pages do);
the login page does.

`robots.txt` is a route (`RobotsController`, name `robots`), not a file: a file in
`public/` is served by the web server before Laravel, so `public/robots.txt` was
deleted and a test fails if it returns. The Disallow lines come from `route(..., false)`
(the storefront prefix follows the routes) and the `Sitemap:` line from `APP_URL`. The
storefront stays open; disallowed: `/admin`, the store back office, `/password/change`,
`/health`, cart, checkout, the account pages, `<storefront>/seller/` (with the slash:
`/seller-policy` is public), `<storefront>/api/` and `/api/`. Account paths are prefix
matches, so a CMS page whose slug *starts with* one of them (e.g. `dashboard-info`)
would be blocked.

**3. Sitemap (`App\Services\Sitemap`, `SitemapController`).** `GET <storefront>/sitemap.xml`
is always a `<sitemapindex>`; its files are `sitemap-{source}-{n}.xml` for `static`
(home, brands / categories / blog index, the policy pages that exist), `pages`,
`categories`, `brands`, `products`, `blog` and `classifieds`, each file at most 50,000
URLs (`MAX_URLS_PER_FILE`; about 150 bytes a URL, so the count binds, not the 50 MB).
Content type `application/xml`. Visible content only:

- **Products:** `filter_products()` (the listing's own rules: published, `auction_product = 0`,
  in-house, or from a *verified* seller while the vendor system is on) **and `approved = 1`**
  (the product page 404s otherwise, though `filter_products()` does not check it when the
  vendor system is off), with a non-empty slug. Digital products are ordinary products on
  this storefront and follow the same rules. Classifieds: only while `classified_product = 1`,
  `published = 1` and `status = 1`.
- **Posts** `status = 1` and not soft-deleted; **categories** except `Uncategorized`
  (as `all_categories` hides it for non-admins), **brands** and **pages** with a slug; a
  policy page is listed at its policy route, never at its slug; the `home_page` row is not.
  Not listed: shops, flash deals, coupons, compare, anything private.
- **`lastmod`** from `updated_at` for posts, pages, categories and brands; **never for
  products**: `products:sync` upserts every product in the feed each hour and Eloquent
  bumps `updated_at` with it, so the date would claim everything changed at the last sync.
- Explicit column selects on the base query builder (no hydration, no `$with` translations),
  `forPage()` + `cursor()` per file, so the queries per file do not grow with the rows (a test
  pins this). Every URL is `APP_URL`'s origin + `route(..., false)`.
- **Cache** (the default store, `Sitemap::TTL` = 3600 s, nothing evicts by hand; a change
  shows within the hour): `sitemap.counts` (URLs per source) and `sitemap.{source}.{page}`
  (the rendered file). The index is rebuilt from `sitemap.counts`. A page outside
  `1..ceil(count / 50,000)` or an unknown source is a 404 *before* any cache key is written.
- Not in the sitemap on purpose: images, news, hreflang alternates.

**4. JSON-LD (`App\Support\StructuredData`, `partials/json_ld.blade.php`).** Printed only as
`json_encode(... JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)` inside
`<script type="application/ld+json">`. Types: **Product** (name, url, up to 5 images, plain-text
description (sanitized, stripped, 500 characters), sku (first stock sku, else `mat_id`), brand,
`offers`, `aggregateRating`) + **BreadcrumbList** (Home, the category chain, the product) on
product pages; **WebSite** with a `SearchAction` (`<storefront>/search?keyword={search_term_string}`,
a real GET route) + **Organization** (name, url, `header_logo` when set) on the home page;
**BreadcrumbList** on category and brand listings (none for search or the plain listing);
**BlogPosting** (headline, description, `datePublished`, `dateModified`, image, publisher,
`mainEntityOfPage`) on posts.

- **Offers follow the page.** `offers` (price in the default currency with tax and discount,
  `priceCurrency`, `InStock` / `OutOfStock` from the stock rows, digital products always in
  stock) is printed only to a visitor the page shows prices to, and never for variant products
  (the page prints a range). **A crawler is a guest, so production markup carries no price.**
  Publishing prices in markup while the page hides them breaks Google's rule that markup must
  match the page and defeats the gate; making prices public is a business decision for the owner
  (checklist 11.7), not done here.
- **`aggregateRating`** only when the reviews section is on and published reviews exist: the mean
  of those reviews and their count.
- **No author** on `BlogPosting`: `blogs` has no author column.
- Tests: the exact JSON of a product without and with reviews (and with tax, discount and zero
  stock), the home page and a blog post; `</script><script>alert(1)</script>`, quotes, `&` and `<b>`
  in a product, brand and category name cannot leave the script or the JSON.

**The `/temp` prefix.** No new code names it. `Seo`, `Sitemap`, `StructuredData` and
`RobotsController` take paths from the request and `route(..., false)`, so they follow the prefix
when it is removed. It is still named by `RouteServiceProvider`, `bootstrap/app.php` (`redirectTo`)
and the `app-url` meta in the layouts (all established, untouched). Removing it changes every
indexed URL: see checklist 11.8.

**Not done.** `hreflang` (Arabic and English share one URL by session language), AMP, an image
sitemap, Search Console / Bing set-up, real `twitter:site` / `twitter:creator` handles (no setting
exists), `sameAs` social links in `Organization`, prices in markup, redirects from non-canonical
hosts, automatic sitemap invalidation (it expires), per-page `<meta name="keywords">` cleanup (still
printed, as before).

**Found on the way, not fixed (outside this scope; the first three were fixed afterwards, see §43).** The product page divides by `unit_equal` for a
discounted product when it is `0` / `null` (`details.blade.php`, "Price Per Piece": a 500 for a verified
customer); `top_selling_products.blade.php` lazy-loads `product_translations` and builds `route('product')`
for products without a slug (a 500 in `local` / `testing`, where strict mode is on, or with a slugless
product); the policy and blog views read `$page` / `$blog` without a null check (a missing row is a 500
rather than a 404); the two seller-shop views printed `$shop->meta_img` in `twitter:image`, a column
`shops` does not have (that tag is gone). **Fixed because a test
needed it:** the product page's review list lazy-loaded each review's `user` (a 500 under strict mode with
two or more reviews); it now eager loads it.

**Evidence: the rendered `<head>`, from a real server.** `php artisan serve` on a scratch SQLite
database (`migrate:fresh --seed`, then one category pair, a brand, a blog post, a custom page, the
settings and slugs added by hand; `APP_ENV=staging`, because `local` turns strict mode on and the
seeded products have no slug, see "Found on the way"), `APP_URL=http://127.0.0.1:8123`. Lines are
as printed, `curl -s`; `og:image` and JSON-LD are omitted where a page had none.

| URL | status | `robots` | `canonical` = `og:url` | `og:type` | `twitter:card` |
| --- | --- | --- | --- | --- | --- |
| `/temp` | 200 | `index, follow` | `http://127.0.0.1:8123/temp` | website | summary |
| `/temp/product/salty-crisps` | 200 | `index, follow` | `http://127.0.0.1:8123/temp/product/salty-crisps` | product | summary_large_image (`og:image` = the thumbnail) |
| `/temp/category/snacks` | 200 | `index, follow` | `http://127.0.0.1:8123/temp/category/snacks` | website | summary |
| `/temp/category/snacks?page=2` | 200 | `index, follow` | `.../temp/category/snacks?page=2` | website | summary |
| `/temp/category/snacks?sort_by=newest&utm_source=x` | 200 | `noindex, follow` | `.../temp/category/snacks?sort_by=newest` | website | summary |
| `/temp/search?keyword=crisps` | 200 | `noindex, follow` | `.../temp/search?keyword=crisps` | website | summary |
| `/temp/blog/opening-hours` | 200 | `index, follow` | `.../temp/blog/opening-hours` | article | summary |
| `/temp/about-us` (custom page) | 200 | `index, follow` | `.../temp/about-us` | website | summary |
| `/temp/terms` (policy) | 200 | `index, follow` | `.../temp/terms` | website | summary |
| `/temp/terms-conditions` (the same policy at its slug) | 200 | `index, follow` | `.../temp/terms` | website | summary |
| `/temp/login` | 200 | `noindex, nofollow` | `.../temp/login` | website | summary |
| `/temp/cart` | 200 | `noindex, nofollow` | `.../temp/cart` | website | summary |

`<title>`s were the page's own (`Salty Crisps | Albaraka`, `Snacks wholesale`, `Opening hours`, `About us`,
`Terms & Conditions`) or the settings' `Albaraka Holland wholesale` (home, search, login, cart). Headers:
`/admin/login`, `/temp/admin` (a 302 to the login) and `/temp/api/v2/currencies` each answered
`X-Robots-Tag: noindex, nofollow`. `/robots.txt` (`text/plain`) printed `User-agent: *`, the 24
`Disallow:` lines (`/admin` ... `/api/`) and
`Sitemap: http://127.0.0.1:8123/temp/sitemap.xml`. `/temp/sitemap.xml` (`application/xml`) listed six
files. Product JSON-LD on that page, as printed (guest, so no `offers`):
`{"@context":"https://schema.org","@type":"Product","name":"Cumque Ut","url":"http://127.0.0.1:8123/temp/product/salty-crisps","image":["https://via.placeholder.com/640x480.png/006633?text=in"],"description":"Crunchy crisps","sku":"SKU-1","brand":{"@type":"Brand","name":"Acme"}}`
plus a four-item BreadcrumbList (Home, Food, Snacks, the product).

**Sitemap URL counts on the seeded data** (same database, cache cleared first): `static` 9 (home, brands,
categories, blog, 5 policy pages), `pages` 1, `categories` 2, `brands` 1, `products` 34, `blog` 1 = **48 URLs in
6 files**. The seeder's 33 products have no slug and are not listed (the sitemap, and the product page, need
one); the count above is after giving every scratch product a slug. Before that, `products` was 1 and the total
**15**. A request made before the slugs were added kept answering 15 until the cache was cleared, which is the
one-hour cache working as designed.

**What was verified, and what was not.** Run here: the focused Pest files while developing
(`StorefrontSeoMetaTest`, `StorefrontMetaViewsTest`, `StorefrontRobotsTest`, `StorefrontSitemapTest`,
`StorefrontStructuredDataTest`: 16 + 18 + 71 + 18 + 12 tests, all real routes rendered); the server run above;
and `python3 .claude/scripts/pre_push_check.py --full`, which passed on the final code: ESLint, Prettier,
vue-tsc, Vitest (333 files, 2577 tests), Pint, PHPStan (0 errors) and Pest (**6522 tests, 38091 assertions**).
The first `--full` run failed on two existing tests that assert the blog page's `itemprop` tags
(`MarketingPublicRenderingTest`, `BlogControllerTest`); the tags are printed again and **no existing test was
edited**. Not run: Playwright, the MySQL migration tests, the composer / npm audits and the deploy-script test
(the attested run was not used, so no `CI-Verified` trailer exists); nothing ran on MySQL (the sitemap's
`forPage()` / `cursor()` queries were only exercised on SQLite); no rich-results test, no Search Console, no real
crawler; the headers were read from `php artisan serve`, not from the production proxy. The PR was merged
without CI, SAST or deploy (all skipped on purpose).

## 43. Storefront 500s fixed: unit_equal, top sellers, missing rows (2026-10-20)

Model: **claude-sonnet-5-5**, effort **medium**. Branch `claude/storefront-seo-bugs-dvyhna` from
`develop` **508d883** (the PR #455 merge). Scope: the four defects §42 listed under "Found on the way, not
fixed" as 500s. The storefront stays Blade; no visible content for a valid row, price rule (prices only for a
signed-in, admin-verified customer), URL or route name changed. Shipped in
[PR #456](https://github.com/arb-rajab/warehouse_management/pull/456). Each fix is its own commit, with its test
written first and seen failing. Every commit, the PR title and the merge commit carry `[skip ci]`.

| # | Bug | Fix | Proving test |
| --- | --- | --- | --- |
| 1 | `unit_price / unit_equal` threw `DivisionByZeroError` for a verified customer when `unit_equal` is `0` or `null` (the discounted branch of `product_details/details.blade.php`, and `partials/product_box_1.blade.php`, which every listing card uses). The undiscounted branch was already guarded by `@if (unit_equal > 0)`. | `Product::$price_per_piece` (`pricePerPiece()` accessor): `unit_price / unit_equal`, or `null` unless `unit_equal > 0`. The three views print the per-piece line only when it is not `null`. The value and the `€` formatting for a valid `unit_equal` are the same expression as before. | `StorefrontUnitEqualTest` (0 and null on the product page, discounted and not, and the listing, for a verified customer and a guest; a valid `unit_equal` prints the same `€0.625`), `ProductTest` (accessor: 4, 1, 0, null, negative). Before the fix 4 of 9 failed with `DivisionByZeroError`. |
| 2 | The top-selling widget built `route('product', $slug)` for up to six products, so a product with a null or empty slug threw `UrlGenerationException` (a 500), and it lazy-loaded translations, taxes, stocks and the thumbnail per product. | `Product::topSellingOf($sellerId, $limit = 6)` scope: same seller, `filter_products()` rules, `num_of_sale desc` and limit of six, plus `slug` not null and not empty, a column list of what the view and price helpers read, and eager loads (`product_translations`, `taxes`, `stocks`, `thumbnailUpload`, each column-limited). `uploaded_asset()` takes an optional preloaded `Upload` (the old call is unchanged). | `StorefrontTopSellingProductsTest` (slugless and blank-slug products not listed and the page is 200; order, six-item limit, seller scoping, unpublished and auction products excluded; translated names and tax-inclusive prices under strict mode; same query count for 1 and 6 products), `ProductTest` (scope). Before the fix all 4 failed (a 500, and lazy-loading violations). |
| 3 | `BlogController::blog_details` and the five policy actions (`HomeController`) read a row with `first()` and passed `null` to the view: a 500 instead of a 404. `blog_details` also ignored `status`, so an unpublished post could be opened by its slug while the sitemap excludes it. | `firstOrFail()` on all six; `blog_details` also requires `status = 1`. | `StorefrontMissingRowsTest` (11 tests: missing, unpublished and soft-deleted post; the published post still renders its content; each policy page with and without its row; custom page and mobile page missing). Before the fix 9 of 11 failed. |

**Deliberate behaviour change.** An unpublished blog post (`status = 0`) now answers **404** at
`/temp/blog/{slug}`; before, it rendered with `noindex, nofollow`. The sitemap already left it out. A post
that is published, and every existing policy or custom page, is unchanged. `pages` has **no** status or
visibility column (`id, type, title, slug, content, meta_*, keywords, timestamps`), so no published rule
applies to policy or custom pages; `PageController::show_custom_page` (and `mobile_custom_page`) already
answered 404 for a missing row and only gained tests. The two existing tests that opened an unpublished post
(`StorefrontRobotsTest` "a view can set its own robots directive and it wins", `StorefrontSeoMetaTest` "a blog
post is an article and an unpublished one is not indexable") had that one request replaced by rendering
`shop.frontend.blog.details` directly with the unpublished `Blog`, so they still pin the view's own robots rule;
no other assertion changed.

**Found on the way, not fixed (for the owner; checklist D15).** Each is a 500 (in `local` / `testing`, and with a
slugless row in production) only for a row that lacks the column:

- `route('product', $x->slug)` with a possibly empty slug, unguarded: `product_details/related_products.blade.php:16,27`
  (same defect as the top sellers, same category, plus lazy loading under strict mode),
  `product_details/offered_products.blade.php:21,32`, `partials/product_box_1.blade.php:20,22` (every listing card),
  `index.blade.php:105,107,173`, `flash_deal_details.blade.php:36,38`, `partials/search_content.blade.php:33`,
  `partials/cart.blade.php:58`, `partials/addedToCart.blade.php:56,66`, `partials/coupon_box.blade.php:74`,
  `view_compare.blade.php:26` (also two `Product::find()` per row), `order_confirmed.blade.php:169`,
  `user/order_details_customer.blade.php:159,162`, `user/view_wishlist.blade.php:19,38`,
  `user/customer/dashboard.blade.php:263,279`, `user/digital_purchase_history.blade.php:23`, and
  `digital_product_details.blade.php:477,489,637,648` (no route renders that view).
- `route('products.category' / 'products.brand', $x->slug)` with no guard: `product_details/details.blade.php:88`,
  `all_brand.blade.php:31`, `all_category.blade.php:34`, `product_listing.blade.php:59-85,273`, `index.blade.php:303`.
- Division by a column that can be zero, outside the storefront views: `app/Console/Commands/NewFetchProductsCommand.php:272,491`
  and `AllProductsCommand.php:388,408` divide by the feed's `Unit_Equal` (a console sync, not a page).
  `get_price_per_piece()` (`app/Http/Helpers.php:1391`, used by two API resources) treats 0 and null as 1
  and prints a per-piece price; it was left alone because the API contract is not in scope.

None was fixed: each would hide or replace a product link (visible content) or needs a query change and a
decision on what to show for a product without a slug, so it is not a one-line, risk-free change.

**What was verified, and what was not.** See the final report of the PR for the exact commands. Not run:
Playwright, the MySQL migration tests, the composer / npm audits and the deploy-script test (the attested run
was not used, so no `CI-Verified` trailer exists); nothing ran on MySQL; no real browser or crawler.
PR CI, SAST and deploy did not run (skipped on purpose).

## 44. Required and optional form fields (2026-10-21)

Model: **claude-opus-5-5**, effort **high**. Branch `ccr-df04b098-zzj7j8` from `develop` **1768298** (the
PR #456 merge). Scope: how every form shows which fields are mandatory, a tool that measures the gap
across the whole application, a ratchet that stops it growing in the store back office, and two pilots.
The standing rule is [form-required-fields.md](form-required-fields.md); this section is its history and
plan. Shipped in [PR #458](https://github.com/arb-rajab/warehouse_management/pull/458), with CI (no `[skip ci]`).

**The problem, as found.** Nothing in the rules said how a required field is shown, so no form showed it.
The storefront registration form (`shop.auth.register`) required tax number, addresses and trade license
in HTML though `RegisterController::validator` never asks for them, did not require email or the password,
and had **no company name input at all** although the validator requires `company_name`: that form could
not succeed. `FormField.vue` forwarded `required` to the input but printed no mark, and 92 files use it.

**The convention** (in full in the rule): the server rule is the source of truth; a field it requires
shows a red asterisk (`aria-hidden`) plus visually hidden "required" text and carries `required`; optional
fields are unmarked, or "(optional)" in a form where most fields are required (the request read "most
fields are optional", which contradicts "the few optional ones"; the rule records the reading used);
never both; a form with a required field shows the legend "* Required fields"; a conditional rule
(`required_if`, ...) gets a mark only while its condition holds, and help text.

**What changed, by commit.**

1. **Vue foundation.** `RequiredMark.vue` (asterisk + sr-only `common.required`, `ms-0.5`, `text-red-700`
   / `dark:text-red-400`) and `RequiredLegend.vue` (`common.requiredLegend`); strings in `en.json` and
   `ar.json` (`common.required`, `common.requiredLegend`, `common.optional`). Every field component that
   forwards `required` shows the mark exactly when it is set: `FormField` (`required` became a prop; the
   input still carries the attribute), `StoreSelectField`, `StoreBooleanField` (required by default, as it
   always rendered; new `:required="false"`), `StoreUploadField`, `StoreRichTextField`, `ProductSelect`,
   `FilterNumberField`, `FilterDateField` (new optional `required`), and later `StoreNewsletterRecipients`
   and `StoreOrderSearch` (found by the guard). `ResourceFormPage` renders the legend when a mark inside
   its form is rendered (`lib/requiredFields.ts`, provide/inject; since commit 5 the mark registers itself)
   with a `requiredLegend` opt-out. Existing tests changed only where they asserted a label's text (18
   assertions in 13 files) or `StoreBooleanField`'s props (one).
2. **Blade foundation and the registration pilot.** `<x-required-mark />`, `<x-optional-mark />` and
   `<x-required-legend />` in `resources/views/components/`, through `translate()`. Both views that post to
   `RegisterController::register` (`shop.auth.register` at `/temp/register` and
   `shop.frontend.user_registration` at `/temp/users/registration`, the one the storefront links to) mark
   and require exactly name, company name, email, phone, country, password and its confirmation, with the
   legend first; `shop.auth.register` gains the missing company name input and says "(optional)" on tax
   number, company address, shipping address and trade license instead of requiring them. The OTP-addon
   branch is unchanged (with the addon on, the hidden email input stays unrequired although the validator
   requires email: recorded, not fixed). `StorefrontRegistrationRequiredFieldsTest`: the marks, the
   `required` attributes, the legend, and a 422-style error for each required field left out.
3. **`php artisan forms:audit-required`** (`app/Console/Commands/AuditRequiredFieldsCommand.php`,
   `app/Support/RequiredFields/`): described in the rule. It writes `.ai/required-fields-audit-20261021.md`
   and `.json`, committed.
4. **The ratchet** (`RequiredFieldsRatchetTest`, baseline `tests/Feature/Support/required-fields-baseline.json`,
   111 entries at introduction, about 2.5 s) and the Vitest guard (`RequiredFieldComponents.test.ts`).
5. **The back-office pilot: staff and roles.** The staff role select (`role_id`, required by
   `StoreStaffRequest` / `UpdateStaffRequest`) gains its mark; staff create marks all six fields, staff edit
   leaves the optional password unmarked, the role form marks only the name; the legend shows on all four
   pages. Baseline down to **109**; `Store/Admin/Staff` has no finding in any class. The Blade mark moved to
   a fixed `#b91c1c` when Playwright measured the theme's `.text-danger` at 3.6:1.
6. **Rules**: the rule file, its index row, AGENTS.md item 5, agent-conventions "Forms: required and optional
   fields", js.md, and §3 steps 6 and 10 above. The Boost-managed skills (`boost.json`) were not edited.

**Audit results.** Same tool, same day, on `develop` before this branch and on this branch after both
pilots (158 Vue forms, 81 Blade forms; 257 FormRequests, 1 unparsed; 30 inline validators, 5 unparsed):

| Class | `develop` | This branch |
| --- | ---: | ---: |
| A server-required, no `required` (or no control) | 80 | 69 |
| B server-required, no mark | 308 | 93 |
| C `required`/mark, rule optional or absent | 103 | 99 |
| D conditional rule, static mark | 10 | 10 |
| E control with no server rule | 223 | 227 |
| F unparsed | 23 | 23 |
| **Total** | **747** | **521** |

By priority on this branch: P1 (public storefront) 40, P2 (other storefront, account, seller panel) 325,
P3 (back office) 156. The foundation alone removed 205 class B findings (every `FormField`-based label);
the registration pilot removed 11 A and 4 C (E rose by 4: the "(optional)" registration fields are taken
by no rule, because `RegisterController::create()` never stores them). Where the gap is now:

| Module | Forms | A | B | C | D | E | F |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| seller panel (Blade) | 26 | 14 | 14 | 44 | 0 | 121 | 0 |
| storefront/account (Blade) | 12 | 12 | 13 | 23 | 0 | 43 | 1 |
| storefront (Blade) | 38 | 6 | 4 | 16 | 0 | 30 | 6 |
| storefront/auth (Blade) | 4 | 0 | 0 | 4 | 0 | 13 | 0 |
| Store/Admin/Marketing | 19 | 4 | 18 | 0 | 0 | 8 | 0 |
| Store/Admin/Product | 24 | 1 | 13 | 3 | 0 | 0 | 5 |
| Store/Admin/Setup | 27 | 10 | 7 | 0 | 8 | 0 | 0 |
| Store/Admin/Website | 7 | 9 | 6 | 1 | 0 | 2 | 0 |
| Store/Admin/Customers | 13 | 0 | 4 | 7 | 0 | 0 | 0 |
| Store/Admin/Sales | 9 | 6 | 1 | 1 | 0 | 6 | 2 |
| Store/Admin/Trips | 11 | 3 | 4 | 0 | 0 | 1 | 1 |
| Store/Admin/Languages | 7 | 3 | 0 | 0 | 0 | 2 | 0 |
| 17 other modules (WMS admin, auth, blog, sellers, staff, ...) | 42 | 1 | 9 | 0 | 2 | 1 | 2 |
| server-only (unparsed rule sets) | 0 | 0 | 0 | 0 | 0 | 0 | 6 |

Most Blade C and E findings are endpoints that validate nothing (`Request $request` with no rules: the
conversations, password reset by code, seller products and coupons): the UI's `required` is the only check,
and every field is taken unvalidated. That is a server gap first, a marking gap second.

**Top 20 forms to fix first** (public first, then by high and medium findings; the full list with
`file:line` on both sides is in the report):

| # | Priority | Form | Posts to | High | Medium |
| ---: | --- | --- | --- | ---: | ---: |
| 1 | P1 | `shop/seller/profile/address_edit_modal.blade.php:1` | `seller.addresses.update` | 5 | 0 |
| 2 | P1 | `shop/seller/profile/index.blade.php:258` | `seller.addresses.store` | 5 | 0 |
| 3 | P1 | `shop/frontend/seller_form.blade.php:28` | `shops.store` (seller registration) | 4 | 0 |
| 4 | P1 | `shop/frontend/partials/address_edit_modal.blade.php:1` | `addresses.update` | 3 | 1 |
| 5 | P1 | `shop/frontend/partials/address_modal.blade.php:11` | `addresses.store` | 3 | 1 |
| 6 | P1 | `shop/frontend/shipping_info.blade.php:55` | checkout shipping info | 1 | 0 |
| 7 | P2 | `shop/frontend/user/create_user.blade.php:18` | `store_customer` | 12 | 12 |
| 8 | P2 | `shop/seller/product/digitalproducts/edit.blade.php:11` | `seller.digitalproducts.update` | 9 | 0 |
| 9 | P2 | `shop/frontend/user/customer/product_edit.blade.php:24` | `customer_products.update` | 9 | 0 |
| 10 | P2 | `shop/frontend/user/customer/product_upload.blade.php:11` | `customer_products.store` | 9 | 0 |
| 11 | P2 | `shop/seller/coupons/edit.blade.php:18` | `seller.coupon.update` | 7 | 1 |
| 12 | P2 | `shop/seller/coupons/create.blade.php:20` | `seller.coupon.store` | 7 | 1 |
| 13 | P2 | `shop/seller/product/digitalproducts/create.blade.php:12` | `seller.digitalproducts.store` | 7 | 0 |
| 14 | P2 | `shop/seller/shop.blade.php:21` | `seller.shop.update` | 6 | 0 |
| 15 | P2 | `shop/seller/product/products/create.blade.php:23` | `seller.products.store` | 4 | 5 |
| 16 | P2 | `shop/auth/passwords/reset.blade.php:11` | `shop.password.update` | 4 | 0 |
| 17 | P2 | `shop/seller/product/products/edit.blade.php:24` | `seller.products.update` | 3 | 5 |
| 18 | P2 | `shop/frontend/digital_product_details.blade.php:792` | `conversations.store` | 2 | 0 |
| 19 | P2 | `shop/frontend/product_details.blade.php:162` | `conversations.store` | 2 | 0 |
| 20 | P2 | `shop/frontend/user/support_ticket/index.blade.php:79` | `support_ticket.store` | 2 | 0 |

**The tool, checked by hand on 15 forms.** Each was read against its rules and template:
staff create and edit; role edit; customer create (both ternary branches; the 7 C findings are real:
`ValidatesStoreCustomer` makes company name, tax number, bank account, addresses, country and trade license
`nullable`, the create form requires them); customer edit (six separate forms); WMS user create and edit;
WMS settings; blog post create; currency settings (D: `required_with` / `required_without` with a static
`required`); both registration views; seller registration (`shops.store`: asterisks present, no `required`:
A); the storefront address modal (`AddressController@store` validates only `phone`: C on address, country,
postal code, B on phone); password reset by code (validates nothing: C); the product conversation form
(validates nothing: C); the digital product tax rows. The hand check found seven tool errors, all fixed and
covered by `RequiredFieldsAuditTest`: the `register` route name is the GET (the POST on the same URI is
unnamed); a bare `->validate()` on a validator was read as an unparsable rule set; a hidden `0` fallback
beside a checkbox (`is_admin`) was reported as A and B; a checkbox group `x[]` did not answer a required
`x`; an item rule `x.* => required` was applied to a checkbox list (and then, wrongly, not to per-row text
inputs and single selects); forms in `ResourceFormPage`'s `#after-form` slot and nested `<Form>`s were
merged into the outer form. Known limits are in the rule (script-submitted forms, 139 controls with runtime
names in 47 forms, legends not checked, Blade outside `shop/`).

**Plan.** Each batch is one PR: fix every A to D finding of its forms, test that the marks match the rules
(Vitest for Vue, a rendered-page Pest test for Blade, as the two pilots do), shrink the baseline, and
regenerate the report.

- **Phase 2, public and high-traffic forms** (this order): (a) the six P1 Blade forms: storefront and seller
  address forms, seller registration (`shops.store`), checkout shipping info; give `AddressController`
  real rules where it validates only `phone`, as a deliberate, tested change; (b) password reset by code,
  the conversation, product query and support ticket forms: endpoints with no validation get a FormRequest
  first; (c) the back-office modules with mostly B findings, which are mechanical (a `RequiredMark` in a
  hand-written label): Marketing (18 B), Product (13 B, plus the per-tax rows), Setup (7 B, 10 A, 8 D),
  Website (9 A, 6 B), Trips, Sales, Customers (4 B). The 7 C findings on customer create need an owner
  decision first: either the request starts requiring those fields on create, or the form stops (the rule
  says the second, unless the owner wants the first).
- **Phase 3, the rest**: the seller panel (26 forms, 44 C and 121 E: most of its endpoints validate
  nothing, so it starts with FormRequests), the storefront account pages (12 forms), the remaining E
  findings (dead fields to remove, or rules to add: unvalidated input is a mass-assignment question), the 23
  F findings (convert the 5 non-literal inline rule sets, e.g. `CategoryController@store`, to FormRequests;
  give the unresolved computed actions a resolvable form), legends for dialog forms
  (`provideRequiredFields()`), and then extend the ratchet to the Blade P1 and P2 forms once they are clean.

**Existing tests that changed.** Vitest: 18 label-text assertions in 13 files and one `StoreBooleanField`
props list (commit 1), and `StoreNewsletterRecipients.test.ts` (commit 4). Playwright: six locators in five
specs (`store-carriers`, `store-notifications`, `store-representatives`, `store-seller-settings`,
`store-newsletter-ticket`) that found a required field by its exact label text: they now use the role and
accessible name ("Name required") or the label text with its mark. No assertion was removed or loosened.

**What was verified, and what was not.** Run here, on the final code:
`python3 .claude/scripts/pre_push_check.py --full` (Wayfinder types, ESLint, Prettier, vue-tsc, Vitest
**336 files, 2651 tests**, Pint, PHPStan **0 errors**, Pest **6582 tests, 38350 assertions**); an earlier
`--full` run had stopped at vue-tsc on a type error in an edited Playwright spec, fixed before this one.
Playwright, run separately with the preinstalled Chromium: the whole suite once (127 passed, 6 failed on the
locators above), then the six fixed specs plus the new `store-required-mark.spec.ts` (all passed). Focused
Pest files while developing (`StorefrontRegistrationRequiredFieldsTest`, `RequiredFieldsAuditTest`,
`RequiredFieldsRatchetTest`, the staff and role controller tests). `forms:audit-required` was run on this
branch and on a `develop` worktree. Not run here: the MySQL migration tests, the composer and npm audits and
the deploy-script test (CI runs them; no `CI-Verified` trailer was made), and nothing on MySQL.

## 45. Public forms, phase 2a: address forms and the checkout address pick (2026-10-21)

Model: **claude-opus-5-5**, effort **high**. Branch `ccr-6bf71ecb-5ab58g` from `develop` **9815092** (the
PR #458 merge). Scope: phase 2(a) of §44's plan, the public storefront forms, server rule first. The
reference-data PR (countries, policy pages) had not landed on `develop`, so the country fields build on
`App\Models\Country`. Shipped in [PR #459](https://github.com/arb-rajab/warehouse_management/pull/459), with real CI (no `[skip ci]`). Every redirect, flash message, route, session key,
OTP / captcha / throttle path and URL is unchanged; each form's tests were written first and seen failing.

**Shipped, by commit** (the brief's batches 1 and 2; the PR reached about 30 files, the size the brief set as
the limit, so batches 3 to 5 are the next PR, listed at the end):

1. **Storefront address create and edit, and the checkout address pick.**
2. **Seller panel address create and edit.**

### Rules chosen, per form

| Form (view → endpoint) | Before | Now (request) |
| --- | --- | --- |
| New address, `shop/frontend/partials/address_modal` → `AddressController@store` (`addresses.store`, on the profile, dashboard and checkout pages) | inline `phone => required`, flash "Phone number is required"; everything else written unvalidated; HTML required address, country, postal code, phone | `StoreAddressRequest`: **address** `required\|string\|max:255`, **postal code** `required\|string\|max:255`, **phone** `required\|string\|max:255`, **country_id** required while an active country exists, `integer`, exists in `countries` with `status = 1`; `state_id` / `city_id` nullable, exists and active and belonging to the chosen country / state; `latitude` nullable numeric -90..90; `longitude` nullable numeric, greater than -100 and less than 100; `customer_id` sometimes integer. Error bag `storeAddress`. |
| Edit address, `shop/frontend/partials/address_edit_modal` → `AddressController@update` (`addresses.update`) | same as create | `UpdateAddressRequest`: the same rules without `customer_id`, plus the form's hidden `address_id` (sometimes integer, used only to reopen the form). Error bag `updateAddress`. |
| Checkout address pick, `shop/frontend/shipping_info` → `CheckoutController@store_shipping_info` (`checkout.store_shipping_infostore`, `Route::any`) | `address_id == null` → flash "Please add shipping address"; a non-numeric id reached `findOrFail` (404) | `StoreShippingInfoRequest`: **address_id** `required\|integer`; the same flash when it is missing; ownership stays `AddressPolicy` in the controller, after the empty-cart check, as before. |
| New address (seller), `shop/seller/profile/index` → `Seller\AddressController@store` (`seller.addresses.store`) | **nothing validated**; HTML required address, country, state, city, phone | `Seller\StoreAddressRequest`: **address** and **phone** `required\|string\|max:255`; country as on the storefront; state, city, **postal code** (`nullable\|string\|max:255`) and the coordinates optional, with the same checks. Always the signed-in seller's. Bag `storeAddress`. |
| Edit address (seller), `shop/seller/profile/address_edit_modal` → `Seller\AddressController@update` (`seller.addresses.update`) | nothing validated | `Seller\UpdateAddressRequest`: the same plus `address_id`. Bag `updateAddress`. |

The shared rules are `App\Http\Requests\Concerns\ValidatesAddress::addressRules()` (four requests). They
mirror the back office's `StoreCustomerAddressRequest` except the longitude bound (below). The
storefront and seller update requests (and the storefront create, which may name `customer_id`) check
ownership in `authorize()` (`AddressPolicy::manage`), **before** the input: an invalid payload for another
account's address is still a 403, and a missing id a 404. `Address::fillFromForm()` writes the eight
columns as both controllers did (a column the form leaves out is cleared, as before).

**Why these fields are required.** The order's shipping address (`OrderController::placeOrder`,
`Helpers.php` 1617), the invoice and the delivery read address, postal code, phone and country. Address,
postal code and phone were already required in the storefront HTML, so no customer using the form could
leave them empty; the server now agrees. The country is required **only while an active country exists**
(`Rule::requiredIf(Country::active()->exists())`): carrier-wise shipping (`getShippingCost`) and
`seller_base_carrier_list()` read `$address->country->zone_id` and fail on an address without a country,
but a fresh database has no countries and the form must still save.

**The forms.** Each marks and requires exactly the fields its request requires (`<x-required-mark />`,
`required`, `maxlength="255"` on the text fields), shows `<x-required-legend />` once at the top, labels
every control with `for`/`id`, keeps the input with `old()` and shows each error under its field
(`is-invalid` + `invalid-feedback`) from its own error bag, so the profile form on the same page never
shows an address error. A failed create reopens the "New Address" modal; a failed update re-renders that
address's edit form inside the edit modal (server-side, for an address `AddressPolicy` lets the user
manage; another account's id in `address_id` renders nothing) and reopens it. The country row is a Blade
component, `<x-address-country-field>` (`resources/views/components/address-country-field.blade.php`):
the active countries, highest `priority` first, then by name, with the mark and `required`; with no active
country it prints "No countries are set up yet. You can save the address without one." and no select.
The checkout's address cards are a `<fieldset>` whose `<legend>` ("Shipping address", marked) names the
radio group, under the legend line; a missing choice shows its error above the cards.

### Judgement calls for the owner

1. **Postal code required on the storefront, optional in the seller panel.** The storefront form always
   required it; the seller form has it commented out, so sellers legitimately have none, and nothing
   downstream breaks without it (it prints blank). The address created at registration
   (`RegisterController`) has no street and no postal code, the one a representative's
   `store_customer` creates no postal code; both still check out (the checkout pick needs only the id),
   and only editing one asks for what it lacks.
2. **State and city optional everywhere.** The storefront form does not ask for them (commented out
   before this work); the seller form required them in HTML although an empty `states` / `cities` table
   left nothing to pick, which blocked the form on a fresh database. Consequence to weigh: area-wise
   shipping (`getShippingCost`, `area_wise_shipping`) charges 0 for an address without a city, and
   `Seller\OrderController` matches delivery boys by city name. If area-wise shipping is ever enabled,
   the city must become required (and the storefront field restored).
3. **Longitude limited to the column.** `addresses.longitude` is `decimal(17,15)`: it holds less than 100
   either way, so a longitude of 100 or more was a MySQL out-of-range error (a 500); it is now a validation
   error. The map script's default pin (Sydney, 151.2195) hits it if a customer saves without moving the
   pin. Widening the column (e.g. `decimal(18,15)`) is a migration for the owner to decide; the back
   office's `StoreCustomerAddressRequest` still allows ±180 and would 500 on MySQL for those values.
   **Superseded by §47**: the owner chose the migration; the columns are `decimal(18,15)` and every request
   allows ±180 again.
4. **The country rule follows the data.** While any country is active, every new or edited address must
   name one; deactivating every country turns the requirement off and the forms say so.
5. **A non-numeric address id at checkout** is now a validation error with the usual flash instead of a
   404; a numeric id that does not exist is still a 404, another customer's still a 403.
6. **`customer_id` on the storefront create** is kept (no form sends it; a representative's ordering
   flow may): validated as an integer and authorized by `AddressPolicy` as before.

### Dead fields and markup found

- `checkout_type` (hidden, `shipping_info.blade.php`, always `logged`): no code reads it. **Removed.**
- The storefront modals' commented-out state and city rows: **removed** (dead markup; the fields are
  optional and absent, as before). The seller forms' commented-out postal code is **kept** as it was: see
  judgement 1.
- The seller edit modal closed its padding `<div>` early (a stray `</div>` after the commented postal
  code): fixed with the rewrite.
- Not dead, but new: the edit forms' hidden `address_id` (see above).

### The audit tool and the ratchet

- `BladeForms` now reads a `<fieldset>`'s `<legend>` as the label of the radios and checkboxes inside it
  when their own label has no mark (a radio card's label names the option, not the group).
  `RequiredFieldsAuditTest` covers it (seen failing first).
- The audit cannot see inside a Blade component, so the country select is invisible to it (a conditional
  rule raises nothing for a missing control); the Pest tests pin it on all four forms.
- **The ratchet now covers the fixed storefront forms**: `RequiredFieldsRatchetTest` lists them in
  `FIXED_STOREFRONT_FORMS` (both registration views, the four address forms, the checkout pick) and fails
  on any class A to D finding there, with no baseline (about 1 s, deterministic). Checked by removing the
  postal code's `required`: the test failed naming the finding. The back-office baseline
  (`required-fields-baseline.json`) is unchanged: no back-office finding was touched.

**Audit totals** (`php artisan forms:audit-required`, same day; the report in
`.ai/required-fields-audit-20261021.md` / `.json` is regenerated):

| Class | Before (`develop` 9815092) | After this PR |
| --- | ---: | ---: |
| A | 69 | 69 |
| B | 93 | 91 |
| C | 99 | 82 |
| D | 10 | 10 |
| E | 227 | 218 |
| F | 23 | 23 |
| **Total** | **521** | **493** |

By priority: P1 40 → **12** (left: seller registration's 4 A, six E on the registration views' unstored
fields, two F payment forms), P2 325 → 325, P3 156 → 156. By module: storefront B 4 → 2, C 16 → 9, E 30 → 25;
seller panel C 44 → 34, E 121 → 117. The five forms fixed here have no finding in any class.

**Existing tests that changed.** `LegacyStoreOwnershipTest` (the storefront and seller update payloads gain
a postal code, which the storefront now requires; the assertions are unchanged). `storefrontHead()` and
`StorefrontRegistrationRequiredFieldsTest` use the new shared `htmlXPath()` (no assertion changed). New:
`StorefrontAddressFormsTest` (62 tests), `SellerAddressFormsTest` (41), one `RequiredFieldsAuditTest` case,
one `RequiredFieldsRatchetTest` case, `formFieldMarks()`, `formPostingTo()`, `flashedMessage()` in
`tests/Pest.php`, and the Playwright happy path `tests/e2e/store-storefront-address.spec.ts` (a customer signs
in, opens "Add New Address" on the profile, sees the legend and the accessible names "Address required" /
"Postal code required", is held back by the browser with the postal code empty, then saves and sees the
address listed). The spec was written after the views changed, so it was not seen failing on the old code.

**Hostile input covered**, on create and update for both areas: over-long strings (including multibyte),
arrays for strings, a missing / non-numeric / inactive country, a state of another country, a city of
another state, a latitude past the pole and a longitude the column cannot hold (each refused, nothing
written); markup kept as text and printed escaped (`<b>`, `&`, `"`); a `<script>` is refused by the WAF
before the endpoint (403); another account's address (403 even with an empty payload, unchanged);
`customer_id` / `user_id` of another account (403 on the storefront; ignored in the seller panel).

### Not done here: the next PR (phase 2a, continued)

Items 3 and 4 shipped in §47; item 5 is still open (§47's "Next").

3. **Seller registration** (`shop/frontend/seller_form` → `ShopController@store`, inline `validate()`):
   name, email, password and its confirmation lack `required` (4 A); the asterisks are literal
   `<span class="text-primary">*</span>`; `shop_name` allows 255 characters against a `varchar(200)`
   column and `address` has no `max` against `varchar(500)` (MySQL 500s); move to a FormRequest.
4. **`store_customer`** (`shop/frontend/user/create_user` → `CustomerController@store_customer`, a
   representative's form): 12 A + 12 B, marks and `required` only; the rules exist; `country_code` is
   unvalidated (E).
5. **Endpoints that validate nothing**: the conversation forms on the product and digital product pages
   (`conversations.store`; `product_id` goes straight to `findOrFail`), the support ticket forms
   (`support_ticket.store`, and the seller's), and password reset by code (`shop.password.update`).

**Phase 2b, proposed**: the back-office modules that only lack marks (Marketing 18 B, Product 13 B plus the
per-tax rows, Setup 7 B / 10 A / 8 D, Website 9 A / 6 B, Trips 3 A / 4 B, Sales 6 A, Customers 4 B and the
owner's decision on its 7 C, Languages 3 A, Support 2 B, Blog 2 B); then the seller panel (26 forms, now 34
C and 117 E, mostly endpoints that validate nothing, so FormRequests first), with each fixed Blade form added
to `FIXED_STOREFRONT_FORMS`.

**What was verified, and what was not.** See the PR's final report for the exact commands and results.


## 46. Legacy reference data as a migration (2026-10-21)

A fresh, greenfield database has none of the rows the legacy store carried in its
own database. Found while testing registration on the dev site: the country list
was empty, the terms and conditions were empty, and every price would have failed
(`get_system_default_currency()` does `Currency::findOrFail(get_setting('system_default_currency'))`
and that setting did not exist).

**What is loaded.** Migration `2026_10_21_100000_load_legacy_reference_data`
calls `App\Support\ReferenceData`, which reads `database/data/reference/*.json`
(extracted by the owner from the legacy dump dated 2026-09-12; reference data
only, scanned for secrets and personal data before it was committed):

| Table | Rows | Notes |
|---|---|---|
| `countries` | 246 | **21 enabled** (Austria, Belgium, Bulgaria, Denmark, Finland, France, Germany, Hungary, Iceland, Italy, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Slovakia, Spain, Sweden, Switzerland); Belgium, France, Germany and the Netherlands have `priority` 1. The rest are listed but off. The admin Countries page switches them. |
| `states` | 4,089 | The legacy list has 4,091; two names occur twice in one country (Kyunggi-do in South Korea, Sussex in the United Kingdom) and are loaded once. |
| `currencies` | 26 | Euro is the active one. **The exchange rates are the legacy store's 2023 values (USD = 1) and are stale.** |
| `languages` | 4 | English, Arabic (code `sa`, app code `ar`, right to left), German, Dutch. |
| `pages` | 7 | Home, the five policy pages and About Us. Only Terms, Privacy and About Us carry text; the seller, return and support policy pages are empty in the legacy data and stay empty. |
| `page_translations` | 3 | English rows for Terms, Privacy and About Us. |
| `business_settings` | 1 | `system_default_currency` = the Euro's id, only when the setting does not exist. |
| `zones` | 0 | Empty in the legacy store; nothing is loaded. |

**Safe to rerun, and on a database that already has the legacy rows.** A row is
inserted only when neither its id nor its natural key exists (country, currency
and language code; page type, or slug for custom pages; state name within a
country; page translation language). An existing row is never updated, renamed,
enabled, disabled or deleted. States follow their country by code, so they land
on the right country even if its id differs. A second active currency is never
created, and an existing default-currency setting is never replaced. `down()` is
a no-op because addresses and settings come to reference these rows.

**Page text was cleaned once, offline.** The legacy policy HTML was pasted from
Microsoft Word (`<style>` blocks, `<head>`, `<meta>`, damaged attributes). It
went through `HtmlSanitizer` and the visible words were compared before and after:
identical in all six texts (the Privacy page shrinks from 32 KB to 18 KB). A stray
right-to-left embedding mark at the end of the About Us title was removed.

**Skipped under `APP_ENV=testing`.** The test suites and the browser fixtures start
from empty reference tables and create what they need; loading 4,000 states into
every test database would break tests that expect them empty. `LoadLegacyReferenceDataTest`
calls the loader directly, runs on SQLite and on MySQL (it is in the CI MySQL job
and in `pre_push_check.py`'s MySQL list), and runs the migration under a non-testing
environment.

**Registration pages.** Both pages now show the real Terms page in the agreement
pop-up (they printed a business setting nothing writes, `get_setting('terms')`);
without a terms page they say so instead of showing an empty box. `/temp/register`
offers the enabled countries as a dropdown, priority countries first, and keeps the
text box when no country is enabled. `/temp/users/registration` already had a
dropdown over the `countries` table; it is no longer empty. The phone widget's
country list is the 21 enabled countries.

**Left alone.** The terms *email* (`shop/emails/terms.blade.php`) still prints
`get_setting('terms')`; the `business_settings` rows of the legacy store were not
extracted (they mix harmless settings with API keys and passwords), so settings
other than the default currency still come from the admin screens.

**Owner items** are in the go-live checklist, 3.10 to 3.13: review the enabled
countries, update the exchange rates, write the three empty policy pages, and
decide on the zones and the shipping set-up.

## 47. Public forms, phase 2a continued: the longitude columns, seller registration, create customer (2026-10-21)

Model: **claude-opus-5-5**, effort **high**. Branch `claude/phase-2a-longitude-forms-xdumd2` from `develop`
**1146d2e** (the PR #460 merge). Shipped in [PR #461](https://github.com/arb-rajab/warehouse_management/pull/461),
with real CI (no `[skip ci]`); recorded here by the next PR (§48). Scope: §45 judgement 3 (the longitude columns), then §45's "Not done here"
items 3 and 4. Each form's tests were written first and seen failing. Every redirect, flash message, route,
session key and URL is unchanged. The PR reached about 26 files, close to the ~30-file limit the brief set,
so item 5 (the endpoints that validate nothing) is the next PR, listed under "Next" below.

**Shipped, by commit.**

1. **Every valid longitude is stored, and the map pin starts at the default location.**
2. **Seller registration** (`shops.store`).
3. **The representative's create-customer form** (`store_customer`).

### The longitude decision

`addresses.longitude` and `shops.delivery_pickup_longitude` were `decimal(17,15)`: 15 digits after the
point leave 2 before it, so a longitude of 100 or more (Sydney, 151.2, the map's old default pin; anything
east of about 100°E or west of 100°W) failed on MySQL with error 1264, and since #459 the address forms
refused it. The shop sells in Europe, but a valid point on the map must be storable.

- **Migration `2026_10_21_110000_widen_map_longitude_columns`** widens both to `decimal(18,15)` with
  `->nullable()->change()`. It reads the column's real type first (`Schema::getColumns()`) and changes it
  only when it holds fewer than three digits before the point, so a rerun, a column already wider (the
  test sets `decimal(20,15)` and checks it stays) and SQLite (whose `numeric` keeps no precision) are left
  alone. `down()` is a no-op: narrowing again would fail on, or truncate, every longitude of 100 or more
  saved since. The latitudes stay `decimal(17,15)`: ±90 fits.
- **Columns checked and not changed:** `visits.map_lat` / `map_lng` are `varchar(20)` (fits
  `-179.999999999999999`); `checkpoints.longitudes` / `latitudes` are `varchar(255)`. No other table has a
  decimal coordinate.
- **Validation**: `ValidatesAddress` (the storefront and seller-panel address requests) allows longitude
  `between:-180,180` again (was `gt:-100|lt:100`); the back office's `StoreCustomerAddressRequest` already
  did. Boundary tests on all three: ±180 and 151.2093 / -179.9999 accepted, ±180.0001 refused.
- **Test**: `tests/Feature/Database/WidenMapLongitudeColumnsTest.php`, in CI's MySQL job and
  `pre_push_check.py`'s `MYSQL_TESTS`. On MySQL 8.0 here: before the migration existed it failed with
  `SQLSTATE[22003] ... 1264 Out of range value for column 'longitude'`; with it, 151.2093, -179.9999, 180 and
  4.9041 insert and read back on both tables, the type is `decimal(18,15)` and still nullable, a narrowed
  column is widened with its rows intact (`4.123456789012345`, `-99.5`, `null`), the latitudes stay
  `decimal(17,15)`, and `down()` keeps a 151.2093 row. MySQL commits DDL at once, so the test restores the
  columns after each case.
- **The default pin.** `App\Support\DefaultMapLocation::coordinates()` reads `google_map_latitude` and
  `google_map_longtitude` (set on the admin Google Map settings page, spelled as the legacy store stored
  them) and returns Amsterdam (52.3676, 4.9041) when either is empty, not numeric (`INF`, markup) or off the
  map. The shared map partial (`partials/google_map`, included by the storefront profile, dashboard and
  address modal and by the seller profile) prints it once as `var defaultMapLocation`, and both address
  modals' edit handlers and the seller's pick-up point map (`seller/shop`) start there instead of Sydney.
  The settings are no longer printed raw into the script, and an address's own coordinates are no longer
  replaced by the default when its edit modal opens (the old partial overrode them whenever a default was
  set). The coordinate inputs stay empty until the customer moves the pin, as before, so a customer who
  never moves it saves the address without coordinates (tested with no default and with a default east of
  100°). `StorefrontMapDefaultLocationTest` renders the pages with and without the settings and asserts the
  initial coordinates.

### Rules chosen, per form

| Form (view → endpoint) | Before | Now |
| --- | --- | --- |
| Seller registration, `shop/frontend/seller_form` (`/temp/shops/create`) → `ShopController@store` (`shops.store`) | inline: `name` required\|string\|max:255, `email` required\|email\|unique:users\|max:255, `password` required\|string\|min:6\|confirmed, `shop_name` required\|max:255, `address` required. HTML: `required` only on shop name and address; literal `<span class="text-primary">*</span>` on all six labels; no `for`/`id` | `StoreShopRequest`: **name** `required\|string\|max:191`, **email** `required\|string\|email\|max:191\|unique` (StoreUser), **password** `required\|string\|min:6\|confirmed`, **shop_name** `required\|string\|max:200`, **address** `required\|string\|max:500`. HTML: mark and `required` on all six, the legend, `for`/`id`, `maxlength` 191/191/200/500, `minlength="6"` on both passwords, errors with `@error`; the password field no longer prints `old('password')` (always empty anyway). |
| Create customer (representative), `shop/frontend/user/create_user` (`/temp/create_customer`) → `CustomerController@store_customer` | inline rules require all twelve fields (unchanged); HTML marked and required none; labels' `for` named no control; the country's error was hidden under bootstrap-select | rules **unchanged** (the brief: marks only where the server already validates). HTML: mark and `required` on name, company name, email, phone, country, tax number, bank account number, company address, shipping address, trade license, password and its confirmation; the legend; `for`/`id`; the country error shown (`d-block`); `minlength="6"` on the passwords. |

**Why `max` follows the columns.** `users.name` and `users.email` are `varchar(191)`, `shops.name`
`varchar(200)`, `shops.address` `varchar(500)` (`2026_09_28_005008_create_users_table`,
`2026_09_28_004011_create_shops_table`). The old `max:255` let 192 to 255 characters through to MySQL, whose
strict mode refuses them ("data too long"; SQLite does not enforce lengths, so the tests check the rule),
and an array for the shop name or address was an "Array to string conversion" 500 (seen in the tests
before the change). The slug is built from the shop name, so 200 characters keep it
inside `shops.slug` (`varchar(255)`).

### Judgement calls for the owner

1. **The longitude columns widened** (above): a schema change on two legacy-shaped tables. It runs on the
   next `php artisan migrate`; on the production database it is an `ALTER TABLE ... MODIFY` on `addresses`
   and `shops` (a table copy on MySQL 8 for a precision change: seconds at the current size).
2. **New addresses keep empty coordinates when the pin is not moved**, rather than saving the default
   location: saving Amsterdam's coordinates for every address whose customer ignored the map would give the
   order's `lat_lang` (`OrderController`, `Api/V2/OrderController`) a false delivery point.
3. **The default pin falls back to Amsterdam**, also when the admin's setting is off the map; the admin page
   already refuses such a value, so only legacy rows can reach the fallback.
4. **Seller registration still requires name, email and password for a signed-in visitor.** The form hides
   them for a signed-in visitor, but `ShopController::create` never shows the form to one (it refuses
   admins, customers and sellers and returns nothing for other account types), so only a hand-made POST
   reaches that branch; it keeps the rules it had. The same hand-made POST still converts a signed-in
   customer (or admin) into a seller, as before: `store()` does not repeat `create()`'s refusals. Not
   changed here (it converts only the caller's own account; guests can register sellers anyway); worth a
   decision before the seller registration link goes live.
5. **reCAPTCHA is checked only in the browser** on seller registration (and on create customer): neither
   endpoint verifies the token. Unchanged; a server-side check is a separate change.
6. **Two dead checks left in `ShopController::store`**: the "Email already exists!" and "Sorry! Password did
   not match." branches cannot run after `unique` and `confirmed`; and the failed-save branch compares
   (`==`) instead of assigning `user_type`. Left as they were (no behaviour to preserve or test).
7. **Create customer's rules disagree with the columns**, recorded, not changed (marks only): `name`
   `max:255` against `users.name` `varchar(191)`, `email` has no `max`, `phone` has no limit against
   `users.phone` `varchar(20)`, `country` (a country name) against `users.country` `varchar(30)` (the
   21 enabled names fit, but three of the 246 are longer and the rule accepts any string), the `trade_license` mimes list says `bmb` (likely `bmp`). These are MySQL
   errors for long input from a signed-in representative; a FormRequest for this endpoint is the fix.
8. **The OTP-addon branch of create customer**: its hidden email stays unmarked and unrequired (the browser
   would block the form on a hidden required field), as on the registration forms (§44); the visible
   phone is marked and required. With the addon on, the form has two `phone` inputs; unchanged.

### The audit and the ratchet

`FIXED_STOREFRONT_FORMS` gains `seller_form` (`shops.store`) and `create_user` (`store_customer`): neither
has a class A to D finding. The back-office baseline is unchanged (no back-office finding was touched).

| Class | Before (`develop` 1146d2e) | After this PR |
| --- | ---: | ---: |
| A | 69 | 53 |
| B | 91 | 79 |
| C | 82 | 82 |
| D | 10 | 10 |
| E | 218 | 218 |
| F | 23 | 23 |
| **Total** | **493** | **465** |

By priority: P1 12 → **8**, P2 325 → 301, P3 156 → 156. By module: storefront A 6 → 2 (the two left are
the P2 product question forms), storefront/account A 12 → 0, B 13 → 1. The P1 findings left are six E (fields
`RegisterController` never stores: `country_code` on both registration views; tax number, company address,
shipping address and trade license on `/temp/register`, deliberately "(optional)" since §44) and two F (the
PayHere form posts to an external URL; `CheckoutController@checkout` builds its rules at run time). Neither
is a marking gap: the E fields need an owner decision (store them, or drop the inputs), the F forms have
nothing to mark. `create_user` keeps one E (`country_code`, filled by the phone widget, read by nothing).

### Tests

New: `WidenMapLongitudeColumnsTest` (9 cases; SQLite and MySQL), `StorefrontMapDefaultLocationTest` (17),
`SellerRegistrationFormTest` (25), `StorefrontCreateCustomerFormTest` (16); `validMapLongitudes()` in
`tests/Pest.php`; boundary cases in `StorefrontAddressFormsTest`, `SellerAddressFormsTest` and
`CustomerAddressControllerTest`. Changed: the two address tests' "a longitude the column cannot hold" case
(151.2195, refused) became "past the antimeridian" (±180.0001, refused), since 151.2195 is now valid.
No assertion was removed or loosened otherwise.

### Next: the rest of phase 2a

Items 5 and 7 and judgements 4 and 5 shipped in §48; item 6 is still open.

5. **Endpoints that validate nothing**, each with a FormRequest, hostile-input tests (over-long, wrong types,
   HTML, another user's ids) and unchanged ownership checks: the conversation forms on the product and
   digital product pages (`conversations.store`, `ConversationController@store`; `product_id` goes straight
   to `findOrFail`), the support ticket form (`support_ticket.store`), and password reset by code
   (`shop.password.update`).
6. **The P1 leftovers above**: the owner's decision on the unstored registration fields.
7. **Create customer's rules against its columns** (judgement 7): a FormRequest.

**Phase 2b, proposed**: unchanged from §45: the back-office modules that only lack marks (Marketing 18 B,
Product 13 B plus the per-tax rows, Setup 7 B / 10 A / 8 D, Website 9 A / 6 B, Trips 3 A / 4 B, Sales 6 A,
Customers 4 B and the owner's decision on its 7 C, Languages 3 A, Support 2 B, Blog 2 B); then the seller
panel (26 forms, 34 C and 117 E, mostly endpoints that validate nothing, so FormRequests first, including
the pick-up point coordinates of `Seller\ShopController@update`, which are still unvalidated), with each
fixed Blade form added to `FIXED_STOREFRONT_FORMS`.

**What was verified, and what was not.** Run here, on the final code (commits 1 to 3; the docs commit
after them changes no code): `python3 .claude/scripts/pre_push_check.py --full` (Wayfinder types, ESLint,
Prettier, vue-tsc, Vitest **336 files, 2651 tests**, Pint, PHPStan **0 errors**, Pest **6800 tests, 6797
passed, 3 skipped, 39305 assertions**); the six `MYSQL_TESTS` files on the local MySQL 8.0 (CI runs 8.4):
**56 tests, 50 passed, 6 skipped** (the SQLite-only cases); the whole Playwright suite with the
preinstalled Chromium: **134 passed**. Focused Pest files while developing, each seen failing first (the
migration test also on MySQL). `forms:audit-required` before the work and after each form group. Not run
here: the composer and npm audits and the deploy-script test (CI runs them); no `CI-Verified` trailer was
made, so CI runs every group (the PR also changes `.github/workflows/tests.yml`, which runs everything).

## 48. Public forms, phase 2a finished: password reset by code, product questions, support tickets, the seller conversion, reCAPTCHA, create customer (2026-10-21)

Model: **claude-opus-5-5**, effort **high**. Branch `claude/phase-2a-forms-recaptcha-8acgk4` from `develop`
**343d9d8** (the PR #461 merge). Shipped in [PR #462](https://github.com/arb-rajab/warehouse_management/pull/462),
merged into `develop` as **03f9ab44**; recorded by §49. Scope: §47's "Next" items 5 and 7 and the three owner decisions recorded on
PR #461: close the seller conversion hole (§47 judgement 4), verify reCAPTCHA on the server (judgement 5),
give create customer real limits (judgement 7). Each group's tests were written first and seen failing,
then passing. Every redirect, flash message, route and URL is unchanged except where a row below says so.
The PR reached about 33 files, a little over the ~30 the brief set; the one item left (reCAPTCHA on the
customer registration views) is under "Next".

**Shipped, by commit.**

1. **Password reset by code** (`shop.password.update`).
2. **Product questions and support tickets** (`conversations.store`, `support_ticket.store`).
3. **Seller registration turns a signed-in account away** (`shops.store`).
4. **Create customer limited to its columns** (`store_customer`).
5. **reCAPTCHA verified on the server** (`shops.store`, `store_customer`).

### Rules, per form: before and now

| Form (view → endpoint) | Before | Now (request) |
| --- | --- | --- |
| Password reset by code, `shop/auth/passwords/reset` → `HomeController@reset_password_with_code` | **nothing validated**; `password != password_confirmation` flashed a warning; a 3-character password reset the account; an array for any field was a 500 (the email's in the `store-auth` limiter) | `ResetPasswordWithCodeRequest`: **email** `required\|string\|email\|max:191`, **code** `required\|string\|digits:6`, **password** `required\|string\|min:6\|confirmed`. A refusal re-renders the form (422) with each error under its field and the email and code kept (the form is only ever rendered in answer to a POST, so "back" has no GET route). |
| Product question, `shop/frontend/product_details` and `digital_product_details` (no route renders the latter) → `ConversationController@store` | **nothing validated**; `product_id` went to `findOrFail`, so an unpublished or unapproved product's seller got the question; arrays were 500s | `StoreConversationRequest` (bag `storeConversation`): **product_id** `required\|integer`, exists in `products` with `published = 1` and `approved = 1`; **title** `required\|string\|max:1000` (`conversations.title` varchar(1000)); **message** `required\|string\|max:65535` (`messages.message` text, as the other text columns' rules) |
| Support ticket, `shop/frontend/user/support_ticket/index` → `SupportTicketController@store` | **nothing validated**; a ticket without a subject was a NOT NULL 500; `attachments` took any upload ids, another account's included | `StoreSupportTicketRequest` (bag `storeTicket`): **subject** `required\|string\|max:255` (`tickets.subject`); **details** `required\|string\|max:10000` (`ValidatesSupportReplies`, the back office's support-text limit; the column is longText); **attachments** optional, the uploader's comma-separated image ids, the caller's own, at most 50, no repeats (`ValidatesStoreUploadReference::retainableImageUploadIdsRule(null)`) |
| Seller registration, `shop/frontend/seller_form` → `ShopController@store` | rules from §47; a signed-in account was **converted into a seller** (its customer row deleted) and given a shop | `StoreShopRequest::authorize()`: guests only; the refusal is the page's own (below), before the input is read. Rules as §47, plus reCAPTCHA. |
| Create customer, `shop/frontend/user/create_user` → `CustomerController@store_customer` | inline: name `max:255` (column 191), email no `max` (191), phone, country no rule beyond `required` (20, 30), addresses `string` without `max` (255), tax and bank numbers no type; `trade_license` `mimes:doc,docx,pdf,png,jpeg,bmb`; `abort_unless(is_rep, 403)` after nothing | `StoreRepresentativeCustomerRequest`: all twelve still required; **name** `string\|max:191`, **email** `string\|email\|max:191\|unique`, **phone** `string\|max:20`, **country** `string\|max:30`, **company_name**, **company_address**, **shipping_address** `string\|max:255`, **tax_number**, **bank_account_number** `string\|max:100`, **password** `min:6\|confirmed`, **trade_license** `file\|mimes:doc,docx,pdf,png,jpg,jpeg\|max:20000`; `authorize()`: representatives only (403, now before the input). Plus reCAPTCHA. |

**The forms.** Each marks and requires exactly what its request requires (`<x-required-mark />`, `required`,
the legend once), ties every label to its control (`for`/`id`; the reset and question forms had no labels at
all, only placeholders), limits each field in HTML as the rule does (`maxlength`, `minlength`, the code's
`pattern="[0-9]{6}"` and `inputmode="numeric"`, the license's `accept`), keeps the input after a refusal
(never a password) and shows each error under its field. The question and ticket modals read their own error
bag and reopen after a refusal; the product page's other forms stay clean. The reset form no longer prints
the email into the code field on the reset-link page (`value="{{ $email ?? old('code') }}"`).

### The password reset, check by check

Nothing was weakened or reordered: the input is checked first (where the mismatch check stood), then the
email names the account and `StoreVerificationCodes::consume()` tries the code against it, unchanged: 15
minutes, single use (the row is deleted), 5 attempts, keyed to the user, so a code never works for another
account's email (tested: the attacker's own code is not even charged an attempt). Malformed input spends
none of the code's attempts; the `store-auth` throttle counts it all the same (tested: five refused posts,
then the right code is a 429). A wrong, expired or reused code, an unknown email and another account's email
keep the "Verification code mismatch" notice with a 200. The identifier is the email only: the endpoint has
never looked up by phone (the SMS reset page is not part of this app).

**The `store-auth` limiter** cast `email` to a string; an array there was a 500 on every throttled route
before the endpoint ran. A non-text value now names no account (the IP limit still applies) and the
endpoint answers with its validation error (`app-providers.md`).

### The seller conversion hole

`ShopController::create` refuses admins and customers ("Admin or Customer can not be a seller") and sellers
("This user already a seller"), and returns an empty page to any other signed-in account. `store()` now
refuses every signed-in caller the same way, in `StoreShopRequest::authorize()` / `failedAuthorization()`:
back, with the page's notice (`StoreShopRequest::refusalFor()`, which `create()` now calls too), or the
store's generic "Sorry! Something went wrong." for the types the page shows nothing to (staff, delivery
boys, ...). The conversion branch in `store()` is removed: nothing can reach it. Tests: a hand-made POST
from a customer, an admin, a seller, a staff member, a delivery account, a customer who already has a shop,
and an invalid payload; each writes no shop and leaves the account's type unchanged. (On the test database
the old conversion failed on the legacy `customers` table, which only the production database has; there
it converted the account.)

### reCAPTCHA on the server

The widget appears in four views (`rg -n g-recaptcha resources/views`): seller registration, create
customer, and both customer registration views (`shop.auth.register`, `user_registration`, posting to
`RegisterController::register`). This PR verifies it on the first two; the registration views are next.

- `App\Rules\Recaptcha` posts the token, the secret and the visitor's IP to
  `https://www.google.com/recaptcha/api/siteverify` (form-encoded, 5 s timeout) and accepts only
  `"success": true`. `Recaptcha::rules()` applies `bail|required|string|<rule>` to `g-recaptcha-response`
  only while `get_setting('google_recaptcha') == 1` (the existing setting; the forms show the widget exactly
  then); with it off, the field is not validated and Google is never called.
- **The secret**: `services.recaptcha.secret_key`, from `CAPTCHA_SECRET`, next to the existing site key
  (`CAPTCHA_KEY`). Never hard-coded, never logged; the token is not logged either (tested with `Log::spy`).
- **Fail closed** (the owner's decision to weigh, below): no secret configured, a non-2xx answer, an
  answer that is not a JSON verdict, or no connection refuse the form with "The robot check could not be
  completed. Please try again." and log a warning (the HTTP status only). A refused or missing token gets
  "Please confirm that you are not a robot." The error shows under the widget.
- Tests (`StorefrontRecaptchaTest`, both forms, `Http::fake`): valid, refused, missing, not text, 503,
  connection failure, an HTML answer, no secret, nothing logged, setting off, a stray token with the setting
  off. Each refusal writes nothing.

### Judgement calls for the owner

1. **reCAPTCHA fails closed.** While the setting is on, a Google outage, a timeout or a missing
   `CAPTCHA_SECRET` blocks seller registration and create customer until it is fixed. The alternative,
   failing open, would let any bot through whenever Google cannot be reached or the key is missing, which
   is the hole this closes. Before turning the setting on, set `CAPTCHA_KEY` and `CAPTCHA_SECRET` (Appendix A
   of the go-live checklist) and submit both forms once.
2. **The reset form answers a refusal with 422 and the form** instead of a flashed warning and a 200 (the
   mismatch case); the code checks keep their 200 and notice.
3. **A non-numeric code is now a validation error**, not a counted attempt: it could never be right, and it
   still counts toward the throttle.
4. **Support ticket details are held to 10,000 characters**, the back office's limit for replies, although
   the column is longText; a longer description was never useful in the modal.
5. **Text columns get 65,535 characters**, as elsewhere in the app: on MySQL a message of that many
   multibyte characters is still longer than a `text` column's 65,535 bytes.
6. **The trade license's "bmb" is dropped, not made "bmp"**: `TradeLicenseStorage` keeps only pdf, doc,
   docx, png and jpg, so a bitmap the rule let through would have failed after the account was created.
7. **Create customer's country** is limited to its column (30) and is still any text: three of the 246
   country names are longer and cannot be stored, and the form offers only active countries. Requiring
   one of the active countries (`Rule::exists`) is a small follow-up if wanted.
8. **The seller registration's dead checks** ("Email already exists!", "Sorry! Password did not match.",
   §47 judgement 6) are left as they were.

### The audit and the ratchet

`FIXED_STOREFRONT_FORMS` gains the reset form, both product question forms and the support ticket form;
none has a class A to D finding. The back-office baseline is unchanged: no back-office finding was touched.

| Class | Before (`develop` 343d9d8) | After this PR |
| --- | ---: | ---: |
| A | 53 | 53 |
| B | 79 | 79 |
| C | 82 | 72 |
| D | 10 | 10 |
| E | 218 | 215 |
| F | 23 | 23 |
| **Total** | **465** | **452** |

By priority: P1 8 → 8, P2 301 → **288**, P3 156 → 156. Per commit: the reset form C 82 → 78; the questions
and tickets C 78 → 72, E 218 → 215; the seller conversion, create customer and reCAPTCHA leave the totals
as they were (those forms had no finding left). **The six registration fields that are never stored**
(owner decision pending, not touched): `country_code` on both registration views, and tax number, company
address, shipping address and trade license on `/temp/register` (P1, class E).

### Tests

New: `StorefrontPasswordResetCodeFormTest` (27), `StorefrontProductConversationFormTest` (22),
`StorefrontSupportTicketFormTest` (21), `StorefrontRecaptchaTest` (22); new cases in
`SellerRegistrationFormTest` (7) and `StorefrontCreateCustomerFormTest` (26). Changed:
`LegacyStorePasswordResetTest` (the wrong-code case sends a wrong six-digit code; a non-numeric one is now a
validation error), `LegacyStoreAccountRecoveryTest` (the mismatched confirmation expects 422; the code is
still usable after it). No assertion was removed or loosened.

**Hostile input covered**: over-long values (multibyte too) one past each column, arrays for every text
field and for ids, a product id that does not exist / is not a number / is unpublished / unapproved, another
account's upload as a ticket photo, a non-image upload, a repeated photo, markup (stored as sent, printed
escaped; ticket details through `clean_html()`), a sender, receiver, owner or status sent by the caller
(ignored), a guest (sent to sign in, nothing written), a signed-in non-representative (403 before its input
is read).

**A testing trap found**: reading the session in a test (`assertSessionHasErrors`) before the next request
starts the session in the test, and that next request no longer sees the flashed errors. Assert errors on
the rendered page when the test renders it afterwards, as the form tests here do.

### Next: phase 2a leftovers, then 2b

- **reCAPTCHA on customer registration**: `RegisterController::register` (both registration views) shows
  the widget and does not verify it; add `Recaptcha::rules()` to its validator and the error under the widget
  in both views.
- **The unstored registration fields** (above): store them, or drop the inputs (owner).
- **The back office's customer rules** (`ValidatesStoreCustomer`) allow 255 for name, email and phone against
  191 / 191 / 20: the same MySQL errors create customer had.

**Phase 2b, proposed**: the back-office modules that only lack marks (Marketing 18 B, Product 13 B plus the
per-tax rows, Setup 7 B / 10 A / 8 D, Website 9 A / 6 B, Trips 3 A / 4 B, Sales 6 A, Customers 4 B and the
owner's decision on its 7 C, Languages 3 A, Support 2 B, Blog 2 B), with `ValidatesStoreCustomer`'s limits;
then the seller panel (26 forms, 34 C and 117 E, mostly endpoints that validate nothing, so FormRequests
first: the seller's support ticket and the ticket replies on both sides, products, digital products,
coupons, the shop settings and its pick-up point coordinates), each fixed Blade form added to
`FIXED_STOREFRONT_FORMS`.

**What was verified, and what was not.** Run here, on the final code (commits 1 to 5; the docs commit
after them changes no code): `python3 .claude/scripts/pre_push_check.py --full` (Wayfinder types, ESLint,
Prettier, vue-tsc, Vitest **336 files, 2651 tests**, Pint, PHPStan **0 errors**, Pest **6925 tests, 6922
passed, 3 skipped, 39926 assertions**); the whole Playwright suite with the preinstalled Chromium
(`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium npx playwright test`; `npm run test:e2e`
stops at its Vite build here, since `fonts.bunny.net` is unreachable, so the setup's font-less build was
used; this PR changes no JavaScript): **134 passed**. Focused Pest files while developing, each group seen
failing first. `forms:audit-required` before the work and after each group. Not run here: the MySQL
migration tests (no migration changed), the composer and npm audits and the deploy-script test (CI runs
them); no `CI-Verified` trailer was made, so CI runs every group the plan picks.


## 49. Back-office required fields, phase 2b batch 1: Marketing and Product; customer registration reCAPTCHA (2026-10-21)

Runtime identity: **GPT-6 Codex**. The exact model variant and reasoning-effort setting are not exposed by
this runtime, and there is no model picker here. The requested **GPT-6.1 Sol / High** could not be independently
verified or selected; neither is claimed as the actual setting. Branch `codex/phase-2b-batch-1` starts at
`origin/develop` **03f9ab44**, the merged PR #462. [PR #463](https://github.com/arb-rajab/warehouse_management/pull/463)
is the single PR into `develop` for this batch. `AGENTS.md` portability rules were followed: no Claude
hooks or Boost results were assumed. PHP is **8.5.11** in Docker (`wms-phase2b-php:8.5`, based on the existing
`wms-cloud-php:8.5` image with WebP-enabled GD),
Laravel **13.34.0** from installed source. The existing vendor tree, local environment and Vite manifest
were checked; missing frontend packages were restored with **npm 11.9.0 `npm ci`**, without lockfile changes.
Wayfinder actions/routes were generated, and Docker PHP's memory limit raised from 128 MB to 1 GB.

**Scope and commits.** Marketing and Product are each one commit; the production CAPTCHA keys and customer
registration verification are separate commits. With the mandatory follow-ups and this documentation the
PR reaches about 39 files, so Setup and Website are deferred at the owner's approximate 30-file cutoff.
Routes, permissions, existing label text and translation keys are unchanged. Two new English/Arabic help
keys explain conditional product requirements. No server field-presence rule or column limit changed in
these two modules; names were checked against the narrowest translated column (campaign titles 50,
category/brand/attribute names 50, product names 200, units 20, colour names/code 30/10). No boundary test
for a changed limit is needed because no limit changed.

**Marketing.** Required marks now accompany coupon type, discount type and selected products; popup
summary, offer and page; flash-deal text colour; offer brand/base product; and the campaign product picker
and each row's discount type. The existing shared marks and automatic legend are reused. Full mounts pin
the complete set of marks and native required controls for cart/product coupons, both offer kinds,
flash deals, popup fields and campaign rows; a mounted flash-deal page checks exactly one legend.

**Product.** Marks cover physical/digital categories, discount types, each tax amount/type and the physical
unit price. Stock visibility radios have `required` and one mark on their legend. Product-wise shipping
radios do likewise only while their server setting is enabled. The quantity-multiplied checkbox remains
optional and sits outside that radio fieldset. Flat shipping cost is required only on flat-rate shipping;
flash discount **and** type are required and marked only with a flash deal selected. English/Arabic help
explains both conditions. Full mounts cover selection and clearing, disabled settings, multiple tax rows,
optional controls and the already-correct brand-discount field.

**The audit, before and after each module.** `php artisan forms:audit-required` was run on the untouched
base, before/after Marketing, before/after Product, and after registration verification. Exact counts:

| Module | Before A/B/C/D/E/F | After A/B/C/D/E/F |
| --- | --- | --- |
| Marketing | 4 / 18 / 0 / 0 / 8 / 0 | 4 / 0 / 0 / 0 / 8 / 0 |
| Product | 1 / 13 / 3 / 0 / 0 / 5 | 0 / 5 / 3 / 0 / 0 / 5 |
| Setup (deferred) | 10 / 7 / 0 / 8 / 0 / 0 | unchanged |
| Website (deferred) | 9 / 6 / 1 / 0 / 2 / 0 | unchanged |
| Whole application | 53 / 79 / 72 / 10 / 215 / 23 | 52 / 53 / 72 / 10 / 215 / 23 |

Total **452 → 434 → 425**. Back-office baseline **109 → 91 → 82**: only the 18 Marketing B entries,
then one Product A and eight Product B entries actually absent from the new audit were removed.

**Judgement calls and audit limitations.** No new baseline entry was added and no audit implementation
was changed to make these numbers fall. Marketing's four A findings are the script-populated `products`
array: the required multi-select is deliberately unnamed and rows post dynamic nested names, which the
scanner cannot resolve. Product's five B findings are four digital-tax labels plus the brand-discount
label: dynamic `for`/`id` expressions prevent the scanner associating their existing rendered marks.
Full-mount tests verify all of them. Product's three C findings are conditional sections scanned while
the scratch server settings exclude their rules: taxes and flat shipping cost remain required only in
the rendered state that needs them. Removing those requirements would disagree with the active server
rules. Product's five F findings remain (category inline writers, computed actions and the edit request's
unsaved-model `digital` scope/attribute collision); they are not claimed fixed. Client-only search fields
still account for Marketing's eight E findings.

**Production keys.** `.env.production.example` names empty `CAPTCHA_KEY` and `CAPTCHA_SECRET`, and says not
to enable the reCAPTCHA setting until both are set because verification fails closed. The existing
production-template test pins both empty values and that warning; seen failing first.

**Customer registration.** Both `/temp/register` and `/temp/users/registration` post to
`RegisterController::register`, whose validator now uses `Recaptcha::rules()` and `Recaptcha::messages()`
from PR #462, with the error displayed below each widget. Only the existing `google_recaptcha` setting
activates it. The shared Http fake dataset now exercises both pages as well as seller registration and
representative create customer: valid, invalid, missing, wrong-type token; provider 503, connection
failure, non-verdict response, missing secret, no secret/token logged; setting off with no token or a
stray token. Refusals write no account. The audit parser test now expects the added CAPTCHA rule and its shifted source line. The shared registration payload moved to `tests/Pest.php` at its
second test-file caller. Fail-closed operation now applies to all three POST endpoints: configure both
keys before enabling the setting; a provider outage blocks submission while it is enabled.

**Verification.** Baseline on `develop` code: required-fields audit **452**, ratchet plus registration tests
**124 passed / 759 assertions**, and all four requested modules' Vitest files **58 files / 267 tests**.
The complete backend baseline includes `LegacyStoreRegistrationTest` and was repeated in an isolated
`03f9ab44` worktree. Initial attempts found missing Wayfinder output/packages and the 128 MB PHP limit; those were setup
failures, corrected before the successful baseline. New marking assertions and CAPTCHA/template behavior
tests were run failing before implementation. Focused Marketing **5 files / 12 tests**; focused Product
**5 files / 27 tests**; registration/CAPTCHA **56 tests / 293 assertions**; final focused ratchet and
registration group **142 tests / 819 assertions**; production template **6 tests / 406 assertions**.
Whole Vitest and vue-tsc ran before module commits. PHPStan passed with no errors; Pint ran on dirty PHP
files. The first full pre-push attempt found a stale audit expectation (updated for the new CAPTCHA rule),
stale Composer class-map entries for removed controllers (regenerated with `dump-autoload --no-scripts`),
and Docker GD without WebP (enabled by rebuilding GD in the local Docker image). No test was skipped or
quarantined to address these failures. The final explicit `python3 .claude/scripts/pre_push_check.py --full` **passed** before the last commit:
Wayfinder generation, whole-repo ESLint, Prettier, vue-tsc, Vitest **336 files / 2,651 tests**, Pint,
PHPStan **0 errors**, config-cache clear, and parallel Pest **6,944 passed / 3 skipped / 40,022 assertions**
(**137.30 s**). The three skips are the existing MySQL-only longitude precision cases, skipped on SQLite.
The repaired-runtime focused checks also passed: audit/autoloader **98 tests / 669 assertions**, uploads
**87 tests / 403 assertions**. Playwright, MySQL migration tests, composer/npm audits and the deploy
script test were **not run by `--full`**; their CI jobs remain the gate when planned. No `CI-Verified`
trailer was written or attested, and the script was never run bare on a clean committed tree. The full
production `npm run build` also **passed**, including remote fonts: Node 24 needed `NODE_USE_ENV_PROXY=1`
and the provided CA via `NODE_EXTRA_CA_CERTS` to use the managed proxy; the first attempt without those
failed font-host DNS. The font-free fallback was not used. `git diff --check` passed.

**Browser follow-up from real CI.** The first PR run passed every planned non-browser job but failed the
two physical/digital product create cases: three exact `Browser VAT` label-text selectors missed the new
mark. Both specs now use the exact accessible spinbutton name **`Browser VAT required`**, retaining the
single-tax count assertion and additionally asserting native `required`. These changes are folded into
the Product commit. The two affected specs were run locally: **7 passed (44.7 s)**, including create/edit,
uploads, stored/pasted scripts, RTL, contrast and keyboard/editor behavior, with CSP enforced. Local PHP
ran in Docker against the dedicated `database/e2e*.sqlite` files, with the backend exposed only on loopback;
system Chromium/FFmpeg were used. Initial local launches found missing video-encoder setup and an ignored
stale `public/hot` marker; the marker was backed up to use the production build, without weakening CSP.
Playwright's generated transform cache was moved outside the repository before whole-repo lint. The final
`--full` pass above was repeated after these browser-spec edits. No assertion was loosened and no test
was quarantined. Real CI is run again for the revised PR head; results are available in PR #463's checks.


**Remaining scope.** Carry forward **Setup** (10 A / 7 B / 8 D: conditional marks and help, especially
currencies), then **Website** (9 A / 6 B / 1 C). **Batch 2** remains Trips, Sales, Customers, Languages,
Support and Blog. The owner has chosen to drop screen requirements exceeding the server's, including
customer create's seven C findings; `ValidatesStoreCustomer` name/email/phone must also be limited to
191/191/20 with boundary tests. Separately, the registration fields never stored still need the owner's
decision (store them or remove them), and seller-panel endpoints without validation remain phase 3.
## 50. Seller forms, phase 3 batch 1: account, money and text writes (2026-10-21)

Runtime: **GPT-6 Codex**; the exact model variant and reasoning-effort setting are not exposed by this
runtime. The requested **claude-opus-5-5 / high** cannot be selected here and is not claimed. Branch
`codex/seller-validation` starts at `origin/develop` **637f7d15**, the merge of the previous
[PR #463](https://github.com/arb-rajab/warehouse_management/pull/463). No newer `develop` change was present
when work began. The owner authorized committing, opening one PR to `develop`, and merging after all CI
is green. Account, money and text writes are separate commits. At approximately **32 files**, including
the audit reports, browser coverage and this documentation, the requested size cutoff stops this batch
before shop and commerce. No product or digital-product writer or view was changed.

### Rules, per form: before and now

| Form / route | Before | Now |
| --- | --- | --- |
| Profile (`seller.profile.update`, `seller/profile/index`) | `SellerProfileRequest`: name required/max 191 without a string check; minimum 6 only when both password controls were nonempty; a mismatch was silently ignored; phone, avatar and bank fields unvalidated | Name required/string/max **191**; optional phone string/max **20**; optional new password string/min **6**, confirmed against the existing `confirm_password`, at most **72 bytes**, no NUL; either populated password control requires the other. Optional avatar integer referencing **the caller's own image**. Payment switches boolean; bank name/account name/account number optional strings/max **255/200/50**, routing number optional signed 32-bit integer. Bag `sellerProfile`. |
| Email (`user.change.email`, same view; shared with customers) | No validation; `isUnique()` then the existing emailed verification link, else the existing warning | `ChangeStoreEmailRequest`: email required/string/email/max **191**, unique in store `users`, ignoring the caller. Bag `changeEmail`. The controller still queues the same verification and leaves the current email unchanged until its callback. Resubmitting the caller's current email passes validation and retains **"Email already exists!"**; another account's duplicate is now a field validation error. |
| Withdrawal (`seller.money_withdraw_request.store`, `money_withdraw_requests/index`) | Inline amount required/numeric/>0; message optional string/max 2000; `SellerWithdrawService` already did the locked balance and minimum checks | `Seller\StoreWithdrawRequest` keeps those rules and adds amount **< 10^18** (`decimal(20,2)` storage range, also rejecting numeric overflow/INF), plus the unknown-input refusal. The service and its transaction, first balance >5 check, then minimum / balance minus pending requests, are **unchanged**. |
| New ticket (`seller.support_ticket.store`, `support_ticket/index`) | Nothing validated | Seller `StoreSupportTicketRequest` reuses the storefront rules: subject required/string/max **255** (`tickets.subject`), details required/string/max **10,000** (the back office's support-text cap; `longText` column), optional comma-separated image upload ids, **only the caller's**, at most **50**, unique and existing. Bag `storeTicket`. |
| Ticket reply (`seller.support_ticket.reply_store`, `support_ticket/show`) | Nothing validated; controller already scoped `ticket_id` to the caller and authored the reply as the caller | `StoreTicketReplyRequest`: ticket id required/integer; reply required/string/max **10,000** (`longText`, back-office support cap); same optional own-image references. The existing scoped lookup now also runs before validation for a well-formed id; another account's id remains **404**, even with invalid text. |
| Conversation reply (`seller.conversations.message_store`, `conversations/show`) | Nothing validated; `ConversationPolicy::participate` already checked membership | `StoreMessageRequest`: conversation id required/integer; message required/string/max **65,535 characters AND bytes** (`messages.message` is MySQL `TEXT`). Membership is checked through that same policy before validating a well-formed id, so another conversation stays **403**, an absent numeric id **404**. The controller's existing Gate check remains. |
| Product-query reply (`seller.product_query.reply`, `product_query/show`) | Inline `reply => required`, nothing else | Seller `ReplyProductQueryRequest`: reply required/string/max **10,000**, using `ValidatesSupportReplies` as the back office does (`product_queries.reply` is `longText`). The seller-scoped query is resolved before validation; another seller's query is **404**. The controller keeps its scoped lookup and redirect. |

Every endpoint refuses **unknown input, including empty unknown fields**, through
`RejectsUnexpectedInput`; only `_token` and `_method` are exempt transport fields. It inspects submitted
files and query input too. No seller-controlled owner/status field is added. All seven fixed Blade forms
are added to `FIXED_STOREFRONT_FORMS`; the ratchet already reaches seller Blade forms reliably, so no
scanner expansion or back-office baseline change was needed.

**The forms.** Shared marks and one legend per required form, native requirements, associated labels,
column-sized `maxlength` / numeric limits, retained old text and field errors. The profile and email read
only their own error bags, preserving the address modal's bags. Ticket and withdrawal refusals reopen
their modal. Password controls stay empty on a refusal; both legacy password names are now excluded
from flashed old input in `bootstrap/app.php`. Scalar guards prevent rejected array values in `old()`
from crashing the next GET. User HTML still stores as sent; escaped inputs, escaped conversation/history
output and existing `clean_html()` ticket output are unchanged.

**Dead and unshown input.** Removed the ticket-reply form's unused hidden `user_id` (the controller has
always derived the author from auth) and the query-reply form's unused hidden `conversation_id` (the
route id has always selected the query). Both now fail as unknown input. Profile `email`, withdrawal
`user_id/status`, ticket `user_id/status/code`, conversation `user_id/sender_id/receiver_id/attachments`
and any other unruled field are refused. None is needed by these controllers. All persisted profile
and bank fields are actually shown in the profile form.

### Judgement calls and existing defects

1. **Unchecked profile payment switches previously blocked a normal submission**: the controller assigned
   null to non-null integer columns when an unchecked checkbox sent nothing. They now save **0** through
   `boolean()`; this is the one persistence repair needed for the form to succeed. The bank-name error
   also incorrectly read the phone error; it now reads its own field.
2. **Password rules follow the existing six-character policy**, with no new current-password challenge
   (this controller never required one). The bcrypt input cap is 72 **bytes**, not Unicode characters;
   NUL input errored in the hasher, and longer input is unsafe because bcrypt truncates it. Tests cover
   a 72-byte multibyte password, 73 bytes, multibyte overflow and NUL. Browser maxlength is a character
   approximation; the server byte check is authoritative. The existing default exceptions configuration
   flashed `new_password` and `confirm_password` into the session; both are now redacted.
3. **Own uploads means own even for a privileged seller.** The shared upload-reference helper gains an
   opt-in `ownOnly` argument used by the two seller ticket requests; its other callers retain their
   previous behavior. A seller's imported avatar uploaded by someone else must be cleared or replaced
   before resaving the profile; it receives no grandfathered exception.
4. **Email verification is retained, with an existing defect to follow up.** `HomeController::new_verify`
   still has no input validation. More seriously, `email_change_callback` finds the account by the code
   and takes the target email independently from the URL: the code is **not bound to the emailed address**,
   and the callback does not recheck uniqueness. Fixing that recovery flow is outside this validation
   batch. No new verification flow was added.
5. **Text limits deliberately reuse the back office's 10,000-character cap** for details, ticket replies
   and product-query replies although their columns are `longText`. Conversation messages additionally
   enforce MySQL's 65,535-byte limit; the other existing `max:65535` validators elsewhere still count
   characters only and are not changed here.
6. **The withdrawal service and its money precision are retained.** Its float cast, locking, minimum,
   balance and pending-request behavior are unchanged. This batch does not redesign currency arithmetic
   or add a fractional-digit policy.
7. The shared email request exposes the customer profile's already-unmarked/unrequired email control to
   the audit (**one A and one B**). That customer form is not in this seller batch; it needs its own
   error bag display and marking in the account-form follow-up.

### Audit and verification

`php artisan forms:audit-required` ran on untouched `develop`, then after account, money and text writes;
`.ai/required-fields-audit-20261021.md` / `.json` are regenerated. **26 seller forms** throughout.

| Scope / class | Before | After account | After money | After text |
| --- | ---: | ---: | ---: | ---: |
| Seller A / B / C / D / E / F | 14 / 14 / 34 / 0 / 117 / 0 | 14 / 13 / 34 / 0 / 108 / 0 | same | 14 / 12 / 30 / 0 / 102 / 0 |
| Seller total | **179** | **169** | **169** | **158** |
| Overall A / B / C / D / E / F | 52 / 53 / 72 / 10 / 215 / 23 | 53 / 53 / 72 / 10 / 205 / 23 | same | 53 / 52 / 68 / 10 / 199 / 23 |
| Overall total | **425** | **416** | **416** | **405** |

All seven fixed forms have **no finding in any class**. The untouched back-office baseline remains **82**.
Tests were written and run before implementation: account **44 failures / 51 tests**, text **49 failures /
71 tests**, and money failures on shared marks/old input, unknown fields and numeric overflow (the initial
money render fixture also needed the existing `seedStoreCurrency()` helper). Array-old-input and password
redaction/bcrypt tests were separately seen failing before those fixes. No test was removed or loosened.
Focused Pest covers **198 tests** across the three seller files, storefront ticket regressions and
the ratchet (the final full run below also includes them). Hostile cases include boundary and over-limit
strings, arrays, HTML,
unknown/forged owner fields, second-seller and customer ids, and foreign/non-image/repeated uploads.

The cloud runtime uses PHP **8.5.11** and Laravel **13.34.0** from the installed source.
`wait_for_setup.py` reported no active Claude setup; the existing cloud PHP activation script was used.
PHPStan needed local socket access allowed by the execution environment; it passed with **0 errors**.
The first full run exposed the same stale Composer classmap and missing GD WebP support recorded in §49:
`composer dump-autoload --optimize --no-interaction` repaired the deleted-controller references (focused
**82 tests / 625 assertions** passed), and GD was rebuilt with WebP in the local `wms-seller-php:8.5` Docker
image and copied into the cloud PHP runtime (focused **87 upload tests / 403 assertions** passed). No
repository dependency, model, image-processing rule or test was changed to address those runtime failures.
The demo-mode early refusal is also pinned and remains ahead of the ownership lookup.
The old provisioned `node_modules` lacked the newer chart/editor/map dependencies: restored with
**npm 11.9.0 `npm ci`**, lockfile unchanged, then whole Vitest **336 files / 2,651 tests** and vue-tsc passed.
`npm run build` passed, including remote fonts. The focused Playwright spec passed **3 tests**, including
the new seller profile/withdrawal/ticket workflow and the existing uploader/login cases, with system
Chromium/FFmpeg and enforced CSP on local HTTPS. The first new browser attempt failed in fixture setup
because Shop is guarded; the fixture now uses DB writes, without changing model mass assignment.
The stale ignored `public/hot` marker was backed up outside the repository for the production build.

The final explicit `python3 .claude/scripts/pre_push_check.py --full` **passed before the last commit**:
Wayfinder generation, whole-repo ESLint, Prettier, vue-tsc, Vitest **336 files / 2,651 tests**, Pint,
PHPStan **0 errors**, config-cache clear, and parallel Pest **7,117 passed / 3 skipped / 40,684 assertions**
(**80.036 s**). The three skips are the existing MySQL-only longitude cases on SQLite; none was added or
quarantined. The full run itself does **not** execute Playwright, MySQL migrations or dependency audits.
Playwright was run separately as above; MySQL migrations were not run locally (no schema changed).
`bash .github/scripts/deploy-remote-test.sh` was also run separately: **all rollback checks passed**.
`git diff --check` passed. No bare pre-push or attested run was used, and no `CI-Verified` trailer was
written. Real PR CI and the subsequent staging deployment are checked before the final owner report.
The PR plan selects PHP, JS and E2E; MySQL, dependency-audit and deploy-script CI jobs are not planned.

Separately, `npm audit --omit=dev --audit-level=high` passed (**0 vulnerabilities**). Local
`composer audit --locked --abandoned=report` could not complete: the managed proxy denied
`packagist.org/api/security-advisories/` with CONNECT **403**. The PR planner skips dependency audit
because no dependency file changed, so Composer advisories are **not verified by this batch**. No
destination-policy workaround or advisory ignore was used.

### Deferred at the file cutoff

**Group 4, SHOP**: one request for the four `seller.shop.update` sections with a strict section-specific
input allowlist, column-sized shop/address/meta fields, own images, optional social URLs <=255 and numeric
coordinates within ±90/±180 (`shops.delivery_pickup_longitude` is already `decimal(18,15)`). Keep the current
section dispatch, auth-derived shop, routes, redirects and flashes. `seller.shop.verify.store` needs rules
from its configured dynamic fields and the real storage contract: `ShopVerificationStorage` stores private
pdf/doc/docx/png/jpg, <=20 MB; a missing verification-form setting currently reaches `foreach(null)`.

**Group 5, COMMERCE**: seller coupons already use `CouponRequest` (the brief's "validates nothing" does
not apply to every endpoint). Keep `CouponPolicy`, code uniqueness and owner derivation; validate discount
bounds, date order and seller-owned products before parsing dates. Its current `prepareForValidation()`
explodes unvalidated `date_range` and builds `details` before validation, so arrays/malformed ranges can
error. The bulk importer currently only checks `hasFile('bulk_file')`; match supported MIME/size and retain
ownership constraints after inspecting `ProductsImport`, without silently enabling a second products writer.

**Separate product/digital-product PR (ownership settled by Option A, §52)**: do not widen `Product`'s
`#[Fillable(['name', 'ar_name', 'thumbnail_img', 'published'])]` as a validation fix. Physical products
already use `ProductRequest`: it requires name/category/unit/minimum quantity/unit price and conditionally
discount; name max **255** exceeds `products.name` **200**, lacks a string check, and category/unit are
only `required`. `ProductService::sellerAttributes()` is an input allowlist, **not** a replacement for
rules or the model allowlist. Its store/update call `Product::create()` / `->update()` with many more
columns, risking silently discarded attributes (or strict-mode exceptions). Stocks/tax/translation writes
need their own shape, ranges and ownership validation and a transaction before the current destructive
stock rebuild. Services assume `tags[0]` is JSON, choice arrays and variant keys have valid shapes, prices
and quantities are usable, and dates parse; all require hostile tests. Digital create/update accept plain
`Request`, assign many columns directly (so the mass-assignment limit does not protect them), assume
`tags[0]` and JSON values exist, take unvalidated photos/downloads/prices/tax, and lowercase an unvalidated
slug on update. Keep `ProductPolicy::updateAsSeller`, subscription checks, approved/published invariants,
localization, sanitization and current URLs/flashes. Inspect every upload reference and nested tax/choice/
stock product id against a second seller. Pin column boundaries, min/discount arithmetic, transaction
rollback, and the known MySQL decimals/choice-order edit defects (D13) before changing these writers.

## 51. Seller forms, phase 3 batch 2: shop, verification, coupons and bulk upload (2026-10-22)

Runtime: **GPT-6 Codex**. The requested `claude-opus-5-5 / high` is unavailable; the exact active
variant and effort are not exposed, so neither is claimed. This continuation starts at `develop`
**7524aa0a**, the merge of [PR #464](https://github.com/arb-rajab/warehouse_management/pull/464).
The original brief's previous [PR #463](https://github.com/arb-rajab/warehouse_management/pull/463)
is recorded in §50. `develop` was fetched before editing: account, money and text writes were already
merged, so they were not implemented again. The owner authorized one PR to `develop`, separate SHOP and
COMMERCE commits, and merging only after every CI check is green. Product/digital-product forms and
writers are unchanged. At **37 files**, this batch stops after completing SHOP and COMMERCE; the separate product writers remain deferred.

### Rules, per form: before and now

| Form / route | Before | Now |
| --- | --- | --- |
| Shop basic information, `seller.shop.update` | No validation; name, phone, address and metadata required only in HTML | `Seller\UpdateShopRequest`: name required/string/max **200**, address required/string/max **500**; nullable phone and meta title string/max **255**; nullable meta description string/max **65,535 characters and bytes** (MySQL TEXT). Logo is an integer identifying the caller's own image. Shipping cost is required/numeric/at least zero/max **999999999999999999.99** only for `seller_wise_shipping`; otherwise its presence is refused, even empty. |
| Pickup coordinates, same endpoint | No validation | Both keys present; both nullable, each required with the other; numeric latitude **-90..90**, longitude **-180..180**. The widened longitude column from #461 is unchanged. |
| Banners, same endpoint | Any image references, no validation | Optional comma-separated own-image ids; no duplicates, at most **50**. Top banner takes one id; top/full-width/half-width banner strings max **191** (their columns). Sliders are longText and the same 50-image reference limit bounds their size. |
| Social links, same endpoint | Arbitrary unvalidated values | Nullable strings, HTTP/HTTPS URLs, max **255**, for Facebook, Instagram, Google, Twitter and YouTube. |
| `seller.shop.verify.store` | Configured fields required only in HTML; storage checked file content/20 MiB while writing, so a later refusal could leave earlier files behind; absent configuration crashed | `Seller\StoreShopVerificationRequest`: every configured field required; text string/max **10,000**; select/radio restricted to configured choices; multi-select a nonempty list of distinct configured choices (max 100). Files must pass the existing storage's content checks, PDF/Word/PNG/JPEG, at most **20 MiB**, before any file is stored. Errors use the actual `element_N` field, bag `sellerVerification`. |
| `seller.coupon.store` / `.update` | Root `CouponRequest`; code required/global unique/max 255; discount numeric/min 1; date splitting and details construction happened before validation; products not scoped | Seller request: enums for type and discount type; code required/string/max **255**, same global uniqueness, ignoring only the updated coupon; discount numeric/min **1**/max **999999999999999999.99**, percent <= **100**. Cart minimum and maximum discount numeric/min **1**/max **999999999999999999.99**; product coupons require a nonempty list (max 1,000) of distinct integer ids owned by this seller. Fields for the other coupon type are refused even empty. Date range is a real `MM/DD/YYYY - MM/DD/YYYY` range, end >= start, fitting signed INT timestamp columns. Details and timestamps are derived only after validation, never accepted from the client. |
| `seller.bulk_product_upload` | Only `hasFile`; invalid or missing input could silently do nothing or reach the spreadsheet reader | Seller request shares `ValidatesProductSpreadsheet` with the back office: required/file/xlsx,xls,csv,txt/**10 MiB** contract, adds strict input refusal and a reader/content check matching the extension. Spreadsheet row errors appear under `bulk_file`; the existing importer, sanitization, seller ownership, subscription/approval logic and notifications remain unchanged. |

Every endpoint rejects unknown submitted keys (query input and uploaded files included), even empty ones.
`_token` and `_method` retain their transport exemption. Shop forms replace the unused hidden `shop_id`
with a validation-only `section`; original clients without it still infer a section from the original
controls. Mixed sections are refused. The controller still selects the shop from auth and retains its
existing dispatch. Shop bank/status/owner fields are not enabled here; the profile's bank controls remain
their separate writer. Coupon `user_id`, `id`, `details`, `start_date`, `end_date` are refused: route/auth
identify the row and owner, and the controller needs derived dates/details only. The edit page keeps its
unnamed id for the unchanged AJAX helper. The import endpoint accepts only `bulk_file`.

### Forms, judgement calls and existing defects

- All eight fixed Blade forms use shared marks, required attributes and a legend where required;
  errors appear under their controls, text is retained with guarded `old()` values, and passwords/files
  are never repopulated. Shop and verification use named bags. User HTML still follows the existing
  escaping/sanitization paths; no output escaping or persisted-text cleaning was changed.
- **Nullable shop phone/metadata are optional.** Their old browser requirements exceeded the controller
  and database. Name/address stay required because they select the basic-information write. Pickup
  coordinates may both be cleared; a half-filled pair is refused rather than silently ignoring it.
- **No foreign-upload grandfathering.** Even a currently stored image must belong to the seller. Legacy
  images owned by another account must be replaced or cleared before that section can save.
- **Verification configuration:** missing, malformed or unsupported configuration renders without a
  crash and disables Apply; a forged POST is refused without recording an empty application. All fields
  shown by a valid configuration remain required, as the old form intended. The 10,000-character text
  limit is a deliberate support-style cap within the longText storage column.
- **Coupons are bounded by signed INT dates:** the date picker can store whole days from 1901-12-14 to
  2038-01-19. Supporting later dates requires a separate column migration. Global code uniqueness is
  preserved, including across owners; changing to per-seller uniqueness is not part of this PR.
- **Coupon old input was broken:** the page fetched its fields through a second AJAX request after
  consuming the flashed values. Create/edit now render a shared seller field partial immediately;
  switching type still uses the original helper routes. The unused posted edit `id` is removed, and the
  edit template's stray parenthesis is fixed as part of making that form render and submit reliably.
- **Bulk import defects:** renamed text could pass the MIME list as `txt` and then crash the XLSX reader;
  the selected reader must now recognize its content. Row errors previously missed the file field;
  both admin and seller call `ProductSpreadsheetImporter` for the admin's existing error mapping, with
  unchanged import behavior. This does not enable a new products writer: the seller importer already
  uses `Product::forceCreate` and derives ownership from auth.
- **Reported, not fixed:** `ProductsImport::downloadThumbnail()` creates external-image upload rows
  without `user_id`. The new own-image rules cannot reuse such unowned rows in shop/profile fields.
  The seller import POST also does not repeat its GET page's verification-status check. Both predate
  this validation work; no middleware/permission/ownership flow was added or changed. The root
  `CouponRequest` remains on API routes and still parses dates before validation there; only the
  requested seller-panel endpoints use the new seller request. The importer also allows unit length
  50 against `products.unit` 20 and has no column-sized price ceiling; carry those row-rule defects
  into the separate products writer review.

### Audit and ratchet

`FIXED_STOREFRONT_FORMS` gains shop update (all four sections), verification, both coupon forms and bulk
upload. The scanner now evaluates rules with literal hidden values (cached separately per input), reads
conditional Blade marks as dynamic, and follows literal field partials while preserving their source
file/line. Nested forms and recursive cycles are not merged into an outer form. These are checked with
focused scanner tests, including a deliberately missing required attribute after a correct section scan.
The back-office baseline remains **82**: no listed finding disappeared.

| Audit | Before A/B/C/D/E/F | After |
| --- | --- | --- |
| Seller panel, 26 forms | 14 / 12 / 30 / 0 / 102 / 0 (**158**) | 0 / 10 / 23 / 0 / 87 / 0 (**120**) |
| Overall | 53 / 52 / 68 / 10 / 199 / 23 (**405**) | 39 / 50 / 61 / 10 / 184 / 23 (**367**) |

After SHOP: seller **138** (14/12/24/0/88/0), overall **385** (53/52/62/10/185/23). The current-date audit is saved in
`.ai/required-fields-audit-20261022.md` and `.json`. All **120** remaining seller findings are in
the four explicitly excluded physical/digital product create/edit forms; every other named write form
has no finding. The five other seller forms in the 26-form count are read-only search/filter forms.

### Verification

SHOP was tested before implementation: **75 failed / 5 passed**, exposing absent validation and array
500s. The corrected SHOP tests reached **82 passed / 321 assertions** before the final extra ownership
cases. COMMERCE's valid pre-implementation baseline was **54 failed / 14 passed**; the first corrected
implementation reached **68 passed / 213 assertions**. The literal-partial scanner test and the bulk row
error test were each observed failing before their fixes. The combined focused run passed **230 tests / 833 assertions** across
`SellerShopFormsTest`, `SellerCommerceFormsTest`, `LegacyShopVerificationTest`,
`LegacySellerScopingTest`, `RequiredFieldsAuditTest`, `RequiredFieldsRatchetTest`, and the admin
`ProductBulkUploadControllerTest`. The later pickup-help case was separately seen failing and then
passed (**1 test / 4 assertions**) and folded into the unpushed SHOP commit. Exact **20 MiB**
verification and **10 MiB** bulk files are accepted; one byte more is refused with no new file/import
write. A separate red/green check pins the metadata byte-limit message, so multibyte overflow is
not misleadingly described as a character-count error. The bulk boundary case uses a recognizable CSV and asserts that the importer is called exactly
once, for the accepted file. Exact decimal ceilings are also pinned: `999999999999999999.99`
passes for shipping/discount, while a higher-precision value that would round past storage fails
before a write (the three refusal cases were observed failing first).

`npm ci` with npm **11.9.0** restored missing frontend packages without changing the lockfile.
Wayfinder was regenerated, stale Composer class-map entries refreshed with `dump-autoload --no-scripts`,
and `npm run build` passed with the managed proxy/CA, including fonts. PHP is **8.5.11** with a 1 GiB
memory limit in a local Docker image; GD was rebuilt with WebP for the existing upload suite. The first
GD build failed because apt's restricted user could not read the mounted CA; a readable secret mount
and apt's explicit CA setting fixed it without disabling TLS verification.

Whole Vitest passed **336 files / 2,651 tests** with `--maxWorkers=2`. Unrestricted, four-worker, and later one/two-worker attempts had local scheduling
timeouts; both affected files passed **184 tests** with two workers before the full pass.
The final explicit full run again passed **336 files / 2,651 tests**; Vitest logged a worker-shutdown
timeout warning but returned success after completing every test. Repository test code, assertions and CI deadlines are unchanged. The final local pre-push run uses
a temporary npm wrapper with **two workers** and **30-second test/hook deadlines** for this slower
runtime. Every test still runs; real CI uses the repository defaults and must pass before merge.
`npx prettier --check tests/e2e/store-legacy-uploader.spec.ts`, PHPStan and `git diff --check` were also run.

Focused Playwright (`tests/e2e/store-legacy-uploader.spec.ts`) passed **7 tests** on local HTTPS with CSP
enforced and system Chromium/FFmpeg. The first combined new workflow exceeded its 30-second limit;
it was split into four independent cases, retaining coverage. A subsequent runtime attempt found an
orphaned Docker-backed test server; stopping that task-owned server and restarting its PHP container
released the port. The coupon date picker normalizes reversed ranges before POST, so browser error
recovery now uses a real duplicate code; both coupon endpoints' reversed-date refusals remain pinned
in Pest. No browser assertion was removed from the existing three cases.

The explicit `python3 .claude/scripts/pre_push_check.py --full` is run before the final COMMERCE commit.
Its first attempt stopped at ESLint on test-created private directories owned by the container user;
workspace-user ownership was restored while retaining private permission modes. A later full attempt
passed the frontend/static checks but found a global Pest helper-name collision with the existing admin
verification tests; the new seller fixture was renamed. A backend-only retry was stopped before completion to consolidate verification into a fresh explicit
`--full` run, with four CPUs available to the unchanged parallel Pest runner. The completed full run passed Wayfinder generation, whole-repo ESLint, Prettier, vue-tsc,
Vitest (**336 files / 2,651 tests**), Pint (**1,360 files**), PHPStan (**890 files / 0 errors**) and config
cache clear. Parallel Pest completed with **7,290 passed / 3 skipped / 1 failed / 41,319 assertions**
(**797.14 s**): the existing admin verification round-trip omitted its configured, browser-required
Licence file and expected null storage. That test now first asserts missing-file refusal with no writes,
then posts a real PDF and checks private storage while retaining the ordered-answer and JSON assertions.
Pint on that file and its complete focused suite passed (**34 tests / 254 assertions**). No application
code changed after the full run; the final commits still require the complete real CI suite before merge. No bare pre-push or attested run was used, and no `CI-Verified` trailer
was written. MySQL migrations and dependency audits are not part of `--full`; no schema or dependency
file changed. Playwright ran separately as above. Real CI and the deploy result are checked before the
final owner report.

### Separate product/digital-product PR

Carry forward §50's detailed writer inventory. Option A (§52) settles column ownership;
validation and explicit seller persistence remain the next separately gated PRs.
Do not widen `Product`'s `#[Fillable(['name', 'ar_name', 'thumbnail_img', 'published'])]` as a validation fix.
Physical `ProductRequest` assumes name/category/unit/minimum quantity/unit price and conditional discount;
its name max 255 exceeds the 200 column and category/unit lack types. `ProductService::sellerAttributes`
is only an input allowlist; its create/update attributes exceed the model's fillable limit. Digital
writers accept plain Request and assign columns directly. Both need column-sized rules, hostile shape,
price/date/discount boundary tests, own upload/nested stock/tax/choice ids, transaction rollback before
stock rebuild, and pinned policy/subscription/approval/localization/sanitization behavior. Inspect the
`tags[0]` JSON assumptions, malformed variant/choice inputs, unvalidated digital slug/files/prices/tax,
and D13's MySQL decimal/choice-order edit defects. Account email-callback defects remain recorded in §50.

## 52. Product column ownership: Option A and origin collisions (2026-10-22)

Runtime: **GPT-6 Codex**. The requested `claude-opus-5-5 / high` cannot be selected here;
the exact active variant/effort are not exposed and are not claimed. Branch
`codex/product-column-ownership` starts at fetched `origin/develop` **8899d105**,
the merge of [PR #465](https://github.com/arb-rajab/warehouse_management/pull/465).
Previous seller validation PRs are [#464](https://github.com/arb-rajab/warehouse_management/pull/464)
and [#463](https://github.com/arb-rajab/warehouse_management/pull/463).
This is **PR A**. PR B (seller physical persistence/validation and D13) must wait for
this PR's green merge and successful deploy; PR C (digital and remaining defects)
must likewise wait for B's deployment. No skip-CI or hand-written attestation is used.

### The owner's decision and existing rows

**Option A replaces the pending decision in §§5, 14–16, 21–23 and the former
“only writer” rule in shared-database.md.** The sync owns exactly `name`, `ar_name`,
`published` of feed-origin rows. The store owns every other column and
may write those three only on products it created itself. `synced_at` is sync
metadata: non-null identifies feed-origin; null identifies store-created or
unclassified. Approval authority, deletion rules, foreign-key outcomes, and
publication/stock side effects for permitted store-origin changes are unchanged.
The previous Eloquent upsert also implicitly changed `updated_at`: Option A now
leaves that store-owned timestamp untouched on existing rows and records sync time
in `synced_at`. New rows retain their creation timestamps.

Migration `2026_10_22_101723_add_synced_at_to_products_table` is an ordinary
nullable-column addition, without the old production/table-existence no-op guard.
**All existing rows remain null.** No name, unchanged publication state, legacy
`from_api`, `mat_id` or old timestamp proves origin. This deliberately protects
unknown rows and temporarily stops feed updates to them. The owner must review
an authoritative creation/feed inventory and reclassify exact verified IDs;
shared-database.md provides the owner-run SQL procedure. It has not been run on
production or staging by this work. The initial classification timestamp is not
claimed to be the time of a historic sync. Rollback drops only the marker; taking
this release back also takes back its ownership enforcement, so keep a reviewed
classification inventory outside the database before rolling it back.

### Implementation and enforcement

- The sync keeps 500-row chunks, hourly scheduling/overlap protection, 200/191
  character truncation, configured URL, error handling and no-pruning behavior.
  It sets/refreshes the marker on every accepted row. The conditional SQL upsert
  preserves **every** column of a conflicting null-origin row, including timestamps.
  A race with a newly auto-incremented store row is protected at the write itself.
  Conflicts are counted and every ID is named in a warning log and command output.
  The marker assignment is last because MySQL evaluates SET assignments in order.
- `ProductColumnOwnership::SYNC_COLUMNS` holds the one list. Product refuses
  changed protected assignments early, before legacy related-row writes, and
  ProductBuilder checks the current rows again under locks at the actual write.
  It covers direct assignment, `fill`/`forceFill`, services, normal/quiet saves,
  bulk Eloquent updates and counter updates. Mixed-origin bulk writes are refused
  atomically; store upserts and marker changes are refused. Raw/base-builder
  product writes outside the sync are prohibited by an architecture-style AST test.
- Every model insert resets the marker to null, including duplication/quiet
  replication of a feed row. InHouseProductWriter, the admin/seller digital
  controllers, seller ProductService, switches, the seller API and legacy imports
  use this model. The guard does not widen fillable or grant approval permissions.
  A legacy import attempting protected feed changes now fails explicitly; it does
  not gain sync privileges. Store-owned fields remain editable on feed rows.

### Seller forms and outstanding work

No seller validation/persistence repair has started in PR A. Characterization
of the current physical/digital forms belongs to PR B's first step, after A is
merged and deployed. The four-column fillable defect remains a defect; this PR
makes no claim that the seller ProductService now persists its discarded fields.

| Form | Old rules | Rules after PR A |
| --- | --- | --- |
| `seller.products.store` | ProductRequest: required name (max 255), category, unit, numeric minimum/unit price, conditional discount; other controls largely unvalidated | Unchanged; PR B must narrow the name to 200 and validate the full form before explicit persistence. |
| `seller.products.update` | Same ProductRequest and legacy mass assignment | Unchanged; the shared ownership guard now refuses protected changes on feed-origin rows. |
| `seller.digitalproducts.store` | Plain Request, direct unvalidated assignments | Unchanged; PR C replaces these with validation and an explicit transactional writer. New rows have null origin. |
| `seller.digitalproducts.update` | Plain Request, direct unvalidated assignments | Unchanged; feed name changes are now refused before destructive stock replacement. |

D13 is **open**: MySQL decimal representation and mismatched stored choice/stock
names still require failing MySQL regression tests and their fix in PR B. D16's
four seller forms remain open. Conversation/bulk row-rule defects recorded in
§§50–51 remain for the subsequent batches; existing validated forms are retained.
The current committed audit is **367** findings: A/B/C/D/E/F =
**39/50/61/10/184/23**; seller **120** = **0/10/23/0/87/0**. Ownership work does
not remove a form finding or a ratchet baseline entry (back-office baseline 82).

### Verification

`wait_for_setup.py` ran first and reported no active setup. The stale checkout
was fetched to #465 before edits. PHP **8.5.11** / Laravel **13.34.0** run in a
local Docker container; MySQL **8.4** uses the isolated `warehouse_test` database
with the requested root/password credentials. Existing local vendor/assets were
checked. Missing frontend packages were restored using npm **11.9.0 ci** without
lockfile changes; npm's cache is under the task workspace. The stale Composer
classmap was regenerated. GD was rebuilt with WebP, as existing upload tests need.

Focused ownership/sync/migration tests passed on SQLite (24 tests/211 assertions)
and MySQL (25 tests/212 assertions, including the architecture test added later).
The complete focused admin physical/digital forms plus ownership tests passed
**118 tests/867 assertions**, including seller physical/digital refusal before
stock changes. Earlier failures were snapshotting an unrefreshed factory model
and the stale local Composer classmap; corrected without loosening assertions.
Pint passed, PHPStan reported **0 errors**, and the required script selftests
passed. The final full run and complete MySQL-group results are recorded below
when completed; a focused result is not represented as full verification.

The complete local MySQL group (the nine files listed in the CI MySQL job and
`pre_push_check.py`) passed **77 tests/389 assertions**, with **6 existing
SQLite-specific skips** on MySQL. The fresh audit again measured **367 overall /
120 seller** findings. `bash .github/scripts/deploy-remote-test.sh` passed all
rollback checks; `npm audit --omit=dev --audit-level=high` reported **0 vulnerabilities**.
`npm run build` passed, including fonts through the managed proxy/CA.
Focused Playwright on `store-product-create`, `store-product-edit` and
`store-digital-product-forms` passed **8 tests (53.3 s)** with enforced CSP,
system Chromium/FFmpeg, and the production build. The first runtime attempts
exposed Docker bridge proxy trust and separate `/tmp` directories; the runtime
now trusts only the actual local bridge/loopback and uses a shared task temp
directory. No browser assertion, timeout, CSP or repository test code changed.

The first explicit `--full` run passed Wayfinder, ESLint, Prettier, vue-tsc,
Vitest (**336 files/2,651 tests**), Pint and PHPStan (**893 files/0 errors**),
then Pest failed before loading its suite because Artisan's child PHP inherited
the container's 128 MB default. The local PHP ini now sets 1 GiB for children
as well as the parent. A fresh explicit `--full` run follows this runtime fix.

Final ownership review removed one implicit old Eloquent behavior: the sync no
longer changes the store-owned `updated_at` on existing rows. The stronger
preserve-every-other-column assertion passes on MySQL (**10 sync tests/104
assertions**); the complete final MySQL group passed **77 tests/390 assertions**
(**205.46 s**, the same 6 existing driver-specific skips). New feed rows still
receive creation timestamps. `synced_at` carries last-sync time. After this code
correction the explicit full gate is repeated on the final implementation.
The previous full pass had **7,312 passed/3 existing skips/41,533 assertions**;
it is not substituted for the final-tree pass.

**Final explicit `python3 .claude/scripts/pre_push_check.py --full`: passed before
PR A's last commit.** It ran setup readiness, Wayfinder generation, whole-repo
ESLint, Prettier, vue-tsc, Vitest (**336 files/2,651 passed**), Pint, PHPStan
(**893 files/0 errors**), config-cache clear, and parallel Pest (**4 processes,
7,312 passed/3 existing MySQL-only skips on SQLite/41,534 assertions, 198.66 s**).
The separate final MySQL, browser, npm audit, deploy rollback, CI-planner (**23
passed**) and script-selftest results above are not claimed as part of `--full`.
Composer audit was not run locally; the real CI Dependency audit remains required.
No test was skipped or quarantined to repair a failure, no bare or attested run
was used, and no CI-Verified trailer was added. `git diff --check` passed.
The final source-comment clarification changes no executable code.

## 53. Seller physical catalogue: explicit persistence and validation, PR B1 (2026-10-22)

Previous PRs: [#465](https://github.com/arb-rajab/warehouse_management/pull/465)
completed shop/commerce validation; [#466](https://github.com/arb-rajab/warehouse_management/pull/466)
settled Option A (§52), merged as `40cb27eaa8703135321a9f44c92b34cc6bf6baac`.
Its [develop deploy](https://github.com/arb-rajab/warehouse_management/actions/runs/39789726951)
succeeded before this work started. The mandatory `artisan migrate --force`
path succeeded; individual migration logs/database rows were not independently
visible. The tested-tree lookup succeeded and the merged-suite repeat was
correctly skipped. All twelve PR checks succeeded: scan lookup, Semgrep, CI
planning, Prettier, PHPStan, dependency audit, deploy-script tests, JavaScript
checks, Pint, Pest, MySQL migrations and Playwright. No subscription API was
available, so checks were monitored through the GitHub API.

The physical work is split at the owner's approximately 30-file limit: **B1**
repairs persistence/validation and protects inconsistent stock; **B2** finishes
D13's back-office decimal display and unmatched-stock presentation. Each must
merge green and deploy successfully before the next starts. Digital forms and
remaining conversation/bulk defects stay in **C**, after both physical deploys.

### Characterization before implementation

Two real seller endpoint tests passed against #466 before changing the writer
(20 assertions). Only inside those tests, silent-discard protection was restored
to production's setting and then reset in `finally`; this exposed the actual
production defect rather than the local strict-mode exception. The final tests
assert repaired persistence, and do not require the old defect to survive.

`Product` still has four fillable columns: `name`, `ar_name`, `thumbnail_img`,
`published`. Physical create retained the posted name/thumbnail and computed
publication. Physical update retained name/thumbnail (the base name only in the
default language). The tested update with an unchanged null thumbnail changed
**only name** in `products`. These intended product fields were discarded:

- Posted columns: `category_id`, `brand_id`, `unit`, `weight`, `min_qty`, `tags`,
  `barcode`, `photos`, `video_provider`, `video_link`, `external_link`,
  `external_link_btn`, `unit_price`, `discount`, `discount_type`, `current_stock`,
  `description`, `shipping_type`, `est_shipping_days`, `low_stock_quantity`,
  `stock_visibility_state`, `cash_on_delivery`, `is_quantity_multiplied`,
  `meta_title`, `meta_description`, `meta_img`, `pdf`.
- Derived/server columns: `slug`, `discount_start_date`, `discount_end_date`,
  `shipping_cost`, `colors`, `choice_options`, `attributes`, and on create
  `added_by`, `user_id`, `approved`. A discarded value could happen to equal the
  column default; that did not mean the service saved it.

The create row instead had `added_by=admin`, `user_id=null`, `approved=1`, no
category/unit/slug and base price zero, despite the service computing seller
ownership and pending approval. Stock SKU/price/quantity, translations
(name/unit/description), taxes, and the separately assigned `variant_product`
were separate writes and could survive. The stock price was 12.50 while the
base price stayed zero. This was a persistence defect, never a product design.
The retired refund addon's `refundable` control has no products column and is
not offered; forged values are refused. The seller API retains the legacy
service and is outside these four panel-form repairs; its ownership guards
from #466 still apply.

### Old and new physical form rules

| Form | Before | Now |
| --- | --- | --- |
| `seller.products.store` | `ProductRequest`: name required/max255 without string; category/unit merely required; minimum/price numeric without lower/column bounds; conditional discount below unit price or 100; remaining controls unvalidated. Service silently discarded product columns. | `Seller\PhysicalProductRequest`: name required/string/max200; physical category and optional brand must exist; unit required/string/max20; weight decimal(8,2), nonnegative (blank saves its nonnullable zero default); minimum integer 1..2147483647; amounts nonnegative decimal(20,2), at most two fractional digits; discount below unit price or 100%; real ordered date range with signed-INT timestamps; tags in the existing one-element Tagify JSON list, each plain string value and joined max500; URLs/providers/stock visibility enumerated and column-bound; quantities integer and bounded; only the seller's correctly typed existing image/document uploads; strict unexpected keys, including null/query/file fields. Explicit owner, origin null and approval policy. |
| `seller.products.update` | Same weak request; nontransactional stock deletion/replacement, product columns discarded, then translation/tax writes. | Same bounds plus an existing language and an optional free slug of max255; ownership checked before validation and rechecked under the row lock. Base name/unit/description change only in the default language; translations update independently. Owner, approval and publication are preserved. Stock and tax/translation changes share the product transaction. |

Colours/attribute ids and each value must exist; choices are bounded lists,
variant names must be unambiguous and fit varchar255, and the Cartesian product
is capped at 500. Each generated legacy `price_*`, `qty_*`, `sku_*`, `img_*` key
has its own rule; unrelated dynamic keys are refused. Dots and spaces use safe
wire keys while stored variant names retain their original spelling. Arrays in
scalar fields are refused. Gallery ids are distinct, existing owned images,
at most 50. There is no grandfather exception for another account's upload.
Shared field partials use the required mark/legend components, associated labels,
ARIA and scalar-safe old input; the conditional shipping-cost requirement and
mark follow the selected shipping type. The uploader and product controls remain available, with retired server-owned
hidden fields removed. The edit-only cash switch and shipping/tax visibility
were accidentally flattened into create behavior here; §54 restores that parity.

### Writer, ownership and D13 decisions

`SellerProductWriter` explicitly assigns the validated columns; common physical
computations and related-row writes live in `PhysicalProductWriter`, used by
both it and `InHouseProductWriter`. Fillable was not widened. Create sets
`added_by=seller`, the authenticated seller id, `approved=0` when
`product_approve_by_admin=1` (otherwise 1), physical type and a null origin.
This restores the service's intended pending policy; no seller approval ability
was added. Update locks the owned row and preserves authority columns. Cache
and compiled views clear through an after-commit callback. Failure replacing a
stock row rolls back product and related rows.

Option A remains exact: sync owns only `name`, `ar_name`, `published` on feed
rows; store writers own every other column and may write those three only on
store-origin rows. Existing pre-marker rows remain null/unclassified until the
owner reviews exact ids (§52); no automatic reclassification occurs here.
Every physical endpoint accepting a product id is tested with a second seller:
edit, update, duplicate, delete, publish, feature and SKU-edit preview. The latter
previously exposed other sellers' stock and now scopes to the caller. Preview
requests validate shapes/references and generated rows; attribute option HTML
is escaped. The attribute helper now returns a real JSON response, preventing
Blade middleware from appending markup to its payload. Ordered option loading
and stale-preview rejection keep asynchronous responses from changing the
selected variant order. The list eagerly loads the translations, categories and
stocks it renders; multiple products no longer trigger strict-loading errors.

Both D13 regressions were demonstrated failing on local MySQL **before** the
fix: `12.50` versus SQLite's `12.5`, and saving generated default variants
silently deleting an unmatched stored row. B1 now refuses implicit loss of a
preexisting choice/stock mismatch in both physical writers. The seller form
lists the unmatched variant, price, quantity and SKU for review; the owner must
explicitly reconcile choices to retain that stock before saving. Normal removal
of variants from a consistent product keeps its existing behavior. B2 completes
the back-office warning and exact decimal string normalization without a float
conversion. No automatic permutation of imported choice meanings is guessed.

Audit: **367 → 290** overall (A/B/C/D/E/F **39/40/54/10/124/23**), seller
**120 → 43** (**0/0/16/0/27/0**), back-office baseline **82** unchanged. Both
physical forms join the required-field ratchet. Dynamic variant controls are
also checked on rendered forms and through real browser submissions; unresolved
runtime names are not claimed to have become statically auditable.

### Verification

- Before-change endpoint characterization: **2 passed, 20 assertions**; both
  D13 tests then failed on MySQL before their fixes (decimal representation and
  destructive unmatched-stock save), with the output retained outside the repo.
- Final focused physical Pest: **164 passed, 580 assertions**, including hostile
  scalar/nested inputs, owned uploads, every product-id endpoint, rollback,
  after-commit cache behavior, JSON content type and multi-product rendering.
- Complete MySQL group (MySQL 8.4, `warehouse_test`, root/password): **240 passed,
  6 existing driver-specific skips, 966 assertions**. After the final empty-weight
  correction, its focused MySQL regression also passed (**1 test, 4 assertions**).
  The first full-check attempt was superseded by that correction; a subsequent
  local ESLint traversal failed on Docker-owned private test folders. Ownership
  was corrected, without exclusions, before the final full run. The seller test file is now
  in both CI's MySQL job and the pre-push MySQL file list.
- Focused Vitest: physical variant fields/form helpers, **2 files, 14 passed**.
- Playwright: the retained uploader/seller spec, **9 passed** (including both new
  physical flows). It retains the existing legacy Blade CSP policy; this is not
  claimed to have removed the legacy inline-script exception.
- Required-field ratchet: **4 passed, 7 assertions**; fresh audit **290** overall,
  **43** seller, baseline **82**. `ci_plan_test.py`: **23 passed**.
- Final explicit `python3 .claude/scripts/pre_push_check.py --full` **passed**:
  setup readiness, Wayfinder generation, whole-repo ESLint, Prettier, vue-tsc,
  Vitest **336 files / 2,651 tests**, Pint, PHPStan **897 files / zero errors**,
  config clear, Pest **7,476 passed / 3 existing MySQL-only skips on SQLite /
  42,129 assertions**, four processes, 230.90 seconds. This explicit mode does
  not run the MySQL/browser/audit lanes; the separate local runs are listed above.
  Composer/npm security audit remains a real CI gate for B1. No bare/attested
  run or manual trailer was used. `git diff --check` passed.


## 54. Physical edit reconciliation and MySQL boundaries, PR B2 (2026-10-22)

Previous PRs: #465 (shop/commerce), #466 (Option A, §52), and
[#467](https://github.com/arb-rajab/warehouse_management/pull/467) (physical B1,
§53). #467 merged as `675aaa6b30e31c1390205e26a041abbe2efe5bd6`; its
[develop deploy](https://github.com/arb-rajab/warehouse_management/actions/runs/39801133946)
succeeded before B2 started. All twelve PR jobs succeeded: existing-scan lookup,
Semgrep, CI planning, Prettier, PHPStan, dependency audit, deploy-script tests,
JavaScript checks, Pint, Pest, MySQL migrations and Playwright. The deploy's
mandatory `migrate --force` path succeeded; B1 added no migration. Individual
migration logs are not visible. No subscription API is available; GitHub API
polling supplies the check conclusions. Digital work remains gated until B2 is
merged green and deployed.

D13 is repaired in both physical panels. Stored decimal values now use exact
string normalization: MySQL `12.50` and SQLite `12.5` both display `12.5`, without
rounding large decimal(20,2) amounts through floats. Product, default-variant,
variant and tax defaults use it; user-entered old input is retained as entered.
The back office now lists unmatched stored stock, as the seller panel already
did. Both panels expose explicit attribute ordering so an owner can reconcile
legacy choices to the actual stored stock name, preserve its id, price, quantity
and SKU, and then save. Neither guesses the meaning of imported permutations.
The B1 refusal of destructive mismatch saves remains in both writers.

Real MySQL failures were demonstrated before the fixes: endpoint default price
`12.50` versus `12.5`, loose comparison collapsing distinct `01` and `1` values,
and valid Unicode names producing an empty or over-255 generated slug. Exact
comparisons now preserve numeric-looking values. `ProductSlug` supplies a bounded,
nonempty generated slug and bounded unique panel-copy slugs; an explicit custom
slug still follows its existing max255 rule. Names that cannot transliterate
use a random `product-` fallback. Collision suffixes fit within 255 characters.
Seller API duplication is outside these panel repairs.

Seller preview can retain typed row metadata from the previous attribute order
while computing a new order; those obsolete keys are validated and ignored only
by this read-only preview. Actual store/update requests still refuse unknown
keys. Brackets, asterisks and backslashes in existing attribute values use safe
hashed input keys while the stored variant name remains unchanged. The preview
never writes a product. Existing physical store/update rules from §53 remain,
with strict distinct choice strings so `01` and `1` are different values.

A parity review found a B1 shared-partial regression: edit inherited create's
hidden cash-on-delivery value and section settings. Edit again offers its original
cash checkbox when cash payment is enabled, preserves an unchecked old value,
and shows shipping/estimate and active taxes independently of the create-only
section settings. Create retains its original behavior. A real-endpoint test
checks those differences and saving cash off. Approval and deletion authority
are unchanged.

**Ownership remains Option A:** sync owns exactly name/ar_name/published on
feed-origin rows; store owns all other columns and those three only on its own
rows. Existing pre-marker rows remain null/unclassified pending owner-reviewed
exact-id reclassification (§52). No new migration or reclassification is included.
The characterization losses and physical old/new rules remain recorded in §53;
digital characterization and remaining conversation/bulk defects belong to C.

Audit remains **290 overall**, A/B/C/D/E/F **39/40/54/10/124/23**; seller **43**,
**0/0/16/0/27/0**; back-office baseline **82**. This finishes D13, not the other
open checklist defects.

### Verification

- Before-fix failures reproduced locally on MySQL 8.4 for decimal endpoint output,
  numeric-looking choices and generated slug boundaries; additional focused
  preview and metacharacter failures reproduced before their fixes.
- Focused Pest: **393 passed, 2,586 assertions**, followed by the edit parity
  regression **1 passed, 14 assertions**. Complete local MySQL group:
  **256 passed, 6 existing driver-specific skips, 1,151 assertions**; final parity
  regression then passed on MySQL too (**1 passed, 14 assertions**).
- Focused Vitest: **2 files, 24 passed**. Production build passed. Focused browser
  specs: **12 passed**, including real seller and back-office reconciliation.
  Local permission failures in generated assets/uploads were corrected without
  test exclusions; stale local browser servers were cleaned up before the final
  passing run. The strict back-office CSP report assertion remains intact; the
  fixture clears unrelated remote product thumbnails before login.
- The first explicit full gate stopped on three ESLint style errors in the new
  reorder handler. They were corrected, without relaxing rules. The second
  explicit `python3 .claude/scripts/pre_push_check.py --full` passed setup
  readiness, Wayfinder, ESLint, Prettier, vue-tsc, Vitest (**336 files / 2,653
  tests**), Pint (**1,373 files**) and PHPStan (**899 files / zero errors**),
  then cleared config and ran four-worker Pest. Pest exited 1 while writing
  `/tmp/__laravel_test_cache_directory/test-results`: the old Docker-root-owned
  cache was not writable by the current non-root test user. It produced no final
  test totals, so this is **not a passing full gate**. No test was skipped or
  quarantined. The attempted cache ownership repair was not executed because
  automatic approval review failed (workspace credits exhausted). On resumption,
  renewed Docker socket permission was requested but not granted. The cache must
  be repaired and the explicit full gate repeated before B2's last commit.
  B2 is uncommitted and unshipped; C has not started. No bare/attested run or
  manual CI-Verified trailer was used. `git diff --check` passed.

## 55. Seller digital catalogue and remaining seller row rules, PR C (2026-10-23)

Branch `codex/seller-digital-c` starts at fetched `origin/develop`
`25f49b61c57f0964c11b61ef8d5ace552a9b3962`, containing #468 and
#469. Both C1 (digital) and C2 (conversation/bulk row rules) fit in this change;
no split was necessary. This is seller-panel work. Back-office digital
controllers, mobile API, storefront downloads and `products:sync` are unchanged.

### Characterization, before replacing the controller

`SellerDigitalProductPersistenceTest` ran against the original controller:
**7 passed, 84 failed, 134 assertions**. The passing characterization cases were:

- `digital create characterizes assigned fields and server authority`: all
  displayed digital fields persist through direct assignments: name/category,
  tags/description, prices/discount/type, gallery/thumbnail, meta texts/image and
  downloadable file id. The translation, tax and zero-quantity stock are also
  written. This is **not** the physical flow's mass-assignment field-loss defect.
  The row has seller owner, added_by=seller, digital=1, published=1, origin=null,
  but **approved=1 even when admin approval is enabled**.
- `digital update characterizes assigned fields without changing authority`:
  posted digital fields save, slug is lowercased, translation follows lang;
  owner/added_by/approval/publication stay as stored. An unchanged null image is
  explicitly cleared, as before. Non-default language keeps base name/description.
- The four cases now named `digital refuses characterized foreign upload
  overlong name missing file and unknown field` originally accepted a foreign
  upload, 201-character name (SQLite), missing file, and unknown key. Unknown
  fields are silently dropped; the create form's hidden added_by and edit's
  hidden id/added_by have never selected authority. They are removed and refused.
- `digital refuses another sellers id before validating input` originally
  redirected without writes. The same ProductPolicy now authorizes the request
  before validation, retaining the original warning/redirect, and is checked
  again by the writer under lock.

The 84 validation failures reproduced both store/update accepting bad input or
crashing instead of returning field errors. Separate approval and stock-failure
rollback regressions then failed **3/3** before implementation. Both form
mark/old-input tests failed before replacing the templates. A two-product list
render reproduced a lazy-loading 500; its endpoint now eager-loads narrow
translation/category columns. A new picker parity test caught this PR initially
refusing descendants with digital=0; the rules now accept exactly the displayed
digital-root tree, including those descendants.

### Field rules: original plain Request → validated seller contract

| Field | Before | Now (store and update unless stated) |
| --- | --- | --- |
| name | Any assigned value; create HTML required, no limit | Required string, max **200** characters; HTML limit/mark/required agree. |
| category_id | Any id; browser required | Required integer in the offered digital-root category tree. |
| unit_price, purchase_price | Unvalidated; browser required | Required numeric, nonnegative **decimal(20,2)**, max **999999999999999999.99**, at most two fractional digits; zero accepted. |
| discount / discount_type | Unvalidated; inconsistent marks | Both required; nonnegative decimal amount, type amount/percent; amount <= unit price, percent <=100. Full discounts and a free product with zero discount remain possible. |
| tags | Assumed tags[0] JSON; malformed shapes crash | Optional one-element list containing Tagify JSON, <=100 objects with only a nonblank string value, no commas, joined length <=500; input JSON <=10,000 characters. |
| photos | Arbitrary comma-separated references; edit HTML required | Optional string max2000, at most50 distinct existing **seller-owned image** ids. |
| thumbnail_img, meta_img | Arbitrary references; thumbnail HTML required on edit | Optional integer referencing an existing **seller-owned image**. No retained foreign-reference exception. |
| file | Arbitrary or missing reference despite visible star | Required integer referencing an existing **seller-owned** upload of one of the uploader's supported kinds: image/audio/video/document/archive. Unknown/null upload kinds refused; the picker continues to offer all those kinds. Upload content validation remains the existing uploader's responsibility. |
| description | Arbitrary assigned HTML | Optional string, sanitized before validation/persistence; MEDIUMTEXT has no artificial text cap. |
| meta_title, meta_description | Any assigned value | Optional strings; existing MEDIUMTEXT/LONGTEXT storage, no invented varchar cap. |
| tax_id[], tax[], tax_type[] | Unchecked parallel arrays | Shared active-tax references, distinct ids, bounded lists, matching lengths, required per-row numeric decimal amount/type; percent <=100. Existing taxes stay when no tax rows are posted, as before. |
| lang (update) | Unvalidated; controls default-language base text | Required existing language; non-default translation does not change base name/description. |
| slug (update) | Unvalidated lowercase string | Optional max255 alphanumeric/hyphen, lowercased before uniqueness validation, excluding only this product; omitted/blank preserves current slug. Create generates a bounded unique ProductSlug with a nonempty fallback. |
| ownership/status/origin and unknown keys | Unused keys silently discarded | Strict refusal, including empty values, query keys and uploaded unknown fields. Only _token/_method are exempt transport keys. |

Both Blade wrappers use shared fields, marks and one legend, field errors and
scalar-safe old input. The optional image/tag fields lose their false stars or
requirements. Tax rows have separate amount/type labels and requirements.
Client-side numeric constraints and file-selection feedback supplement server
validation. The old hidden added_by/id values are gone; edit retains lang.
Stored decimal defaults use exact ProductFormNumber formatting; old input keeps
its entered representation. The retained AIZ uploader and Blade CSP policy stay.

### Explicit transactional persistence and ownership

`SellerDigitalProductWriter` assigns an explicit allowlist, creates seller-owned
rows with digital=1, published=1 and null origin, and applies the physical
seller approval setting (pending=0 when enabled, approved=1 otherwise).
Update locks the seller-owned Product, rechecks ProductPolicy and preserves all
ownership/approval/publication/origin columns. Product's existing feed-column
guard runs at assignment/save **before** stock/tax/translation mutation. A feed
rename refusal therefore preserves all related rows; non-default translation and
store-owned fields remain editable. Stock and tax failures roll back the entire
transaction. Slugs use ProductSlug; no unguard/all-input fill or widened model
fillable is introduced. Shared related-row operations move from
PhysicalProductWriter to ProductWriter; physical computations remain separate.
The seller Tagify/gallery rules are shared with PhysicalProductRequest.

Option A is unchanged: sync owns only name/ar_name/published on feed-origin rows;
store owns all other columns and those three on its own rows. No data is
reclassified. The owner-reviewed exact-id procedure in §52 remains operational
work, not a new decision for this PR.

### Conversation and bulk row rules

`SellerCatalogueRowRulesTest` exercises actual seller endpoints. The seller
conversation request already had the 65,535-byte guard from §50: exact
multibyte boundary succeeds, one byte more fails, and a foreign conversation
still fails membership before malformed-message validation. No conversation
behavior is changed. The character-only **storefront** conversation validator
recorded in §48 is outside this seller-only task and remains a follow-up.

Six seller bulk regressions failed before repair: unit >20 characters, price
above decimal(20,2), excess fractional precision, stock >2147483647, tags >500,
and video URL >100. Seller ProductsImport now enforces these storage bounds;
admin import rules retain their previous behavior. An exact-boundary CSV exposed
the spreadsheet reader rounding a decimal string to a float before validation;
a seller-only value binder preserves CSV strings. Existing sanitization,
subscription/approval/ownership and whole-import rollback remain. Tests pair a
valid and invalid row and assert no product/stock/upload write, then accept exact
boundaries and prove another seller's product is unchanged.

The previously reported import-created unowned external upload rows and missing
POST verification-status repeat are unchanged; this task addresses row bounds,
not a new import permission or historical upload-ownership policy. No owner
choice blocks these repairs, and no automatic cleanup/backfill is performed.

### Audit and verification

The committed 20261022 audit is regenerated with `forms:audit-required
--date=20261022`: **290 →255 overall**, A/B/C/D/E/F **39/40/38/10/105/23**;
seller **43 →8**, **0/0/0/0/8/0**. Remaining seller findings are scanner E
false positives for the conditional lang/slug controls in physical and digital
shared partials; runtime language/slug rules are covered at the endpoints.
Both digital wrappers join FIXED_STOREFRONT_FORMS. The back-office baseline
stays **82**, with no new or removed baseline entry; its findings did not change.

- Initial digital characterization: **7 passed /84 failed /134 assertions**;
  approval/rollback **3/3 failed** before repair; both form render cases failed.
  Six bulk bounds failed before repair. Seller conversation byte/membership
  characterization passed. The invalid exact-boundary import fixture also
  exposed CSV float conversion; its unrelated video/noise fixture mistakes were
  corrected without weakening the intended boundary/no-write assertions.
- Broad focused seller Pest: **493 passed /1,894 assertions**. The final
  integration pass after preserving the original authorization response and
  updating the feed test's digital payload passed **307 tests /1,538 assertions**.
  The feed regression retains its name-error and unchanged product/stock checks.
- Final focused MySQL 8.4: **162 passed /625 assertions**, covering both new
  files plus ProductColumnOwnershipTest. Both new files are listed in **both**
  tests.yml and pre_push_check.py MYSQL_TESTS. Their routes render Blade or
  redirect; no new Inertia render test needs withoutVite().
- Playwright, full retained uploader/seller spec: **11 passed (1.2 minutes)**,
  including digital missing-file feedback, actual owned file upload/selection,
  create/edit, pending approval and matching base/stock prices. System Chromium
  and FFmpeg were used; /opt/pw-browsers/chromium is absent. The first launch
  lacked Playwright's FFmpeg cache path; a task-local link uses the installed
  FFmpeg. Broader attempts exposed the stale ignored public/hot marker and a
  translation key whose displayed Save product casing depends on the first
  caller. The marker was backed up and the new template/test use the existing
  canonical label. Existing browser assertions/CSP/deadlines remain unchanged.
- Production build, targeted Prettier/ESLint, node syntax check, form audit,
  ratchet, script selftests, CI planner (**23 tests**) and diff checks passed.
  Baseline entries remain unchanged.
- The first explicit --full stopped at TypeScript because provisioned packages
  were stale; npm ci restored the locked dependencies. The second passed
  Wayfinder, ESLint, Prettier, vue-tsc, **336 Vitest files /2,653 tests**, Pint
  and PHPStan (**zero errors**) but Pest found **3 failures /6 errors /3 existing
  SQLite driver skips** across 7,641 tests. Two integration fixtures/response
  differences are repaired as above. Missing WebP and stale Composer classmap
  caused the other failures; optimized autoload was regenerated, lock unchanged.
  GD was built locally with WebP. Automatic approval review refused replacing
  the shared provisioned extension because of its broader runtime impact; the
  shared extension was left untouched. A task-local php.ini/extension directory
  loads the new GD only for this task. Its multipart suite passed **36 tests /
  168 assertions**. A fresh explicit full gate runs with that isolated config.

The final full-gate and complete MySQL-group results follow below. No bare or
attested pre-push run, skipped/quarantined test or manual CI-Verified trailer is
used. No Vue source changed; whole Vitest still runs in the full gate.

The complete MySQL CI list passed **403 tests /1,749 assertions**, with **6 existing
driver-specific skips** (409 total, MySQL 8.4). It includes the existing physical
persistence and admin MySQL edit tests as well as both new files and the corrected
feed-ownership fixture.

The next isolated-config full run passed its frontend/static checks again, then
Pest reported **7,637 passed /1 failed /3 existing SQLite driver skips /42,891
assertions**. The one failure was Blade's atomic compiled-view rename: the
concurrently running MySQL group's existing physical writer cleared the root
view cache while parallel Pest was compiling a view under test_2. This was a
local verification scheduling error. No application/test assertion or timeout
was changed for it; a fresh explicit --full runs alone, after MySQL and browser
checks finish. Its final result is recorded below.

The full pre-push mode does not include the separate browser/MySQL lanes.
Composer/npm advisory audits, deploy rollback tests, a full-repository browser
run and deployment were not run locally for C. Real PR CI remains required.

**Final explicit `python3 .claude/scripts/pre_push_check.py --full`: passed**
with task-local PHPRC, after the other test groups finished. It ran setup
readiness, Wayfinder generation, whole-repo ESLint, Prettier, vue-tsc,
**336 Vitest files /2,653 passing tests**, Pint, PHPStan (**zero errors**),
config clear and parallel Pest: **7,638 passed /3 existing MySQL-only skips on
SQLite /42,897 assertions (149.521 seconds)**. No application or test code changed
after this pass; only verification documentation was finalized. The complete
MySQL and browser results above were run separately. No new test was skipped,
loosened or quarantined. The change fits **25 files**, covering C1 and C2 together.


## 56. Back-office required fields, phase 2b batch 2a: Setup and Website (2026-10-23)

Branch `codex/marks-setup-website`, from current `origin/develop`
`d74a3425f072736cf533cf743572caf62eb0b009` (PR #470, already merged).
Both requested modules fit in **29 files**; Website is included in batch 2a.
The server remains authoritative. No endpoint, validation rule, storage limit,
permission, submitted value or behavior for valid input changes. No PHP source,
MySQL test list or CI-Verified trailer is edited. No boundary test is needed
because no server limit changes.

**Rendered fixes.** Carrier billing and the shipping-zone group gain marks;
the first zone checkbox is natively required only while none is selected,
so selecting any zone satisfies the group without requiring every zone.
Paid ranges/prices remain marked and required only while rendered; free shipping
omits them. Zone countries and the general-settings timezone gain marks.
The Google Map switch follows `required_without_all` using only the coordinates
actually submitted: disabling the map omits the coordinate controls, so the switch
is then required even when a stored coordinate draft remains. Coordinates retain
their paired requirements. Currency decimal controls follow whether the format
select has a value. Both conditions have English/Arabic help. Each of the two
currency and three shipping settings forms gains its own legend.

Website custom-page slugs gain a mark only on editable slugs (new/custom pages);
policy URLs remain read-only, unnamed and unmarked. Header menu label/link rows,
footer label/link rows and each home category gain marks, activating the existing
ResourceFormPage legend. Optional images, text, slider links, coupon settings and
boolean switches stay unmarked. Removing the final home category removes its
mark and legend. These runtime row labels were unresolved controls in the audit,
so their rendered fixes do not reduce its totals.

### Audit and ratchet status

`php artisan forms:audit-required` on the untouched base reproduced **255**.
The first scratch-output attempt failed because its output directory did not yet
exist; after creating it the audit ran successfully before any UI edit.
The committed current-date report/JSON are
[required-fields-audit-20261023.md](../required-fields-audit-20261023.md) and
[required-fields-audit-20261023.json](../required-fields-audit-20261023.json).
The 20261022 snapshot remains historical. The scanner implementation is unchanged.

| Module | Forms | Before A/B/C/D/E/F | After A/B/C/D/E/F | Status |
| --- | ---: | --- | --- | --- |
| Store/Admin/Setup | 27 | 10 / 7 / 0 / 8 / 0 / 0 | 8 / 0 / 0 / 5 / 0 / 0 | Real gaps fixed; remaining findings are limitations below |
| Store/Admin/Website | 7 | 9 / 6 / 1 / 0 / 2 / 0 | 9 / 5 / 1 / 0 / 2 / 0 | Real gaps fixed; remaining findings are limitations below |
| Whole application | 239 | 39 / 40 / 38 / 10 / 105 / 23 | 37 / 32 / 38 / 7 / 105 / 23 | **255 → 242** |
| Back-office baseline | — | 82 entries | 72 entries | Only the ten absent A/B fingerprints removed |

### Every finding: disposition and audit limitations

Each row identifies one original finding. Paths are relative to
`resources/js/pages/Store/Admin/`. “Limitation” means no UI change was made to
satisfy the scanner. Mounted tests and focused endpoint tests preserve the actual
contract. The five retained D findings are conditions that are always true in
these separate forms, as permitted by the convention's normal-state rule; they
are not runtime-dependent requirements made static.

| Form | Class | Field | Disposition |
| --- | --- | --- | --- |
| Setup/Carriers/Create | A | carrier_price | Limitation: dynamic zone/range inputs post the required array |
| Setup/Carriers/Create | A | delimiter1 | Limitation: dynamic required row controls post the array |
| Setup/Carriers/Create | A | delimiter2 | Limitation: dynamic required row controls post the array |
| Setup/Carriers/Create | A | zones[] | Fix: native group requirement when no zone is checked |
| Setup/Carriers/Edit | A | carrier_price | Limitation: dynamic zone/range inputs post the required array |
| Setup/Carriers/Edit | A | delimiter1 | Limitation: dynamic required row controls post the array |
| Setup/Carriers/Edit | A | delimiter2 | Limitation: dynamic required row controls post the array |
| Setup/Carriers/Edit | A | zones[] | Fix: native group requirement when no zone is checked |
| Setup/Carriers/Index | A | id | Limitation: the Wayfinder action supplies id via mergeQuery; the scanner does not read action query parameters |
| Setup/GeneralSettings/Index | A | settings | Limitation: nested named controls post the parent array |
| Setup/Carriers/Create | B | billing_type | Fix: RequiredMark inside the paid-only label |
| Setup/Carriers/Create | B | zones[] | Fix: RequiredMark on the paid-only group legend |
| Setup/Carriers/Edit | B | billing_type | Fix: RequiredMark inside the paid-only label |
| Setup/Carriers/Edit | B | zones[] | Fix: RequiredMark on the paid-only group legend |
| Setup/GeneralSettings/Index | B | settings[timezone] | Fix: RequiredMark; existing native required retained |
| Setup/Zones/Create | B | country_id[] | Fix: RequiredMark; existing native required retained |
| Setup/Zones/Edit | B | country_id[] | Fix: RequiredMark; existing native required retained |
| Setup/Currencies/Index | D | decimal_separator | Fix: mark and required only with nonempty symbol_format |
| Setup/Currencies/Index | D | no_of_decimals | Fix: mark and required only with nonempty symbol_format |
| Setup/Currencies/Index | D | symbol_format | Limitation: the formats form never submits system_default_currency, so required_without always applies |
| Setup/Currencies/Index | D | system_default_currency | Limitation: the default form never submits symbol_format, so required_without always applies |
| Setup/GoogleMap/Index | D | google_map | Fix: mark and required only while both submitted coordinates are empty/absent |
| Setup/ShippingConfiguration/Index | D | flat_rate_shipping_cost | Limitation: its own form always submits type=flat_rate_shipping_cost; retain required and add legend |
| Setup/ShippingConfiguration/Index | D | shipping_cost_admin | Limitation: its own form always submits type=shipping_cost_admin; retain required and add legend |
| Setup/ShippingConfiguration/Index | D | shipping_type | Limitation: its own form always submits type=shipping_type; retain required and add missing mark/legend |
| Website/Appearance | A | settings | Limitation: Form transform explicitly posts the settings object |
| Website/Appearance | A | settings[show_cookies_agreement] | Limitation: transform posts true or false, both valid; required checkbox would wrongly forbid false |
| Website/Appearance | A | settings[show_subscribe_form] | Limitation: transform posts true or false, both valid |
| Website/Appearance | A | settings[show_website_popup] | Limitation: transform posts true or false, both valid |
| Website/Footer | A | settings | Limitation: Form transform explicitly posts the settings object |
| Website/Footer | A | settings[registration_tutorial_button] | Limitation: transform posts true or false, both valid |
| Website/Footer | A | settings[show_social_links] | Limitation: transform posts true or false, both valid |
| Website/Header | A | settings | Limitation: Form transform explicitly posts the settings object |
| Website/Home | A | settings | Limitation: Form transform explicitly posts the settings object |
| Website/Appearance | B | settings[show_cookies_agreement] | Limitation: a boolean value is always supplied; the checkbox is not an acceptance requirement |
| Website/Appearance | B | settings[show_subscribe_form] | Limitation: a boolean value is always supplied |
| Website/Appearance | B | settings[show_website_popup] | Limitation: a boolean value is always supplied |
| Website/Footer | B | settings[registration_tutorial_button] | Limitation: a boolean value is always supplied |
| Website/Footer | B | settings[show_social_links] | Limitation: a boolean value is always supplied |
| Website/Pages/Create | B | slug | Fix: RequiredMark only for editableSlug, agreeing with the existing control |
| Website/Pages/Edit | C | slug | Limitation: the audit binds an unsaved policy Page; custom edits require slug, policies omit it; both rendered branches tested |
| Website/Footer | E | settings[delivery_boy_app_link] | Limitation: rule and control exist only with the delivery addon; scratch rule evaluation has it disabled |
| Website/Footer | E | settings[seller_app_link] | Limitation: rule and control exist only with vendor_system_activation; scratch rule evaluation has it disabled |

**UI versus server.** Three actual static-requirement disagreements are corrected:
Google Map's switch and the two currency decimal controls. Their marks and native
attributes now follow the server condition in both directions. No server rule is
added to justify an old mark. The Website edit-slug C is a scanner model-state
limitation, so the valid custom-page requirement is preserved; no real optional
field in these modules needed a UI requirement removed. The Website switches
remain unchecked-capable because `required|boolean` permits false.

### Verification and remaining scope

Before implementation, after restoring missing Wayfinder output and locked
frontend dependencies with `npm ci`, new assertions failed in **11 files**:
**12 failed / 62 passed**. They cover billing/zone/country/timezone marks,
Google Map and currency conditions, per-form legends, editable slugs and dynamic
Website rows. The initial missing-module failures were setup errors, not claimed
as reproduced UI defects. The legend assertions wait for Vue's next flush.

Focused changed tests: **12 files / 82 passed**. The complete Setup/Website page
and affected field-component run: **36 files / 194 passed**. Focused Pest (audit,
ratchet and eleven module controller suites): **467 passed / 3,080 assertions**.
The first post-fix ratchet correctly failed naming the ten stale baseline entries;
it passes after their removal. `npm run types:check` passed. Changed-file ESLint
found import ordering/blank-line errors in new tests; autofix and Prettier resolved
them, and both checks passed. No PHP file changes, so dirty Pint is not applicable.

The first full pre-push run passed all frontend checks (**336 files / 2,664
Vitest tests**), Pint and PHPStan (**0 errors**), then found local runtime defects:
GD lacked WebP in the already-started workers and the Composer class map still
named two deleted legacy controllers. Its Pest result was **7,631 passed / 1 failed /
6 errors / 3 existing MySQL-only skips / 42,853 assertions**. The missing WebP
failure was separately reproduced by the existing upload suite (**50 passed /
1 failed**). The task-built GD extension enables WebP (native PHP **8.5.11**),
and `composer dump-autoload --no-scripts` refreshes only vendor metadata.
After repair, the upload suite passed **51 tests / 235 assertions**; the four
affected upload/multipart/digital-route/trip-map suites passed **170 tests /
1,031 assertions**. No dependency, lockfile or test assertion changed to repair
these failures. A final review also removed unnecessary ARIA from the native
zone fieldset; its checkbox's native conditional required remains.

**Final explicit `python3 .claude/scripts/pre_push_check.py --full`: passed.**
It ran setup readiness, Wayfinder generation, whole-repository ESLint, Prettier,
vue-tsc, **336 Vitest files / 2,664 passing tests**, Pint, PHPStan (**0 errors**),
config clear and parallel Pest: **7,638 passed / 3 existing MySQL-only skips on
SQLite / 42,897 assertions (190.085 seconds)**. Application and test code did not
change after this pass; only verification documentation was finalized.
`git diff --check` passed, and the finding table was checked against all 43
original scoped findings with no omission or duplicate.

Not run locally: Playwright, the MySQL job, composer/npm advisory audits,
deploy rollback tests or a new production asset build. The supplied Vite manifest
was available for the existing Inertia tests. `/opt/pw-browsers/chromium` is absent;
system Chromium is available, but no Playwright run is claimed. These separate CI
lanes remain required when planned. No new test was skipped, loosened or
quarantined, and no CI-Verified trailer was written or edited.

Setup and Website leave D16's Left list. Trips, Sales, Customers, Languages,
Support and Blog remain the later batch, including the owner's existing decision
to drop customer UI-only requirements and separately prove any column-limit fix.
Seller panel, storefront and every other module remain outside this change.
There are **no new owner decisions** and no pending limit alignment in this batch.


## 57. Back-office required fields, phase 2b batch 2b: Trips, Sales and Languages (2026-10-24)

Branch `codex/marks-trips-sales-languages` starts from current `origin/develop`
`21a522f9d201079b9c4358fbc0a6d9459126a9d8`, containing PR #471. All three modules
fit in **26 changed files**. Customers, Support, Blog, Notifications,
Representatives, other modules, seller panel and storefront are unchanged.
The server remains authoritative: no endpoint, server rule, storage limit,
permission, submitted value or behavior for valid input changes. No PHP source
or test changes, MySQL list changes or CI-Verified trailer edits are needed.
No server limit is aligned or changed, so a new endpoint boundary test is not
applicable; existing endpoint validation suites are run below.

### Rendered changes and current status

Driver countries and trip trucks gain marks. The checkpoint group and every
editable order/customer row gain marks too; native row requirements and the
empty-array guard remain. Stored checkpoint references stay hidden and optional
driver/notes controls stay unmarked. Trip status dialogs gain a mark and their
own legend. Inline checkpoint status controls gain associated marked labels
and a legend; notes remain optional. These script-submitted controls are outside
the scanner's form inventory.

Sales product units gain a mark on each row. Bulk delete marks its selection
group and natively requires any one order, without requiring every checkbox;
the requirement clears on selection, returns on clearing, and selection resets
after success. Delivery/payment actions gain marks and separate legends;
item-price forms gain legends. Tracking and manager notes remain optional.

Language RTL/status and JSON import forms gain separate legends. Their boolean
selects still accept 0 and 1. The disabled default-language status is supplied
by the existing hidden control/transform and is display-only: remove its mark
and native requirement. Editable booleans remain required. Translation values
remain present but nullable, so they have neither marks nor native requirements;
there is no required-field legend on those text-only translation forms.

Fresh generated report/JSON:
[required-fields-audit-20261024.md](../required-fields-audit-20261024.md) and
[required-fields-audit-20261024.json](../required-fields-audit-20261024.json).
The 20261023 and 20261022 snapshots remain historical. Scanner implementation
and baseline reasons are unchanged. Only six absent fingerprints are removed;
no entry is added or raised.

| Module | Forms | Before A/B/C/D/E/F | After A/B/C/D/E/F | Total before → after | Status |
| --- | ---: | --- | --- | --- | --- |
| Store/Admin/Trips | 11 | 3 / 4 / 0 / 0 / 1 / 1 | 3 / 0 / 0 / 0 / 1 / 1 | 9 → 5 | Real gaps fixed; remaining limitations/UI-only fields below |
| Store/Admin/Sales | 9 | 6 / 1 / 1 / 0 / 6 / 2 | 5 / 0 / 1 / 0 / 6 / 2 | 16 → 14 | Real gaps fixed; route/array limitations retained |
| Store/Admin/Languages | 7 | 3 / 0 / 0 / 0 / 2 / 0 | 3 / 0 / 0 / 0 / 2 / 0 | 5 → 5 | Legends fixed; array/transform limitations retained |
| Whole application | 239 | 37 / 32 / 38 / 7 / 105 / 23 | 36 / 27 / 38 / 7 / 105 / 23 | **242 → 236** | P3 116 → 110; P1/P2 unchanged |
| Back-office baseline | — | 72 entries | 66 entries | **72 → 66** | Only the six fixed A/B fingerprints removed |

### Every original finding and its disposition

One row per original scoped finding (30 total). Retained scanner findings are
not UI defects and are not worked around by adding bogus hidden or required
controls. Manual F checks include the controller, route and real FormRequest.

| Form and endpoint | Class | Field | Disposition |
| --- | --- | --- | --- |
| Languages/Index → LanguageController@updateRtlStatus | A | `status` | Limitation: Form transform renames rtl-<id> to status. The required boolean select accepts both 0 and 1; add its missing form legend. |
| Languages/Translations → LanguageController@storeAppTranslations | A | `translations` | Limitation: dynamic nested key/value names supply the array; keys are hidden, values are present&#124;nullable. Values must stay unrequired and unmarked. |
| Languages/Translations → LanguageController@storeTranslations | A | `translations` | Limitation: dynamic nested key/value names supply the array; keys are hidden, values are present&#124;nullable. Values must stay unrequired and unmarked. |
| Sales/Orders/Create → OrderEntryController@shippingStore | A | `products` | Limitation: dynamic nested product row names supply the array. Required product, quantity, price and unit controls exist; unit marks added. |
| Sales/Orders/Create → OrderEntryController@shippingStore | A | `rep_id` | Limitation: scratch route binding evaluates the representative route; shippingStore is nullable and correctly omits this control. Representative create renders it required and marked. |
| Sales/Orders/Create → OrderEntryController@store | A | `products` | Limitation: dynamic nested product row names supply the array. Required product, quantity, price and unit controls exist; unit marks added. |
| Sales/Orders/Edit → OrderEntryController@update | A | `products` | Limitation: dynamic nested product row names supply the array. Required product, quantity, price and unit controls exist; unit marks added. |
| Sales/Orders/Index → OrderController@bulk_order_delete | A | `id[]` | Fix: mark the selection group; only the first checkbox is required while none is selected. Selecting any order satisfies the group. |
| Sales/Orders/Show → OrderController@edit_item_price | A | `order_id` | Limitation: rules() uses the route name; item-price route requires id and admin_price, not order_id. Preserve the real route contract. |
| Trips/Trips/Create → TripController@store | A | `checkpoints_array` | Limitation: dynamic nested checkpoint names supply the array; empty-row native validation already exists. Group and editable row marks added; stored references stay hidden. |
| Trips/Trips/Edit → TripController@update | A | `checkpoints_array` | Limitation: dynamic nested checkpoint names supply the array; empty-row native validation already exists. Group and editable row marks added; stored references stay hidden. |
| Trips/Trips/Index → TripController@bulkDestroy | A | `list` | Limitation: bulkDestroy(listQuery) supplies the required list in the action query. |
| Sales/Orders/Index → OrderController@bulk_order_delete | B | `id[]` | Fix: mark the selection group; only the first checkbox is required while none is selected. Selecting any order satisfies the group. |
| Trips/Drivers/Create → DriverController@store | B | `country` | Fix: RequiredMark on the existing required country select. |
| Trips/Drivers/Edit → DriverController@update | B | `country` | Fix: RequiredMark on the existing required country select. |
| Trips/Trips/Create → TripController@store | B | `truck_id` | Fix: RequiredMark on the existing required truck select. |
| Trips/Trips/Edit → TripController@update | B | `truck_id` | Fix: RequiredMark on the existing required truck select. |
| Sales/Orders/Show → OrderController@edit_item_price | C | `admin_price` | Limitation: route-dependent rules. order.item.edit_price requires numeric admin_price; retain its mark and native required, add the missing legend. |
| Languages/Translations → LanguageController@storeAppTranslations | E | `search` | UI-only: filters the key list through the GET endpoint; save writes only translations. Retain unmarked. |
| Languages/Translations → LanguageController@storeTranslations | E | `search` | UI-only: filters the key list through the GET endpoint; save writes only translations. Retain unmarked. |
| Sales/Orders/Create → OrderEntryController@shippingStore | E | `customer_id-search` | UI-only: async option search, not a persisted field. The named selected id is validated and saved; retain search unmarked. |
| Sales/Orders/Create → OrderEntryController@store | E | `customer_id-search` | UI-only: async option search, not a persisted field. The named selected id is validated and saved; retain search unmarked. |
| Sales/Orders/Create → OrderEntryController@store | E | `rep_id-search` | UI-only: async option search, not a persisted field. The named selected id is validated and saved; retain search unmarked. |
| Sales/Orders/Edit → OrderEntryController@update | E | `customer_id-search` | UI-only: async option search, not a persisted field. The named selected id is validated and saved; retain search unmarked. |
| Sales/Orders/Show → OrderController@edit_item_price | E | `id` | Limitation: order.item.edit_price explicitly requires this hidden line id; scanner evaluated another route branch. |
| Sales/Orders/Show → OrderController@update_manager_notes | E | `notes` | Limitation: orders.update_manager_notes validates nullable notes (max:2000), and controller saves manager_notes. Keep unmarked. |
| Trips/Drivers/Edit → DriverController@update | E | `email` | UI-only: edit email is readonly account information; UpdateDriverRequest and controller intentionally do not update it. Retain unmarked. |
| Sales/Orders/Index → (unresolved) | F | `-` | Manual verification: listAction selects OrderController@all_orders list routes (FilterOrdersRequest). Search, dates, statuses and per_page are optional; retain unmarked. |
| Sales/Orders/Show → (unresolved) | F | `-` | Manual verification: action.action selects delivery/payment status routes. Both require order_id plus status; add status marks and one legend to each form. |
| Trips/Trips/Index → (unresolved) | F | `-` | Manual verification: listIndex selects TripController@index or today (FilterTripsRequest). All filters optional, including the status checkbox list; no marks or legend needed. |

### UI/server disagreements, ignored fields and owner decisions

The Sales `admin_price` C is a route-binding limitation, not an optional field:
the real `order.item.edit_price` route requires it. The shipping create `rep_id`
A likewise comes from evaluating the other create route; the shipping route is
nullable and continues to omit the representative input. Notes and line-id E
findings are also resolved by reading the actual named route, not by changing
server rules. Native default-language status requirements are dropped from the
disabled display control because its value is already supplied automatically.
There are no other real UI-only presence requirements to remove in this scope.

No silently ignored editable business field was found. Driver edit email is
readonly information, option searches are UI state, and translation search is
GET filter state. Manager notes, line price, selected ids, checkpoints, translation
values and boolean status are validated and persisted by their actual endpoints.
No field the user expects to save was removed. Existing registration-field and
Customer decisions remain open and outside this batch; no new owner decision.
D16 removes Trips, Sales and Languages from Left; Customers, Support and Blog remain.

### Verification

Before any UI edit, `php artisan forms:audit-required --output=work/required-marks-before`
reproduced **242**, including all 30 scoped findings. New mounted assertions
then reproduced **11 failed / 54 passed** across eight files (seven failing).
Initial test-authoring syntax errors were corrected before this reproduction;
they are not counted as UI failures. After implementation, the focused run
passed **8 files / 65 tests**. The complete scoped page/component run passed
**21 files / 146 tests**, including all four changed shared create/edit forms,
optional values, group selection/clearing, dynamic rows and per-form legends.

The first type check found missing locked frontend packages and overly broad
DOM-wrapper typings in new assertions. `npm ci` restored the lockfile dependency
set; no dependency or lockfile changed. Assertions were corrected and types pass.
The first focused Pest run had **388 passed / 1 error / 2,772 assertions**: a stale
Composer class map referenced the deleted root TripController. Vendor-only
`composer dump-autoload --no-scripts` repairs it; no application/test workaround.
The first post-fix ratchet correctly failed naming exactly the six stale entries.

Final focused endpoint/audit/ratchet suites pass **389 tests / 2,774 assertions**.
Changed-file ESLint and Prettier checks pass. `git diff --check` passes.
No PHP files changed, so dirty Pint and new MySQL test-list entries are not
applicable; the full gate still runs repository-wide PHP checks.

The first full gate passed whole-repository ESLint, Prettier, vue-tsc and
**336 Vitest files / 2,681 tests**, then stopped at Pint because the command
sandbox blocked its local worker socket (`127.0.0.1:0`). The rerun uses network
enabled execution so the existing workers can listen; no test or checker changed.
The existing upload suite separately reproduced the bundled GD's missing WebP
support (**50 passed / 1 failed / 216 assertions**). A WebP-enabled GD was built
from the existing PHP 8.5 image with the platform proxy/CA, then installed only
in the workspace runtime. The upload suite now passes **51 / 235 assertions**.
No image fixture, rule, lockfile or assertion changed to repair the environment.

**Final explicit `python3 .claude/scripts/pre_push_check.py --full`: passed.**
It ran setup readiness, Wayfinder generation, whole-repository ESLint, Prettier,
vue-tsc, **336 Vitest files / 2,681 passing tests**, Pint, PHPStan (**0 errors**),
config clear, and parallel Pest: **7,650 passed / 3 existing MySQL-only skips on
SQLite / 42,925 assertions (84.211 seconds)**. `git diff --check` passes. All 30
original findings appear exactly once in the table, and the audit/baseline lose
exactly the same six fingerprints with none added. Only documentation was
finalized before the initial push; the browser-spec follow-up below received
its own verification.

Not run locally before the browser follow-up: the full application Playwright
suite, the MySQL job,
composer/npm advisory audits or deploy rollback tests. Focused browser verification
and a new production asset build are recorded in the follow-up below. These separate CI lanes remain required when planned.
The supplied Vite manifest was available for existing Inertia tests; no new
Inertia-rendering Pest test was added. No new test is skipped, loosened or
quarantined, and no CI-Verified trailer is written or edited.
There are no new owner decisions and no pending limit alignment in this batch.


### Browser follow-up from PR #484 CI

The first CI run passed all planned non-browser checks and **143 Playwright tests**,
but the trip create/edit flow timed out at the exact bare `Customer` label. Adding
the checkpoint RequiredMark changed its accessible name to `Customer required`;
the browser spec had not been updated. The same timeout was reproduced locally
against freshly built production assets before changing the test.

The spec now locates the exact `combobox` named `Customer required` and additionally
asserts the native `required` attribute. Existing empty-checkpoint validation,
create/edit persistence, status/notes and confirmation assertions are retained;
no timeout, retry, skip or application behavior changed. A sweep of the browser
specs found no other affected exact-label locator.

The focused trip browser spec passes **2 tests (9.1 seconds)** with enforced CSP,
local HTTPS, system Chromium and video recording. A fresh production build passes;
a stale ignored `public/hot` file was backed up outside the repository so the
browser uses that build. This adds one browser-spec file to the PR, for **26 files**.
**Follow-up explicit full pre-push gate: passed** (336 Vitest files / 2,681 tests;
Pint; PHPStan zero errors; Pest 7,650 passed / 3 existing MySQL-only skips /
42,925 assertions). Changed-spec ESLint/Prettier and `git diff --check` pass.
No CI-Verified trailer is written; GitHub Actions reruns against the new head.

## 58. Back-office required fields, phase 2b batch 2c: Customers, Support, Blog and small modules (2026-10-24)

Branch `codex/marks-customers-support-blog` starts from current `origin/develop`
`b070b78a6a9b5dedb50b9155c1d94a19ea54b2f3` (includes #484/#485 and #487).
The final batch fits in **30 files**. No server validation rule, limit, route,
permission or seller-panel/storefront file changes. No CI-Verified trailer is
written or edited. The scanner is unchanged. The source of truth remains the
server on the actual named route, including route-dependent request rules.

### Customer route correction: the seven C findings are limitations

The task and older guide sections call customer create's seven C findings UI-only
requirements. That premise does not match current code: `StoreCustomerRequest::rules()`
changes company name, country, tax number, bank account number, company address,
shipping address and trade license from nullable to **required** when
`routeIs('customers.store')`. The real create action uses precisely that named
route. The audit evaluates the request without that route name, reading the
nullable fallback from `ValidatesStoreCustomer`. Seven real endpoint tests now
omit each field and verify its own validation error and no account written.
Therefore no customer-create requirement is dropped and no server rule is added.
The missing country mark is added only on customer create; edit keeps all seven
unrequired/unmarked. Representative create hides these fields and keeps only
its required identity/password controls. This supersedes §44's classification
and the assumptions about dropping these seven requirements in §§49, 56 and 57.

The optional-edit persistence test exposed a real existing 500: a cleared company
name validates as null, but `users.company_name` is NOT NULL with default `''`.
The customer update writer now stores `''` for an explicitly submitted null
company name. An omitted company name retains its stored value. Other nullable
profile values still save null; an absent license stays absent. The regression
was reproduced before the three-line writer change. Validation and limits are
unchanged; existing valid nonempty input behaves as before. Tests prove the
allowed clear, its stored values, and the omitted-field behavior. This is the
only backend behavior repair in this batch.

### Audit and ratchet

The untouched-base audit reproduced **236** findings over 158 Vue and 81 Blade
forms. Regenerated current-date Markdown/JSON:
[required-fields-audit-20261024.md](../required-fields-audit-20261024.md) and
[required-fields-audit-20261024.json](../required-fields-audit-20261024.json).
There were **21 original scoped findings**, all listed below. Thirteen B findings
are fixed. Only eleven absent Store-controller fingerprints leave the baseline;
the two WMS fixes were never in it. No entry is added, raised or reworded.

| Module | Forms | Before A/B/C/D/E/F | After A/B/C/D/E/F | Total before → after |
| --- | ---: | --- | --- | --- |
| Store/Admin/Customers | 13 | 0 / 4 / 7 / 0 / 0 / 0 | 0 / 0 / 7 / 0 / 0 / 0 | 11 → 7 |
| Store/Admin/Support | 2 | 0 / 2 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 2 → 0 |
| Store/Admin/Blog | 7 | 0 / 2 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 2 → 0 |
| Store/Admin/Representatives | 3 | 1 / 0 / 0 / 0 / 0 / 0 | 1 / 0 / 0 / 0 / 0 / 0 | 1 → 1 |
| Store/Admin/Notifications | 1 | 0 / 1 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 1 → 0 |
| Admin/Settings | 1 | 0 / 1 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 1 → 0 |
| Admin/Products | 3 | 0 / 1 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 1 → 0 |
| components | 3 | 0 / 2 / 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 / 0 / 0 | 2 → 0 |
| Whole application | 239 | 36 / 27 / 38 / 7 / 105 / 23 | 36 / 14 / 38 / 7 / 105 / 23 | **236 → 223** |
| Back-office baseline | — | 66 entries | 55 entries | **66 → 55** |

### Every original finding and disposition

Store page paths below are relative to `resources/js/pages/Store/Admin/`.
Each row is one original finding, including both create/edit consumers.

| Form → endpoint | Class | Field | Disposition |
| --- | --- | --- | --- |
| Customers/Customers/Create → CustomerController@store | C | bank_account_number | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Create → CustomerController@store | C | company_address | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Create → CustomerController@store | C | company_name | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Create → CustomerController@store | C | country | Limitation: actual customers.store route requires it; preserve required and add conditional mark. |
| Customers/Customers/Create → CustomerController@store | C | shipping_address | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Create → CustomerController@store | C | tax_number | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Create → CustomerController@store | C | trade_license | Limitation: actual customers.store route requires it; preserve required/mark. |
| Customers/Customers/Edit → AddressController@store | B | country_id | Fix: mark existing required country; address form gets its own legend. |
| Customers/Customers/Edit → AddressController@update | B | country_id | Fix: same shared field and dialog, tested with an existing address too. |
| Customers/Customers/Edit → CustomerController@changeActivityStatus | B | new_status | Fix: mark and separate form legend; notes form stays optional without a legend. |
| Customers/Customers/Index → RepresentativePackageController@assign | B | package_id | Fix: mark and package-dialog form legend. |
| Support/Conversations/Show → ConversationController@reply | B | message | Fix: shared reply mark and form-local legend. |
| Support/ProductQueries/Show → ProductQueryController@reply | B | reply | Fix: same component, different named field, both mounted/tested. |
| Blog/Posts/Create → BlogController@store | B | category_id | Fix: shared category mark; existing native required and automatic legend retained. |
| Blog/Posts/Edit → BlogController@update | B | category_id | Fix: same component; optional metadata/editor/uploads stay unmarked. |
| Representatives/Visits/Show → VisitController@update_note | A | visit_id | Limitation: route-bound visit is merged to visit_id by prepareForValidation; note is nullable. No fake control or requirement added. |
| Notifications/Customers/Create → CustomerNotificationController@store | B | user_id | Fix: required customer select gains mark; automatic ResourceFormPage legend retained. |
| Admin/Settings/Edit → SettingController@update | B | allowed_open_levels[] | Fix: group legend mark; native empty-group condition still allows any selected level. |
| Admin/Products/Index → ProductController@storeBarcode | B | barcode | Fix: mark and barcode-form legend; add the missing box-count form legend too. Optional minimum-pallets stays unmarked without a legend. |
| components/StoreSellerPaymentDialog → SellerPaymentController@store | B | payment_option | Fix: mark on rendered required method selector and form-local legend. |
| components/StoreTicketReplyForm → SupportTicketController@reply | B | status | Fix: mark and form-local legend; reply stays required, attachments optional. |

Shared consumers checked: both blog pages use `ValidatesStoreBlog`; both support
reply pages use `ValidatesSupportReplies`; ticket detail uses
`ReplySupportTicketRequest`; both seller-list/withdrawal dialog consumers post to
`StoreSellerPaymentRequest`; Settings/Edit uses `UpdateSettingRequest` (the other
consumer is an inert Help preview). Customer create/edit and both address actions
were checked against their real request/route branches. No scoped E/F findings
exist, and no editable field is removed or silently ignored.

**Possible business-rule gaps:** no UI-only requirement was dropped, so none is
newly raised by that safeguard. Create's business fields really are required.
Existing documented customer string/column-limit concerns (§48) are not aligned
or reclassified here; no limit is changed and no new limit-boundary claim is made.

**Separate existing owner decision:** §35's negative/zero seller balance form
omits payment_option and Clear due still fails server validation. The method is
not an editable value silently discarded; it is a missing control in an existing
money workflow. Its established tests/behavior are retained; choosing the payment
method/credit policy is separate from adding a mark to the working payout form.
The unstored storefront registration fields remain an owner decision. No new
silently ignored editable-field defect was found in the requested modules.

### Verification

The new mounted assertions reproduced 14 UI failures after missing locked frontend
packages and generated Wayfinder output were restored. One additional seller
assertion was initially outside its fixture setup; after correcting that test,
it separately failed on the missing payment-method mark (1 failed / 7 passed).
The first complete reproduction was 15 failed / 159 passed, including that
fixture-setup error; it is not counted as a UI defect. A later missing box-count
legend was separately reproduced (1 failed / 110 passed), then fixed.
Focused Vue checks pass **10 files / 174 tests**; after the last box-count legend
addition the Products suite passes **111 tests**. A new address test fixture first
failed vue-tsc for an incomplete address shape; the full fixture now types and
passes (4 address tests). No production type or dependency changed.

Focused endpoint/audit/ratchet Pest suites pass **310 tests / 2,049 assertions**.
Customer endpoints pass **141 tests / 634 assertions**, including seven actual
route-required create refusals, nullable edit persistence and omitted company
retention. The first nullable-edit test reproduced the company-name SQL 500
(139 passed / 1 failed) before the writer repair. Changed Inertia-rendering
customer tests call withoutVite(). A final ratchet rerun passes **4 / 7 assertions**.
Dirty Pint, changed-file ESLint/Prettier, npm run types:check and git diff --check
pass. No server limit changes, so limit/limit+1 tests are not applicable. No new
MySQL-specific test file is introduced, so neither MySQL list needs an entry.

A fresh production npm run build passes. AGENTS.md's portable Playwright helper
reuses system Chromium and FFmpeg, and exports PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH.
A stale ignored public/hot marker was backed up under work/ so the browser uses
that build. Browser runs use the existing HTTPS harness and enforced CSP.
Initial browser verification was **11 passed / 3 failed**, all on uploaded images:
the provisioned GD lacked WebP support. The existing upload suite reproduced it
(**50 passed / 1 failed / 216 assertions**). GD alone was rebuilt from the existing
PHP 8.5 image with WebP support using the platform proxy/CA; no dependency,
lockfile, fixture, upload rule or repository runtime configuration changed.
The upload suite then passes **51 tests / 235 assertions**. A first Docker build
failed to read the CA as apt's unprivileged user; a read-only 0444 secret mount
fixed that without disabling TLS verification.

The six affected browser specs (customers, support, blog editor, newsletter/ticket,
seller payments and notifications) next had **13 passed / 1 failed**: an exact
Arabic ticket-status label assertion expected the old unmarked text. It now
expects the exact marked label and additionally checks the marked accessible
combobox name and native required. Its whole newsletter/ticket spec rerun passes
**4 tests (43.9 seconds)**. Thus every test in the six specs passes across the
completed run and focused rerun; no assertion, retry, timeout or test was weakened.
The support reply locator also uses the exact role/name Type your reply required.
A label/key sweep found no other affected browser locator. There is no existing
browser spec for the WMS box-count/barcode/settings controls or representative
package assignment; those controls are covered by mounted tests.

The first explicit full gate stopped at the new address-fixture type error.
A later run was superseded while finalizing the box-count legend and rerun with
bounded Vitest concurrency. The next complete frontend pass had **338 files /
2,701 Vitest tests**, Pint passing and PHPStan **0 errors**, then Pest reported
**7,686 passed / 2 failed / 2 errors / 3 existing SQLite skips / 43,098 assertions**.
Two errors came from vendor's stale class map for removed Trip/DigitalProduct
controllers; composer dump-autoload --no-scripts repairs vendor metadata only.
Two Inertia render failures came from font CSS while the final asset build
replaced the manifest during Pest; that avoidable overlap was stopped, and the
build completed before further testing. The four affected suites then pass
**235 tests / 1,740 assertions** without application/test changes. The full gate
is repeated with stable assets and refreshed autoload metadata.

**Final explicit python3 .claude/scripts/pre_push_check.py --full: passed.**
It runs Wayfinder generation, whole-repository ESLint/Prettier, vue-tsc,
**338 Vitest files / 2,701 tests**, Pint, PHPStan (**0 errors**), config clear and
parallel Pest: **7,690 passed / 3 existing MySQL-only skips on SQLite /
43,136 assertions (182.835 seconds)**. Vitest worker concurrency was bounded
(the final run used VITEST_MAX_WORKERS=4); no checker, assertion or test is
changed to achieve that result. Only verification documentation is finalized
after this pass. git diff --check passes. All 21 original findings have exactly
one disposition row, and the baseline is a strict subset of the original.

Not run locally: MySQL tests, advisory audits, deploy rollback tests or the whole
application Playwright suite. Those separate CI lanes remain required when
planned. The six affected browser specs were run as recorded above. No new
skip, loosened assertion, quarantine or hand-edited CI-Verified trailer is used.
No new test file requires a MySQL-list addition. The final production build also
passes after the box-count legend addition, before the stable full gate.
The unstored registration fields and §35's existing Clear due method/policy issue
remain separate owner decisions; no newly dropped UI requirement or silently
ignored editable field remains in this batch.

## 59. Staff and role editors can no longer escalate (2026-10-24)

Closes role-grants audit finding G-3 and checklist risk R1. Before, `edit_staff_role`
could tick any store permission onto any non-Super-Admin role (the editor's own
included) and `edit_staff` could give any role to any account, the editor's own
included, and set that account's email and password. Either one was equivalent
to every permission but Super Admin, and an account takeover of anything
below Super Admin.

**Rule** (`App\Support\StaffPrivileges`): no one grants a permission they do not
hold, and no one manages an account that holds more than they do. A staff member
holds the permissions given to them directly and through their roles. The Super
Admin holds everything and is never limited. The money and impersonation gates
(R2, R3) therefore stay with whoever holds them: a role editor cannot hand them
on.

| Action | Before | Now |
| --- | --- | --- |
| Role form (`roles.create`, `roles.edit`) | every permission offered | only permissions the editor holds are offered |
| Create role (`roles.store`) | any permissions | refused (error on `permissions`, nothing created) if any is not held |
| Update role (`roles.update`) | any permissions synced | the editor adds or removes only permissions they hold; a permission the role has and they lack stays untouched; ticking one the role lacks and they lack is refused |
| Staff form (`staffs.create`, `staffs.edit`) | every role but Super Admin | only roles that grant nothing beyond the editor's permissions |
| Create or update staff (`staffs.store`, `staffs.update`) | any role but Super Admin | `role_id` must be one of those roles (`messages.staff_role_not_assignable`); on an account's own edit it must be unchanged, so nobody changes their own role |
| Open, update or delete an account (`staffs.edit`, `.update`, `.destroy`) | any non-Super-Admin account | 403 if the account holds anything the editor does not; peers and lower accounts, and the editor's own, are allowed |

No route, permission name or Vue file changed. The role form simply lists fewer
checkboxes for a non-Super-Admin editor, and the staff form fewer roles. The
staff list still shows an edit link on an account the editor cannot open; it
answers 403. Existing data is untouched. The launch plan (Super Admin only) is
unaffected: the Super Admin sees and can do everything as before.

Tests: `tests/Feature/Store/Admin/StaffPrivilegeEscalationTest.php` (17 tests;
9 fail without the change, the other 8 pin the allowed paths, including that the
Super Admin is unrestricted). Six cases in `RoleControllerTest` that used a
one-permission staff account to exercise the full permission list now use the
Super Admin, since a limited editor sees only what they hold. R1 in the go-live
checklist is closed in code.

Not covered, still owner decisions: who gets `edit_staff_role` or `edit_staff` at
all; a role editor can still remove permissions they hold from a role they
belong to (a lockout, not an escalation).


## 60. Seller payout defects D1, D2, D3 and D5 (2026-10-24)

Branch `codex/seller-payout-defects` starts at current `origin/develop`
`7cbfe0374bf81a6bfb63f102f93944473efae630` (PR #490, including the staff
privilege fix and Chromium setup helper). Scope is checklist 11.3 D1/D2/D3/D5.
D4's empty resource actions, D6's seller dashboard, routes, permission names,
gates, commission calculations, wallets, notifications and history screens
are unchanged. Checklist 4.10 still requires the owner's finance-gate sign-off.

### Contract and money directions

`StoreSellerPaymentRequest` already requires `payment_option` (string, max 100),
`amount` (numeric, greater than zero), and the shop reference. Its existing
`txn_code` rule remains nullable/string/max 191 and the writer already stores it.

- **D2:** the Clear due form now offers a required, marked Payment Method
  select, with the form legend. Its `seller_paid_to_admin` choice uses the
  existing non-cash/bank credit branch and records `Seller paid to admin`.
  A negative balance can be cleared; zero balance cannot accept a positive
  amount under the unchanged greater-than-zero rule and the new zero limit.
- **D3:** withdrawal forms show TRX ID for cash and bank, with maxlength 191,
  no required attribute and no required mark. Balance payments retain their
  existing bank-only transaction-code control. The schema actually created
  `payments.txn_code` as VARCHAR(100), despite the existing 191-character
  request contract. The owner explicitly authorized a narrow widening
  migration to nullable VARCHAR(191). It preserves references/nulls, leaves
  already-wider columns alone and never narrows on down(). No withdrawal
  schema, new columns or partial-payment state is introduced.
- **D5:** under the existing shop row lock, the limit for cash/bank is
  `max(admin_to_pay, 0)`; for every other method it is
  `max(-admin_to_pay, 0)`. The former debits, the latter credits and records
  `Seller paid to admin`. Thus a payment moves only the existing due toward
  zero, never past it or farther away. An arbitrary nonempty method remains
  a credit method, not a new cash/bank alias. The server refuses an excess
  with `messages.seller_payout_balance_limit` in English and Arabic:
  “The amount cannot exceed the available balance for this payment method
  (:amount).” Native min/max constraints mirror the limits.
- **D1, owner-approved conservative decision:** a cash/bank withdrawal payout
  must equal the entire requested amount, as well as fit the positive balance.
  Both lower and higher amounts receive `messages.seller_payout_full_request`
  in English/Arabic: “Pay the full requested amount (:amount), or ask the
  seller to submit a new request. Partial payments are not supported.”
  The dialog defaults to the full request and sets its minimum to that amount;
  its maximum is the lesser of the request and balance. An underfunded request
  therefore cannot submit a partial amount. The server remains authoritative.

Exact decimal comparisons use the installed Brick Math package, avoiding float
rounding at the request/limit boundary. The same existing transaction locks the
shop, then the pending withdrawal. Checks precede every status/balance/payment
write; a validation exception rolls the transaction back and the existing finally
block clears the temporary session keys. A paid/nonpending request is still 404,
even when its first payment exhausted the balance. Accepted cash/bank payouts
close status 0/null to 1, set viewed to 1, debit the shop and insert one payment.
Non-cash/bank credits never close a request, even if a request reference is sent.
The seller account ID, amount, normalized method, optional code and null details
stay in the payment row. The request amount and seller message are preserved.

True partial withdrawals remain unsupported. Supporting them would require an
owner-approved durable allocation of payments to requests, remaining/paid amount
tracking and status rules, plus concurrency/idempotency and history tests. That
future accounting/schema work is outside this conservative repair.

### Characterization and proof

Before fixes, real endpoint tests on **both SQLite and MySQL** passed four
characterizations and failed two desired refusal tests (422 expected, 302
returned). The dialog regression tests failed for D1, D2 and D3 before its edit.

| Case | Before | After |
| --- | --- | --- |
| D1: balance 100, request 80, pay 12.50 | balance 87.50, request paid, one payment | 422; balance 100, request pending/unviewed, no payment |
| D2: balance -40, clear 40 | missing method: 422, no writes | selected credit method: balance 0, request unchanged, exactly one `Seller paid to admin` payment |
| D3: full request with 191-character code | four MySQL cases returned 500 on VARCHAR(100); nullable-code cases passed | after approved widening: cash/bank and status 0/null retain the 191-character code; 192 characters refused before writes |
| D5: balance 100, pay 101 | balance -1, one payment | 422; balance 100, no payment |
| D5: balance 100, request 40, pay 41 | balance 59, request paid, one payment | 422; balance 100, request pending, no payment |

`SellerPayoutDefectsTest` covers full-limit payouts, limit+1, both money
directions, wrong-direction methods, zero/negative amounts, missing/invalid
methods, nullable and 191/192-character codes, ownership, repeated submissions,
wallet/noise-row preservation and English/Arabic messages. Refusals compare
snapshots of all shop/request/payment rows and wallet balances. Existing payout
characterizations are updated for the deliberate limits while retaining valid
method/direction, rollback, permission and no-notification coverage. Existing
Inertia-rendering tests in the changed controller test call `withoutVite()`.

Both new Pest files are listed in `.github/workflows/tests.yml` and
`MYSQL_TESTS` in `.claude/scripts/pre_push_check.py`. Migration tests cover old
100-character upgrades, existing rows/nulls, idempotence and already-wider
columns. Vitest checks the submitted Clear due method, required marks/legend,
zero/full-request native bounds and optional code. The existing payout browser
spec is updated for the code, full-amount constraint and an actual Clear due
round trip. Grepping e2e for Payment Method, TRX ID and Clear due found no bare
accessible-label selectors requiring a suffix correction; the updated flow
uses the required accessible names explicitly.

The required-fields audit was regenerated because the Clear due method is now
visible/marked. It remains **223 findings**; the baseline remains **55** with
no additions or exceptions. The scanner already saw the existing positive-due
method and does not distinguish this runtime branch.

### Verification and limits

- Final `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
  Wayfinder generation, whole-repo ESLint/Prettier, frontend types, **338 Vitest
  files / 2,703 tests**, Pint, PHPStan (**zero errors**), config-cache clearing
  and Pest (**7,759 passed / 43,570 assertions**) completed. Three unchanged
  MySQL-only longitude tests skip on SQLite; no test was added to a skip list,
  loosened or quarantined. No hand-written CI-Verified trailer.
- Focused SQLite: **87 passed / 692 assertions** (new payout/migration tests
  and existing payment controller tests). Focused MySQL 8.4 with
  `DB_CONNECTION=mysql DB_DATABASE=warehouse_test DB_USERNAME=root
  DB_PASSWORD=password` and localhost: **52 passed / 367 assertions**, no skips.
- Dialog Vitest: **10 passed**. The existing payout Playwright spec passed
  **1 Chromium test in 11.9 s**, including a real Clear due submission,
  withdrawal transaction code, native full-amount constraint, confirmation,
  focus, RTL, dark contrast and strict CSP. No retained Blade payout modal
  remains to run. The required browser helper reused Chromium and FFmpeg.
- `npm run types:check`, `composer run types:check`, changed-file ESLint and
  Prettier (including the workflow), `vendor/bin/pint --dirty --format agent`
  and `git diff --check` pass. Required-fields ratchet: **4 passed / 7
  assertions**. Script selftests: all cases passed; CI planner: **23 passed**.
  The normal production build passed with the configured proxy and trust.

Initial verification exposed environment defects: stale frontend dependencies
and Vite assets, Composer's old class map naming removed controllers, and GD
without WebP. Dependencies were restored from the unchanged lockfile, assets and
Composer's autoloader regenerated, and WebP-enabled GD rebuilt outside the repo.
The first full Pest run had 7,752 passes, one failure and six errors from those
runtime defects; the final entire full gate above was rerun after their repair
and passed. Earlier characterization failures were intentional pre-fix evidence.

Not run: the full Playwright suite, the rest of the MySQL migration list,
Composer/npm security audits, deploy-script tests, production data/schema
operations or deployment. The requested focused MySQL and browser tests ran
separately because explicit `--full` does not include those CI-only groups.
The finance-owner grants/sign-off in checklist 4.10 remain an operational owner
responsibility; this change grants no permissions. True partial-payment support
remains future owner-approved work, not an unresolved decision in this repair.


## 61. Page-break defects: D6 repaired, D8 already fixed; D15/D7 remainder (2026-10-25)

Branch `codex/page-break-defects` starts at fetched `origin/develop`
`b24133f4380f60b903c63a8658a257315b96b15b` (includes PR #491).
The requested approximately 30-file cutoff applies: this batch ships D6 and
verification of D8. D15's shared URL fallback and D7's remaining handlers are
explicitly deferred together. No routes/names, permission gates, fillable lists,
product ownership, payouts, required-field marks or migrations change.

### D6: seller dashboard on both database engines

The historical SQLite-only classification was incomplete. Before changing code,
`SellerDashboardTest` failed all four render cases on MySQL 8.4 with strict
`ONLY_FULL_GROUP_BY`: `Expected response status code [200] but received 500`,
`SQLSTATE[42000] ... 1055 Expression #2 of SELECT list is not in GROUP BY clause`.
The selected `DATE_FORMAT(created_at, '%d %b')` differed from the grouped
`DATE_FORMAT(created_at, '%Y-%m-%d')`. The same four cases failed on SQLite:
`SQLSTATE[HY000] ... no such function: DATE_FORMAT`. The two refusal cases passed.

Selection and grouping now both use portable `DATE(created_at)`, sum in SQL,
order chronologically, then format the existing `d M` labels in PHP. There is
no driver switch. The seller, delivered-status and exact rolling seven-day
cutoff are unchanged. Empty charts stay empty. Tests cross December/January,
include October/November orders, two orders on one day, the exact cutoff and
one second before it, pending orders and another seller's orders.

After repairing the query, the same tests exposed two further dashboard 500s:
`Attempt to read property "rating" on null`, and
`Attempted to lazy load [order] on model [App\Models\OrderDetail]`.
The view now renders a missing shop with rating zero and the existing Verify Now
state. Product translations/taxes and category translations are eager loaded;
category counts are scoped SQL counts. Total Sales still sums **line price only**
for this seller's lines whose parent order is paid; quantity is not multiplied.
A SQL aggregate replaces the lazy-loading loop. Paid/unpaid and other-seller
noise lines prove the sum, alongside multiple products/categories under strict
loading. The existing rolling 30/60-day Sold Amount calculations remain unchanged.

Tests: `tests/Feature/SellerDashboardTest.php` (six cases, including both
shop/no-shop empty states, populated rendering, chart boundaries, guest/customer
refusals). The existing retained seller uploader browser spec already lands on
the dashboard after verification; that case now also checks the dashboard
heading, Sales Stat, chart canvas and Top 12 Products. No new standalone spec.

### D8: already repaired by the earlier conversion

The named root methods no longer exist. Sections 21 and 23 superseded the old
section-15 evidence: current `Store/Admin/ProductController.php:530-534`
redirects digital products through `redirect()->route('digitalproducts.edit',
['product' => ..., 'lang' => ...])`. The two entries are the admin back-office
aliases `products.admin.edit` and `products.seller.edit`, not seller-panel
ownership routes. Physical products render their existing edit screen.

New `tests/Feature/Store/Admin/ProductEditRoutingTest.php` passed **all eight
cases on the untouched implementation on MySQL**, before the D6 change:
admin/seller alias × physical/digital × default/Arabic. It asserts the literal
`/temp/admin/digitalproducts/edit/{id}` path, exact named redirect with language,
and follows it to the real working edit page; physical cases assert the component,
list type and language. Thus no wrong-URL failure can honestly be reported for
the current base, and no production D8 change is made. Every Inertia render in
the new file calls `withoutVite()`.

Both new test files are in **both** MySQL lists: tests.yml and pre_push_check.py.

### D15 and D7: reproduced remainder at the file cutoff

D15's initial Blade/PHP inventory finds **76 calls in 41 files, 31 of them
Blade files**, including guarded sites. A consistent helper/accessor adoption
with page-render tests exceeds this batch's approximate 30-file limit. It must
not be reduced by changing the meaning or persisted value of `slug` globally.
No D15 call site is fixed in this batch. On isolated MySQL, disposable regression
probes for `/temp/search?keyword=Slugless`, `/temp/categories` and `/temp/brands`
each expected 200 and received **500**, with `UrlGenerationException: Missing
required parameter` for `product`, `products.category`, `products.brand`.
The next batch must provide the shared fallback, working destination resolution,
and retained-page tests for every affected product/category/brand surface.

D7 probes on MySQL found **500** with
`Call to undefined method App\Http\Controllers\ProductController::get_products_by_subcategory()`
and search **200 with an empty body** (`Expecting '' not to be ''`). The registered
routes remain at `routes/shop-admin.php:141,143`, and search still lacks a return
at `app/Http/Controllers/ProductController.php:96-121`. Searches across routes,
Blade, Vue, public JS and the mobile API found no application caller of these
root helper names beyond their registrations (generated Wayfinder is not a
live caller). Route removal is forbidden by this task; retain and implement
these compatibility names in the remainder rather than guessing at their deletion.

The stock-before-package part of D7 is **already fixed** on the current admin
path: `Concerns/UpdatesStoreProductFlags.php:29-35` checks before
`saveAvailability()`. A separate real MySQL POST to `products.published` with an
invalid package returned **422**, preserved the full stock-row snapshot (quantity
37) and left publication false. The root publishing method was removed in §38;
its existing permanent regression is `ProductControllerTest`:
`publishing a seller product outside its shop package is refused before anything is written`.
No D7 production code changes in this batch. The deferred disposable probes
were kept outside tests/ under work/, not added to or skipped in the passing suite.

### Found on the way, not fixed

- D15 URL call sites remain throughout the inventoried storefront and seller
  views: for example `partials/product_box_1.blade.php:20`,
  `product_details/related_products.blade.php:16`,
  `product_details/offered_products.blade.php:21`,
  `all_category.blade.php:34`, `all_brand.blade.php:31` and the seller dashboard
  product links (`resources/views/shop/seller/dashboard.blade.php`, grep
  `route('product'`). These require the deferred shared URL fix.
- The full retained-seller Chromium spec passes 10/11 cases, including the
  extended dashboard case, but its unchanged withdrawal assertion at
  `tests/e2e/store-legacy-uploader.spec.ts:82` finds the saved message cell
  hidden. `resources/views/shop/seller/money_withdraw_requests/index.blade.php:47`
  marks Message with `data-breakpoints="lg"`; the browser snapshot shows the
  responsive row collapsed. The stored text exists. No payout view, assertion,
  viewport, timeout or retry was changed to make this pass; payout work is
  explicitly excluded from this task.
- `app/Http/Controllers/Api/V2/Seller/ShopController.php:119-120` has a separate
  DATE_FORMAT aggregate. It is outside the seller-home defect; no mobile API
  behavior is changed or claimed verified here.
- `resources/views/shop/seller/dashboard.blade.php` labels rolling 30/60-day
  paid-order totals Current month/Last Month (grep `days_ago_30`); changing the
  reporting period or labels would alter normal-data behavior and is deferred.

### Verification

Final verification results are recorded in the PR and task report. Pre-fix
MySQL: **10 passed / 4 failed / 98 assertions** (D8's eight cases plus two D6
refusals pass). Pre-fix SQLite D6: **2 passed / 4 failed**. The query-only
intermediate D6 run: **4 passed / 2 failed**, exposing missing-shop/strict-loading
failures above. Focused post-fix SQLite and MySQL: **14 passed / 114 assertions**
on each engine, without skips. Deferred MySQL probes: **1 passed / 5 expected
failures / 9 assertions**, demonstrating all three D15 row kinds, both remaining
D7 handlers and the already-safe publishing refusal.

One local MySQL verification attempt incorrectly overlapped two schema-resetting
suites and failed on missing/existing tables. Both runs were discarded and
repeated sequentially; only the isolated results above count. No test was
loosened, quarantined or skipped to address that scheduling error.


Final explicit `python3 .claude/scripts/pre_push_check.py --full`: **passed**.
Wayfinder, whole-repository ESLint/Prettier, frontend types, **338 Vitest files /
2,703 tests**, Pint, PHPStan (**zero errors**), config clear and parallel Pest
completed: **7,773 passed / 3 existing MySQL-only skips on SQLite / 43,684
assertions (228.075 seconds)**. No new skip or quarantine. The full mode does
not run MySQL/browser/audit/deploy lanes; focused MySQL and the affected browser
spec ran separately as above. The commit/PR is labelled **Unverified** because
that browser spec still has its unchanged withdrawal visibility failure.

Separate checks passed: frontend/backend types, dirty Pint, changed-spec ESLint,
changed-spec/workflow Prettier, script selftests, CI planner (**23 tests**),
production build and diff checks. The Playwright helper reused system Chromium
and FFmpeg. Initial missing Wayfinder/packages and stale autoload metadata were
repaired without lockfile changes. WebP-enabled GD was built using the configured
proxy/CA and loaded through task-local PHP configuration; the shared runtime
was not replaced. No raw secrets or real data were used.

Not run locally: the remaining MySQL CI file list, the full application browser
suite, Composer/npm advisory audits, deploy-script tests or production operations.
The three existing SQLite skips are driver-specific longitude precision cases.
D15 and both remaining D7 handlers remain the next batch; the current admin
publishing refusal and D8 already pass. No new business-policy or migration
choice is left to the owner for the shipped D6/D8 work. The unchanged responsive
withdrawal assertion still needs verification before merging. No CI-Verified
trailer was written or edited.

## 62. D15: resolving slugless storefront catalogue URLs, primary pages (2026-10-25)

Branch `codex/null-slug-urls` starts at fetched `origin/develop`
`f7532b293ac5f98031f8f7c4f58d99aef62fc322` (PR #492). This is the
requested **30-file batch**, with an explicit remainder. It does not claim that
all D15 callers are fixed: the primary storefront pages and SEO are repaired;
36 individually inventoried builders remain for the continuation below.

### One read-only address and matching resolution

`App\Support\StorefrontUrl::to(Product|Category|Brand)` is the shared model
URL helper. A non-null, non-empty slug is passed verbatim to its original named
route. This includes numeric slugs and `"0"`: no truthiness fallback and no
changed meaning for the persisted `slug` attribute. Null or `''` uses
`/product/~id-123?catalog_id=123` (or the corresponding category/brand route).
All paths come from named routes; `/temp` is not hardcoded in application code.
`forRow()` serves the sitemap's streamed rows without model hydration.

The query discriminator is intentional. A bare numeric fallback can select a
different row's numeric slug. Even an apparently reserved `~id-N` path could
already be a legacy literal slug. With `catalog_id=N`, the ID resolver runs
only when the token and integer discriminator match exactly, and then restricts
the lookup to a row with a null/empty slug. The same path **without** that
query still resolves an existing literal slug normally. Tests follow both
addresses and assert the exact resolved product, rather than just HTTP 200.
Missing/mismatched IDs return 404. Existing product approval, publication and
auction exclusions still apply. The category and brand actions retain their
existing logging and listing filters. No nullable route parameters, route
names, permissions or D7 handlers are changed.

The helper performs no query or write. No model accessor/mutator changes the
meaning of `slug`, no migration or backfill exists, and no product column is
assigned by this change. Tests snapshot every column before/after rendering,
including a feed-origin row under Option A. An owner-run slug backfill is an
optional separate follow-up and would need a policy for previously issued
fallback links; nothing here requires one to render or resolve these pages.

The related-products query now lives in the existing product controller action,
with explicit columns and eager-loaded translations/taxes. After URL repair,
a multi-product verified-customer test reproduced the additional
`LazyLoadingViolationException` already noted in §43. The offered-products
partial also eagerly loads the columns, translations and taxes its cards read.
The existing ten-item related limit, filters and pricing rules are retained.

### SEO: one actual address, with an explicit sitemap exclusion

`Seo::canonicalUrl()` uses `StorefrontUrl::requestPath()`, which shares the
same generator and retains a matching `catalog_id` as identity. Content query
keys still follow §42; tracking is discarded. A plain fallback is indexable;
adding a sort/filter still gives `noindex, follow`. The canonical and `og:url`
printed by `partials/seo_meta.blade.php` equal the real working fallback URL.
`StructuredData` uses `StorefrontUrl::to()` for Product/Offer URLs and each
product/category/brand breadcrumb. `partials/json_ld.blade.php` continues its
existing safe encoder and prints these same URLs. The two partials require no
source changes: their existing values now come from the shared generator.

The sitemap **deliberately retains §42's exclusion of null and empty slugs** for
products, categories and brands. The new regression follows every generated
sitemap file with both kinds of missing slug present and checks for 200 and no
fallback URL; it also checks that a normal product remains listed. Existing
sitemap tests pass unmodified. Non-empty catalogue sitemap rows now pass
through `forRow()`, giving exactly their previous addresses. Blog, CMS, shop,
flash-deal and customer-product slug routes use other models and stay unchanged.

### Reproduction, before the production fix

The initial SQLite and MySQL runs each reported **27 tests: 3 passed,
20 failed, 4 errors, 47 assertions**. Four errors were the expected missing
new-helper method; six failures were fallback destinations returning 404 on
the original slug-only resolver. Request fixtures were corrected before relying
on their page-specific evidence: AJAX search is POST-only, confirmation is
under `/checkout`, and order lines join products by `product_serial`/`serial`.
The corrected SQLite listing dataset reproduced the customer-order failure too.
The error excerpts below are from actual runs, not inferred from grep.

| Page type / request                                                                                           | Before output                                                                                                                                                    |
| ------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Listing `/temp/search`, category `/temp/category/d15-category`, brand `/temp/brand/d15-brand`, keyword search | `Expected response status code [200] but received 500` — `UrlGenerationException: Missing required parameter for [Route: product] ... [Missing parameter: slug]` |
| AJAX search `POST /temp/ajax-search`                                                                          | Same product-route 500, after correcting the initial GET fixture (which correctly returned 404)                                                                  |
| Product page, related block                                                                                   | Same product-route 500, `related_products.blade.php`                                                                                                             |
| Product page, offered block                                                                                   | Same product-route 500, `offered_products.blade.php`                                                                                                             |
| Category index `/temp/categories`                                                                             | 500 — missing `category_slug` for `products.category`                                                                                                            |
| Brand index `/temp/brands`                                                                                    | 500 — missing `brand_slug` for `products.brand`                                                                                                                  |
| Wishlist `/temp/wishlists`, compare `/temp/compare`                                                           | Same product-route 500                                                                                                                                           |
| Customer order `/temp/purchase_history/details/{encrypted-id}`                                                | Corrected product-serial fixture: same product-route 500; the first incomplete address fixture hit an unrelated missing-address error                            |
| Cart `/temp/cart`                                                                                             | Already 200 with the product retained; the dropdown's raw product links are in a Blade comment                                                                   |
| Confirmation `/temp/checkout/order-confirmed`                                                                 | Already 200 after correcting the path; its raw line-item product links are in a Blade comment, and the live page renders the order summary                       |
| Sitemap index and all its files                                                                               | Already 200; null/empty slugs already excluded explicitly                                                                                                        |
| Product/category/brand fallback destinations, null and empty slugs                                            | 404 before resolver support; six cases now assert 200, matching canonical/JSON-LD and unchanged row snapshots                                                    |
| Deferred seller physical-product list (isolated MySQL probe)                                                  | 500 — product-route `UrlGenerationException`                                                                                                                     |
| Deferred back-office `ReviewResource` (isolated MySQL probe)                                                  | `UrlGenerationException` building its product URL                                                                                                                |

The two intentionally failing remainder probes lived under ignored/uncommitted
`work/`, outside the passing suite. They were not added and then skipped or
quarantined. The architecture guard was also run with an empty remainder:
**1 passed / 1 failed**, listing every retained direct builder, before pinning
the explicit remainder. It checks duplicate occurrences and detects multiline
calls, array parameters, `getAttribute('slug')`, URL facade calls and the known
Vue Wayfinder caller. New raw calls fail; removing a retained call also fails
until its exact entry is deleted. No blanket path exclusion is used.

### Complete call-site inventory and disposition

Re-inventoried with `rg` over `app`, `resources/views`, `resources/js`,
`public/assets/js` and routes, including mobile/API resources, server-built
client URLs, guarded calls and Blade comments. **76 PHP/Blade direct named-route
builders in 41 files (31 Blade files), plus one Vue Wayfinder builder = 77 in
42 files.** Also audited the sitemap's dynamic named-route builder and the SEO
partial pipeline. Generated Wayfinder definitions and test fixture URLs are
not application call sites. Request objects' `$this->route('product')` reads
are route-parameter reads, not URL generation.

Every `file:line` below refers to this batch's final source. Lines grouped in a
cell enumerate separate occurrences, including commented occurrences.

**Handled: 41 direct builders in 19 files**, plus the dynamic sitemap builder
and canonical adapter. The shared helper and three controller lookups are
listed after the table.

| File                                                                       | Lines handled                     |
| -------------------------------------------------------------------------- | --------------------------------- |
| `app/Support/StructuredData.php`                                           | 85, 104, 123, 127                 |
| `resources/views/shop/frontend/all_brand.blade.php`                        | 31                                |
| `resources/views/shop/frontend/all_category.blade.php`                     | 34, 41, 49                        |
| `resources/views/shop/frontend/index.blade.php`                            | 105, 107, 173, 303, 312, 317, 650 |
| `resources/views/shop/frontend/order_confirmed.blade.php`                  | 169                               |
| `resources/views/shop/frontend/partials/cart.blade.php`                    | 58                                |
| `resources/views/shop/frontend/partials/category_elements.blade.php`       | 6, 10                             |
| `resources/views/shop/frontend/partials/category_menu.blade.php`           | 5                                 |
| `resources/views/shop/frontend/partials/home_categories_section.blade.php` | 23                                |
| `resources/views/shop/frontend/partials/product_box_1.blade.php`           | 20, 22                            |
| `resources/views/shop/frontend/partials/search_content.blade.php`          | 20, 33                            |
| `resources/views/shop/frontend/product_details.blade.php`                  | 184                               |
| `resources/views/shop/frontend/product_details/details.blade.php`          | 88                                |
| `resources/views/shop/frontend/product_details/offered_products.blade.php` | 21, 32                            |
| `resources/views/shop/frontend/product_details/related_products.blade.php` | 15, 26                            |
| `resources/views/shop/frontend/product_listing.blade.php`                  | 59, 71, 78, 85, 273               |
| `resources/views/shop/frontend/user/order_details_customer.blade.php`      | 159, 162                          |
| `resources/views/shop/frontend/user/view_wishlist.blade.php`               | 19, 38                            |
| `resources/views/shop/frontend/view_compare.blade.php`                     | 26                                |

Additional adapters: `app/Services/Sitemap.php:189`,
`app/Support/Seo.php:89`; generation and resolution:
`app/Support/StorefrontUrl.php:17,29,45,55`,
`app/Http/Controllers/HomeController.php:253`,
`app/Http/Controllers/SearchController.php:150,160`.

**Remainder: 36 builders in 23 files**, exactly the line-by-line `$remaining`
allowlist in `tests/Feature/StorefrontUrlArchitectureTest.php`. These are
unmodified at the file cutoff; the continuation must migrate each to the helper
(or its correct model's route) and delete that entry. A guarded call is still
listed rather than silently counted as repaired.

| File                                                                           | Remaining lines              | Disposition                                                                                          |
| ------------------------------------------------------------------------------ | ---------------------------- | ---------------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Store/Admin/DynamicPopupController.php`                  | 121                          | Already rejects missing base-product slugs with validation; shared-helper adoption deferred          |
| `app/Http/Resources/Store/DashboardProductResource.php`                        | 27                           | Already guards null/empty and emits null public_url; link restoration deferred                       |
| `app/Http/Resources/Store/DigitalProductResource.php`                          | 39                           | Already guards null/empty and emits null public_url; link restoration deferred                       |
| `app/Http/Resources/Store/ProductListResource.php`                             | 71                           | Already guards null/empty and emits null public_url; link restoration deferred                       |
| `app/Http/Resources/Store/ReviewResource.php`                                  | 32                           | Back-office server-built public link; MySQL probe still reproduces failure                           |
| `app/Http/Resources/V2/AuctionProductDetailCollection.php`                     | 82                           | Mobile/API auction product currently targets ordinary product route, whose resolver rejects auctions |
| `app/Http/Resources/V2/ClassifiedProductDetailCollection.php`                  | 66                           | Mobile/API CustomerProduct resource uses ordinary product route; correct model/route needs follow-up |
| `app/Http/Resources/V2/DigitalProductDetailCollection.php`                     | 69                           | Mobile/API server-built public link; unguarded null-slug failure remains                             |
| `app/Http/Resources/V2/ProductDetailCollection.php`                            | 156                          | Mobile/API server-built public link; unguarded null-slug failure remains                             |
| `resources/js/pages/Store/Admin/Customers/Customers/Views.vue`                 | 68                           | Already hides the link when slug is missing; uses server-provided slug via Wayfinder                 |
| `resources/views/shop/frontend/digital_product_details.blade.php`              | 160, 477, 489, 637, 648, 810 | Unrouted retained Blade view (six builders)                                                          |
| `resources/views/shop/frontend/flash_deal_details.blade.php`                   | 36, 38                       | Retained storefront/account builder; unguarded failure remains                                       |
| `resources/views/shop/frontend/partials/addedToCart.blade.php`                 | 56, 66                       | Retained storefront/account builder; unguarded failure remains                                       |
| `resources/views/shop/frontend/partials/coupon_box.blade.php`                  | 74                           | Retained storefront/account builder; unguarded failure remains                                       |
| `resources/views/shop/frontend/product_details/top_selling_products.blade.php` | 12, 25                       | Already excludes slugless products via topSellingOf; existing exclusion/tests unchanged              |
| `resources/views/shop/frontend/user/customer/dashboard.blade.php`              | 263, 279                     | Retained storefront/account builder; unguarded failure remains                                       |
| `resources/views/shop/frontend/user/digital_purchase_history.blade.php`        | 23                           | Retained storefront/account builder; unguarded failure remains                                       |
| `resources/views/shop/seller/dashboard.blade.php`                              | 542, 561                     | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |
| `resources/views/shop/seller/orders/show.blade.php`                            | 161, 165, 174, 179           | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |
| `resources/views/shop/seller/orders/show_backup.blade.php`                     | 150                          | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |
| `resources/views/shop/seller/product/digitalproducts/index.blade.php`          | 82                           | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |
| `resources/views/shop/seller/product/products/index.blade.php`                 | 95                           | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |
| `resources/views/shop/seller/reviews.blade.php`                                | 31                           | Seller Blade public links; unguarded failure remains (physical list reproduced on MySQL)             |

The inventory also found other `{slug}` routes: `flash-deal-details`,
`shop.visit`/`shop.visit.type`, `customer.product`, `blog.details`,
`mobile.custom-pages`, `custom-pages.show_custom_page`, and the category/brand
routes' actual `{category_slug}`/`{brand_slug}` parameters. Their resolvers were
read: they operate on FlashDeal, Shop, CustomerProduct, Blog or Page rather
than the three D15 models. No nullable route or generic ID resolution is added
for those unrelated models. `SearchController::index` and shop filters also
read a brand slug from filter query input; they do not generate one of these
three URLs. The root D7 `ProductController::search` and
`get_products_by_subcategory` handlers are untouched.

### Verification

Focused SQLite and MySQL: **34 passed / 150 assertions on each engine**, no
skips (32 regression cases plus two architecture cases). The DB-rendering test
file is added to **both** `.github/workflows/tests.yml` and `MYSQL_TESTS` in
`.claude/scripts/pre_push_check.py`. All rendering setup in the new file calls
`withoutVite()`. No existing storefront test was modified. Final broad-gate,
existing-storefront and browser results are recorded below after completion.

Final verification in this environment:

- Existing and new `Storefront*Test.php`: **460 passed / 2,141 assertions**, no skips; no existing test changed.
- Focused new tests: **34 passed / 150 assertions on SQLite and MySQL 8.4**, using `DB_CONNECTION=mysql DB_DATABASE=warehouse_test DB_USERNAME=root DB_PASSWORD=password` for MySQL.
- `npm run types:check`, `composer run types:check` (**zero errors**), dirty Pint, changed-workflow Prettier, script selftests and CI planner (**23 tests**) passed. `npm ci` refreshed stale installed packages; Wayfinder regenerated successfully; the production `npm run build` passed with fonts.
- First explicit `python3 .claude/scripts/pre_push_check.py --full`: Wayfinder, whole ESLint/Prettier, frontend types, **338 Vitest files / 2,703 tests**, Pint and PHPStan passed. Pest: **7,800 passed / 1 failed / 6 errors / 3 existing SQLite skips / 43,790 assertions**, out of 7,810 cases. Five failing/error cases require GD WebP support (admin upload plus four raster-normalisation cases). Two errors were stale Composer classmap entries for deleted root digital-product/trip controllers. `composer dump-autoload --no-scripts` repaired the classmap; the digital-product form tests plus the new tests subsequently passed **89 cases / 632 assertions**. The trip-map controller file also passed **28 cases / 146 assertions** after the refresh. The existing MySQL-only longitude skips were not added or changed.
- A second explicit `--full` passed the frontend checks and all **2,703 Vitest tests**, then stopped at Pint: `Failed to listen on "tcp://127.0.0.1:0": Success`. This attempt ran in the restricted shell because additional network approval was unavailable; the parallel formatter needs a local listener. Standalone dirty Pint passes. PHPStan/Pest were not reached in this second run; the first run above remains the full backend evidence.
- `python3 .claude/scripts/install_playwright.py` reused system Chromium and FFmpeg. The unmodified `store-categories`, `store-brands`, `store-product-create` specs ran: **6 passed / 4 failed**. Passing cases include public category/brand Blade pages and a public product page. Four upload-dependent cases received **422** and could not find the uploaded fixture. The supplied GD build has no `imagewebp` support. An earlier browser attempt was stopped after traces exposed a stale ignored `public/hot` marker pointing to a CSP-blocked Vite dev server; moving that marker aside fixed hydration without changing CSP or tests. A subsequent launch briefly found an orphaned test server; its task-owned processes were stopped before the recorded rerun.
- A task-local WebP-capable GD repair was attempted without replacing the shared runtime. Docker's apt update failed to obtain Release metadata. The next package-download attempt was **not executed**: automatic approval review reported that the workspace was out of credits, explicitly a review failure rather than an unsafe-action determination. The runtime repair remains blocked pending workspace-owner recovery/refill. No platform requirements, tests, CSP rules or upload validation were weakened.
- Whole-file Prettier reports formatting warnings for the two historical `.ai` documents; the same warnings occur on their unchanged base versions. Existing prose is preserved to avoid a large unrelated reformat. The new §62 section and changed workflow pass Prettier. `git diff --check` passes.

Not run: the remaining MySQL CI file list, the full application Playwright suite, Composer/npm advisory audits or deploy-script tests. The full gate and affected browser run are **not green**; the commit and PR carry **Unverified:** for Claude to finish verification on a WebP-capable runtime with local-listener permission. No CI-Verified trailer was written or edited. No production system, real data or secrets were used. The remaining D15 callers above are the explicit follow-up under the requested file cap; no new business-policy decision or data backfill is required for the shipped fallback.


## 63. D15 remainder: storefront and seller Blade URLs (2026-10-25)

Branch `codex/null-slug-urls-remainder` starts at current `origin/develop`
`4d969626cf5cd02d1e849c6d6cd8b5185564bf02` (PR #493). The full continuation
would exceed the requested approximate 30-file budget once API/resource,
server-row, Vue, regression-test and CI/documentation changes are included.
This batch therefore completes the storefront and seller Blade areas first.
The architecture allowlist shrinks **36 → 10**. D15 remains open for the final
API/resources + Vue batch; no remaining raw builder is a permanent exception.

### Migrated inventory

All addresses below retain their original absolute form. Nonempty slugs,
including `"0"`, retain their exact former URL bytes; null/empty slugs pass
through `StorefrontUrl::to()`. No product columns, fillable lists, route names,
permissions, payouts, staff/roles, marks, D7 handlers, migrations or data are changed.

| Blade file under `resources/views/shop/`        | Original builders | Disposition                                                                                   |
| ----------------------------------------------- | ----------------: | --------------------------------------------------------------------------------------------- |
| `frontend/digital_product_details`              |                 6 | Brand logo, top/related cards and conversation default use the helper.                        |
| `frontend/flash_deal_details`                   |                 2 | Delete unused `$product_url` calculations; the included product card already uses the helper. |
| `frontend/partials/addedToCart`                 |                 2 | Related product image/name links use the helper.                                              |
| `frontend/partials/coupon_box`                  |                 1 | Product coupon links use the helper.                                                          |
| `frontend/product_details/top_selling_products` |                 2 | Use the helper; retain the existing `topSellingOf` slugless exclusion and filters.            |
| `frontend/user/customer/dashboard`              |                 2 | Wishlist image/name links use the helper.                                                     |
| `frontend/user/digital_purchase_history`        |                 1 | Purchased-product links use the helper.                                                       |
| `seller/dashboard`                              |                 2 | Top-product image/name links use the helper.                                                  |
| `seller/orders/show`                            |                 4 | Both physical and auction image/name branches use the helper.                                 |
| `seller/orders/show_backup`                     |                 1 | Delete the unreferenced backup.                                                               |
| `seller/product/digitalproducts/index`          |                 1 | Product link uses the helper.                                                                 |
| `seller/product/products/index`                 |                 1 | Product link uses the helper.                                                                 |
| `seller/reviews`                                |                 1 | Reviewed-product link uses the helper.                                                        |

The backup search (`rg show_backup app routes resources config tests
public/assets/js`) finds only the old architecture allowlist, with no renderer,
include or route. No source references its view name. The flash-deal variable
has no consumer; its card constructs its own address, so deleting both unused
calculations preserves normal output.

The retained digital view has no production route (§42); tests render it through
a test-only web route. Its two product queries now eager-load translations,
taxes and stocks, with narrow relation columns, after the new multi-product
render reproduced a strict-loading exception. Limits, filters and prices stay.
Its adjacent brand-name link historically targets `shop.visit`, unlike the brand
logo. That old nonempty-slug URL stays byte-identical; only a missing brand slug
uses the shared brand fallback, otherwise the adjacent link still throws after
the logo is repaired. Tests pin both normal URLs. Correcting that historical
normal-slug destination is a separate behavior change.

Seller auction order links retain the ordinary product route they already used.
The helper makes their URL generation safe, but the existing storefront resolver
still excludes auctions. This batch does not broaden storefront visibility or
invent an auction route. Normal and fallback URL strings are both tested.

### Reproduction and normal-slug evidence

The corrected before-fix SQLite suite reports **26 failed / 23 passed /
90 assertions**, before any production edit. Ten storefront/account/fragment
cases and twelve seller cases reproduce `Expected response status code [200]
but received 500` with `UrlGenerationException: Missing required parameter ...
[Missing parameter: slug]`. The digital view's null/blank-brand cases reproduce
missing `brand_slug`; its normal/zero cases expose the existing
`LazyLoadingViolationException` on product translations.

Initial fixture errors (single-segment test URLs shadowed by the CMS catch-all,
missing coupon dates and the seller order's legacy manual-payment columns) were
corrected before relying on that reproduction. Seller reviews' actual endpoint
also has an unrelated `ambiguous column name: id` query error, before it reaches
the template. Its four render cases therefore use a test-only web/auth route
with the real review rows, seller login and unchanged Blade view. The endpoint
query is deliberately not changed or claimed fixed.

`StorefrontRemainingBladeUrlTest` has **49 cases**: null, blank, normal and zero
slugs for each migrated live page/fragment, physical and auction order branches,
and the retained digital view; plus exact top-widget URL/exclusion coverage.
It parses actual rendered anchors and compares their full `href` bytes with the
original named-route URL for normal/zero rows. It also checks the digital
conversation textarea, both brand links and full product-column snapshots.
The backup has no live page to render; the architecture scan proves no raw Blade
builder remains. The guarded top widget was already safe and keeps its filter.

### Final ten callers, not exceptions

The exact final file:line inventory remains in
`tests/Feature/StorefrontUrlArchitectureTest.php`:

- DynamicPopupController:121;
- Store DashboardProductResource:27, DigitalProductResource:39,
  ProductListResource:71 and ReviewResource:32;
- V2 AuctionProductDetailCollection:82, ClassifiedProductDetailCollection:66,
  DigitalProductDetailCollection:69 and ProductDetailCollection:156;
- Customers/Customers/Views.vue:68.

The final batch must preserve each API URL's absolute/relative form and JSON
shape, send a finished server URL to the customer Views row, extend its mounted
Vitest, and remove these final ten entries. No TypeScript fallback or generated
Wayfinder edit is introduced here. Checklist D15 remains explicitly partial.

### Verification

The focused SQLite run passes **93 tests / 330 assertions**: the 49 new cases,
the architecture guard, existing null-slug regressions, top-selling and seller
dashboard tests. Focused MySQL **8.4** passes **83 tests / 294 assertions**, no
skips, with the requested `warehouse_test`, root/password test settings: the new
file, architecture guard and existing null-slug file. The new file is in both
CI's MySQL list and `MYSQL_TESTS`; every new rendering setup calls `withoutVite()`.
PHPStan reports zero errors; dirty Pint and script selftests pass. The existing
customer-page Vitest passes **9 tests**; no Vue source changed in this batch.

Final explicit `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
Wayfinder generation, whole-repository ESLint/Prettier, frontend types,
**338 Vitest files / 2,703 tests**, Pint, PHPStan (**zero errors**), config clear
and parallel Pest all pass: **7,856 passed / 3 existing MySQL-only skips on
SQLite / 43,978 assertions (184.169 seconds)**. Vitest uses four workers;
no timeout, retry, skip, quarantine or checker configuration changes. No bare
or attested run and no CI-Verified trailer were used.

The portable Playwright installer reused system Chromium and FFmpeg. The four
existing affected specs (seller legacy uploader, storefront address, categories
and brands) pass **18 Chromium cases (1.4 minutes)**. The new
`store-slugless-catalogue.spec.ts` passes **one case (11.3 seconds)**, following
the real fallback from customer dashboard, digital purchase history, flash deal
and coupon pages to the expected product; the coupon retains its new-tab link.
Its draft fixture initially omitted required order fields and its initial
assertions overlooked the bilingual heading and new-tab behavior. Those test
fixtures/assertions were corrected without changing application behavior or
loosening deadlines. Final changed-spec ESLint and Prettier pass.

The first build exposed missing locked font packages; `npm ci` restored them
without manifest/lock changes. Wayfinder regenerated through repository tooling,
and the normal production build passed. The supplied GD lacked WebP; a local
Docker build with the configured proxy/CA produced a task-local extension and
PHP configuration, without replacing the shared runtime. Composer's autoloader
was refreshed before the full gate. An ignored stale `public/hot` was backed up
outside the repository so browser tests use the production assets. The isolated
MySQL/PHP containers were removed after focused verification.

Script selftests pass, CI planner passes **23 tests**, and `git diff --check`
passes. Whole-file Prettier warns on the historical guide and checklist; the
same warnings reproduce on both unchanged base files. The new guide section,
workflow and browser spec are formatted; historical prose is not reformatted.
This batch contains **20 changed files**, including the two new test files.

Not run locally: the rest of the MySQL CI list, the whole application browser
suite, Composer/npm advisory audits or deploy-script tests. Focused MySQL and
Chromium ran separately from explicit `--full`. API/resource/Vue migration and
its slugless browser row remain the final batch. D15 is not marked fully fixed.
No owner policy decision blocks this batch. Seller reviews' unrelated query,
the retained digital brand-name normal destination, and auction visibility
remain explicitly documented above rather than silently altered.

### CI follow-up: multiple active coupons

PR #494's first full Chromium run passed 145 cases and failed the new catalogue
case at `/temp/coupons`: `LazyLoadingViolationException` for `Coupon::user`.
The isolated browser fixture had only one active coupon, which did not exercise
Laravel's collection lazy-loading guard. Running the seller uploader and
marketing specs before it reproduced the failure (13 passed, one failed).

`HomeController::all_coupons()` now selects the listing's columns and eagerly
loads `user` and `user.shop` with their required columns. Date filters,
pagination, visibility checks and URL destinations are unchanged. The browser
fixture includes two active coupons and reports the requested path in a failed
status assertion. A real endpoint Pest regression uses both an admin and a
verified seller owner for null, blank, normal and `0` product slugs: all four
failed before the fix with the same 500/lazy-loading exception. The existing
MySQL registrations already cover the extended test file. The architecture
allowlist remains 10; this batch now changes 21 files.

After the fix, focused Pest passes **87 tests / 314 assertions** on SQLite and
MySQL **8.4.13**, with no skips in either focused run. The portable browser
installer passes, and the **entire Chromium suite passes 146 tests (9.3
minutes)**, including the previously failing catalogue case in suite order.
Dirty Pint, changed-spec ESLint/Prettier and `git diff --check` pass.

The final explicit `python3 .claude/scripts/pre_push_check.py --full` exits
**0**: Wayfinder, ESLint, Prettier, TypeScript, **338 Vitest files / 2,703
tests**, Pint, PHPStan (zero errors), configuration clear and parallel Pest
pass. Pest reports **7,860 passed / 43,998 assertions / three existing
MySQL-only skips on SQLite (158.632 seconds)**. Focused MySQL and the complete
browser run above were separate from `--full`; advisory audits, deploy-script
tests and the remainder of the MySQL CI list were not rerun locally. No test
was loosened, retried, quarantined or newly skipped; no trailer was hand-edited.


## 64. D15 complete: API resources, popup targets and customer Views (2026-10-25)

Branch `codex/null-slug-urls-final` starts at fetched `origin/develop`
`f6664ccb221229461b1b110f6e592ef44aa2f0c4`, including PRs #493 and #494.
The architecture test initially passed with exactly ten builders. All ten now
use `StorefrontUrl`; `$remaining` is empty and an explicit assertion prevents
reintroducing an allowlist. **Final direct-builder inventory: none. D15 is FIXED.**

### URL contracts and payloads

| Caller                               | Preserved URL form and field                              |
| ------------------------------------ | --------------------------------------------------------- |
| Store DashboardProductResource       | Absolute `public_url`                                     |
| Store DigitalProductResource         | Absolute `public_url`; encrypted download token unchanged |
| Store ProductListResource            | Absolute `public_url`                                     |
| Store ReviewResource                 | Absolute `product.url`                                    |
| V2 AuctionProductDetailCollection    | Absolute `data[].link`, historical ordinary product route |
| V2 ClassifiedProductDetailCollection | Absolute `data[].link`, historical ordinary product route |
| V2 DigitalProductDetailCollection    | Absolute `data[].link`                                    |
| V2 ProductDetailCollection           | Absolute `data[].link`                                    |
| DynamicPopupController               | Absolute stored `btn_link`                                |
| Customers/Customers/Views.vue        | Relative `public_url` supplied by the server              |

Every normal and `"0"` slug is compared against the full original named-route
string, including scheme, host and path. Each resource has null/empty regressions
and a complete normal-row payload assertion against the committed fixture.
Generated IDs and encrypted download tokens are checked before normalization;
JSON key names, nesting, wrapping and every other value are pinned. Existing
whole-row Inertia assertions deliberately gain the customer URL field or replace
an old null link expectation with the helper's fallback.

The V2 product, classified and auction cases use their real endpoints with a
Sanctum bearer token from `storeApiToken(createStoreUser())`. The digital detail
collection has no live controller return (only commented references), so it is
serialized directly. The auction addon is uninstalled: its schema lacks bid
rows and auction attributes. Its endpoint test supplies those addon-only values
on retrieved models in memory; it changes no schema and still runs the real
route, controller, middleware and serializer.

The classified collection consumes `CustomerProduct`, outside `to()`'s model
union. `forRow('product', slug, id)` preserves its existing ordinary-product URL
contract. This does not turn a classified record into a catalogue product or
repair its historical destination. Likewise the ordinary storefront resolver
still excludes auctions. Correcting those destinations/visibility would be a
separate behavior change, prohibited by this batch's URL and route constraints.
All serialization is safe; no new public visibility or ID lookup is introduced.

The customer controller's existing bounded product query supplies the viewed
Product as a loaded relation to `CustomerPageViewResource`. That resource adds
`public_url` through `StorefrontUrl::to($product, false)`, preserving all prior
fields. Vue binds the completed URL directly and contains no fallback logic.
The existing shared customer types include the additive row interface;
Wayfinder is regenerated with repository tooling, with no generated file edits.
Category rows and deleted-product rows remain unlinked, including when a
category's ID equals a product's ID. Mounted Vitest covers null, empty, normal
and zero slugs; Chromium follows each actual server-supplied link.

Popup buttons are consumed verbatim by the homepage and product-listing Blade
anchors; the admin list displays the stored address. Offer selection and
validation now accept null/empty base-product slugs while still requiring an
existing base product and rejecting whitespace-only values. `LENGTH(slug) = 0`
is intentional: MySQL's collation makes `slug = ''` also match spaces. Store and
update derive `btn_link` with `forRow()`, retain the existing colors, and ignore
forged button fields. Tests assert both writes and the rendered homepage href.
No Blade source, route, permission, product column, migration, backfill, D7
handler, payout, staff/role or required-field behavior changes.

### Failing-first evidence

Before application edits, corrected resource regressions report **36 cases:
16 passed, 16 failed, four errors, 98 assertions**. Both missing-slug values
are covered for every resource. ReviewResource and the unrouted digital detail
collection throw `UrlGenerationException: Missing required parameter for
[Route: product] ... [Missing parameter: slug]`. The real V2 product, classified
and auction endpoints return 500 with the same exception. Dashboard, digital
list and product list were guarded already: their failures are an actual null
versus the expected fallback URL, not a claimed exception. Customer rows fail
because the new field is absent. The auction fixture was corrected for missing
addon attributes before recording these results.

Popup before-fix tests report **two failed, two passed, 52 assertions**: null and
empty targets are omitted from offer options. Existing validation rejected them;
there was no URL-generation exception to claim. Normal and zero already pass.
The mounted Vue suite, after regenerating its previously absent Wayfinder
imports, reports **two failed, 11 passed**: `Unable to get td a` for null and
empty rows. Normal and zero already retain their expected relative hrefs.

### Verification

- Initial architecture test: **2 passed / 2 assertions**, exact ten-entry list.
  Final architecture test: **2 passed / 3 assertions**, empty list.
- Focused SQLite: **445 passed / 3,236 assertions**, no skips. This includes
  the new resource/row cases, popup/customer controllers, prior null-slug and
  Blade regressions, and affected dashboard/digital/physical product tests.
- Focused MySQL **8.4**: **239 passed / 1,296 assertions**, no skips, using
  `DB_CONNECTION=mysql DB_DATABASE=warehouse_test DB_USERNAME=root DB_PASSWORD=password`.
  The new resource file and both popup/customer controller files are registered
  in CI's MySQL list and `MYSQL_TESTS`. New rendering cases call `withoutVite()`.
- Customers module Vitest: **3 files / 27 tests passed**, including the Views
  file's **13 tests**. Final whole-suite Vitest: **338 files / 2,707 tests passed**.
  No StoreProductFormFields timeout or isolated retry occurred.
- `npm run types:check` and `composer run types:check` pass; PHPStan reports
  **zero errors**. Wayfinder regenerated with `--skip-routes --with-form`, and
  the normal production `npm run build` passes. No generated file was edited.
- Changed-file ESLint and Prettier, dirty Pint, script selftests, CI planner
  (**23 tests**) and `git diff --check` pass. Whole-file Prettier warns on the
  historical guide/checklist, identically on their unchanged base versions;
  the new section is formatted without rewriting historical prose.
- `python3 .claude/scripts/install_playwright.py` reused system Chromium and
  FFmpeg. Both affected browser specs (`store-campaign-forms` and the extended
  `store-slugless-catalogue`) pass **4 Chromium tests in 23.3 seconds**, including
  actual customer Views navigation for null, empty, normal and zero slugs.
- Final explicit `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
  Wayfinder, whole ESLint/Prettier, frontend types, all Vitest, Pint, PHPStan,
  config clear and parallel Pest pass. Pest reports **7,901 passed / 44,223
  assertions / three existing MySQL-only skips on SQLite (142.323 seconds)**.
  The first full attempt stopped at one new browser-spec lint spacing error;
  it was corrected before this successful full rerun.

The host initially had no PHP executable. The existing Docker PHP 8.5.11 runtime
and its libraries were copied into ignored task-local `work/`; GD was rebuilt
with WebP for the full suite. Locked npm dependencies and Composer's optimized
autoloader were refreshed without manifest/lock changes. An ignored stale
`public/hot` was backed up in `work/` so Chromium used built assets. No platform
requirements, tests, timeouts, CSP or upload validation were weakened; no
CI-Verified trailer was written or edited.

Not run locally: the rest of the MySQL CI list, the whole application browser
suite, Composer/npm advisory audits and deploy-script tests. All specifically
requested focused checks and the explicit full gate ran. This batch changes
**28 files**. No owner decision blocks D15; the historical classified/auction
URL destinations described above remain a separate behavior-policy follow-up.

## 65. D7: retire the unconsumed root product handlers (2026-10-25)

Branch `codex/product-handlers-d7` starts at fetched `origin/develop`
`2067dde296f66bb182e598ff5b49bf7fd29295bb`, including PR #495. This task
explicitly authorizes removal **only after proving no live consumer**, superseding
§61's earlier restriction on deleting these compatibility routes. Both qualify.
The already-safe package refusal and its permanent stock-preservation test from
PR #492 are retained; no publication writer is changed.

### Consumer inventory and decisions

The following commands were run before production edits; the `git grep` form
makes the evidence reproducible against the exact base even after removal:

```sh
git grep -n -E "get_products_by_subcategory|['\"]products\\.search['\"]|/products/search" 2067dde296f66bb182e598ff5b49bf7fd29295bb -- resources routes tests public/assets config app/Http/Controllers/ProductController.php
rg -n -F '@/actions/App/Http/Controllers/ProductController' resources/js --glob '!actions/**'
rg -n 'get_products_by_subcategory|products.search|products/search' resources routes tests public/assets config app --glob '!*.map' --glob '!vendors.js' --glob '!resources/js/actions/**' --glob '!resources/js/routes/**'
rg -n 'get_products_by_subcategory' resources/js/actions/App/Http/Controllers/ProductController.ts
```

| Root handler                  | Findings                                                                                                                                                                                                                                                                                                                                    | Decision                                                                    |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `get_products_by_subcategory` | Only its shop-admin registration, the ungated-route ratchet entry and generated Wayfinder definition. No Blade, Vue, public JS, menu/config, mobile/API or application caller. No controller method exists.                                                                                                                                 | Remove its POST route and ratchet entry; regenerate Wayfinder.              |
| `search`                      | Only its root definition, shop-admin registration, ratchet entry and generated Wayfinder definition. Both Vue imports from the root controller import **fetch only**. `store.products.search` in the list and its test is a translation label; the actual list submits GET filters to the migrated list action. No live root-search caller. | Remove its POST route, dead method and ratchet entry; regenerate Wayfinder. |

Other `products/search` matches were individually resolved: WMS routes/tests
use `Admin\ProductController`; mobile routes use `Api\V2\ProductController`
and `Api\V2\Seller\ProductController`; customer-product search uses its own
controller. The sidebar/menu contains no reference to either retired handler.
Generated actions are declarations, not consumers, and are ignored by Git.
No generated file was hand-edited.

The source-level WMS `products.search` name sits inside `name('admin.')`, so
its effective name is **admin.products.search** at GET `/admin/products/search`.
The removed store name was **products.search** at POST
`/temp/admin/products/search`. Before and after route listings confirm those
are distinct; tests pin the WMS URI, verbs and controller and reject duplicate
registered names. Neither `routes/web.php` nor any WMS handler changes.

### Contract and failing-first evidence

Before application edits, the new permanent regression file reported
**14 tests: 13 failed, 1 passed, 23 assertions** on SQLite. For product-list
staff, subcategory returned **500**, with
`Call to undefined method App\Http\Controllers\ProductController::get_products_by_subcategory()`;
search failed **Expecting '' not to be ''**, reproducing the empty response.
The route-absence test also failed because the registrations still existed.
Guest JSON requests returned 401; customer/seller requests returned 404;
unprivileged staff could reach both broken handlers. These were real requests,
not conclusions inferred from source.

After removal, both POST URIs return **405 for every caller**, including guests,
customers, sellers, staff without permissions, product-list staff and Super Admin.
An existing DELETE `/products/{product}` matches the same path pattern, so 405,
rather than 404, is the router's correct absence response. No product payload or
successful empty 200 remains. The `search` versus `q` mismatch is eliminated with
the dead method; there is no retained input/JSON/view/search-matching contract to
invent. FormRequest validation, result limits and 403 permission gates apply to
kept handlers; these removed endpoints have no handler to authorize or validate.
The caller matrix instead pins their uniform 405 rejection before dispatch.

`Store/Admin/RootProductHandlersTest.php` snapshots **every column of every
product row**, including store-origin and feed-origin noise, and checks both
private names stay out of every response. It also asserts both root methods and
routes are absent, preserves WMS search and scans application sources for
consumer names/URLs/imports, excluding generated Wayfinder directories. The two
obsolete exceptions leave `LegacyAdminPermissionsTest`; its remaining gates
are unchanged. Existing root fetch, SKU and details consumers remain.
Option A, permission definitions, migrations and all other D-items are unchanged.
The new regression file is registered in **both** MySQL test lists.

### Verification

Focused SQLite (new regression and permission ratchet): **20 passed / 92 assertions**.
Focused MySQL 8.4 (`warehouse_test`, root/password, loopback port 3307):
**14 passed / 71 assertions**, no skips. Frontend types and PHPStan (**zero
errors**) pass. Wayfinder regenerated through
`php artisan wayfinder:generate --skip-routes --with-form`. Dirty Pint, workflow
Prettier, script selftests and CI planner (**23 tests**) pass.

The normal production build passes. The portable Playwright helper reused system
Chromium and FFmpeg; the unchanged `store-products.spec.ts` passes **2 tests
(17.6 seconds)**, covering list actions, Inertia navigation, RTL and dark contrast.
Focused product-list Vitest passes **15 tests**. Whole-file Prettier warns on
the historical guide/checklist, identically on their unchanged base versions;
the new guide section and workflow pass without reformatting historical prose.
Final full-gate results follow below.
The host lacked PHP; a task-local copy of the existing Docker PHP 8.5.11 runtime
and libraries supplies it. GD is rebuilt with WebP in a task-owned image and
copied only into that local runtime. Locked npm dependencies and Composer's
optimized autoloader are refreshed without manifest or lockfile changes. A stale
ignored `public/hot` is backed up outside the repository before browser testing.
No test assertion, timeout, retry, platform requirement or CSP setting is relaxed.

Final explicit `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
Wayfinder, whole-repository ESLint/Prettier, frontend types, **338 Vitest files /
2,707 tests**, Pint, PHPStan (**zero errors**), config clear and parallel Pest
pass. Pest reports **7,915 passed / 44,294 assertions / three existing MySQL-only
skips on SQLite (137.376 seconds)**. No StoreProductFormFields timeout or isolated
retry occurred. Focused MySQL and both Chromium cases ran separately from this
explicit full mode. No bare or attested run was used, and no CI-Verified trailer
was written or edited. `git diff --check` passes. The change fits **eight files**.

Not run locally: the remaining MySQL CI file list, the whole application browser
suite, Composer/npm advisory audits or deploy-script tests. The workflow change
runs every CI group on the non-draft PR. No production data, migration or deployment
operation was performed. **No owner decision remains for D7.**


## 66. D10: trip write scoping (2026-10-26)

Branch `codex/trip-write-scoping` starts at current `origin/develop`,
`29fbb8216c1ac0a9df45f60fef5bafee046e8783` (PR #496). The existing URLs carry
only an order or checkpoint ID; Trips → Orders is a global list, including
unattached orders. The owner confirmed using **required `trip_id` request
context** while retaining every route, route name and permission.

### Conservative rules

1. **Order ownership.** Invoice, delivered and bulk-status writes resolve the
   order through the supplied trip's `checkpoints()` relation, restricted to
   the `Order` polymorphic type. There is no trip-order pivot. Unattached orders,
   another trip's orders and a customer stop with the same numeric ID cannot
   authorize the write. A missing parent or route-bound child, or a child outside
   that parent, is **404**. Bulk references retain the module's **422 `id`**
   convention, and the whole selection rolls back if any attachment is invalid.
2. **Checkpoint ownership.** Notes and status resolve through that same parent's
   `checkpoints()` relation, with the existing `lockForUpdate`; a foreign or
   missing checkpoint is **404**. `trip_id` is validated by the shared
   `TripWriteRequest`: absent, malformed or nonpositive values are **422
   `trip_id`**. Parent and child lookups happen inside the transaction.
3. **Finished trips.** Actual `TripStatus` cases are `pending`, `started` and
   `closed`. Only `closed` is terminal; no cancelled/finished state is invented.
   These order/checkpoint endpoints reject a closed parent with **422 `trip_id`**,
   “Closed trips are read-only.” (English and Arabic). They lock the parent
   before its children, so closure and child writes serialize. Reads, the trip
   status/reopen flow and the CMR download remain available and unchanged.
4. **Mark delivered.** `orders.delivery_status` is nullable free text, separate
   from `payment_status`, `wms_status`, delivery dates and the checkpoint status.
   Legitimate source states are `confirmed`, `ready_for_delivery`, `on_delivery`:
   trip creation/map assignment use out-for-delivery orders, and the existing
   bulk actions can subsequently set confirmed or ready-for-delivery. Pending,
   unknown/null, already-delivered and cancelled values are **422
   `delivery_status`**. Valid delivery still changes only the order's
   `delivery_status` to `delivered`. It does not mark checkpoints delivered,
   settle payment/commission or notify anyone. Invoice save/clear still sets
   `on_delivery` for any attached order's prior status on an open trip; bulk
   still sets either existing bulk target status. No additional status rule is
   invented for those two actions. The trip-update driver-notification fix
   (§26: integer comparison; notify only when the driver changes) is untouched.

The three pages now supply their displayed parent ID. Unattached list orders
have no invoice/delivery control; the map's `orderTrips` payload contains only
actual `Order` attachments to open trips, with the latest attachment per order.
Unassigned map markers stay available for assignment. A bulk status selection
must have one common trip; delete-selection behavior is unchanged. The detail
hides checkpoint write controls when closed. Server errors remain visible and
server checks are authoritative for stale pages. `trip_id` is removed from
redirect queries; the checkpoint redirect's parent cannot be overridden by a
query parameter.

### Evidence and tests

The unchanged endpoint suite passed **66 tests / 612 assertions** before the
fix. The first reproduction run failed all **13** added cases. Required defect
output included:

- foreign order delivered: `- delivery_status: on_delivery; + delivered`;
- foreign invoice: `- invoice_number: OLD; + WRONG`;
- foreign checkpoint status: `- pending / arrived_at null; + delivered / timestamp`;
- closed-trip checkpoint: the same unauthorized status/arrival mutation;
- already delivered: `Expected response status code [422] but received 302`;
- cancelled: `- delivery_status: cancelled; + delivered`.

Two initial extra rejections for confirmed/ready orders were corrected after
tracing the legitimate bulk flow: they are now explicit success cases, preserving
existing valid behavior. No existing test was skipped, quarantined or weakened.

`TripWriteScopingTest` snapshots every column of every row in `orders`,
`order_details`, `checkpoints`, `trips`, `trucks`, `notifications`, `wms_jobs`,
`wms_job_batches`, `wms_failed_jobs`, with unrelated trip/order/checkpoint noise.
Every refusal checks exact before/after equality plus no sent notifications or
queued jobs. Coverage includes cross-trip and unattached references, morph-type
collisions, closed parents, missing/malformed parents, missing children, invalid
order states, each route's guest/customer/unprivileged-staff rejections and
mixed-selection rollback after an earlier valid bulk write. Successful snapshots
pin order-only delivery/invoice changes, checkpoint notes/status changes and
first-arrival retention, with payment/commission/attachments/trips untouched.
Closed-trip reads still work. Existing controller tests retain their rejection
matrices and now submit explicit parent context; Inertia readers call
`withoutVite()`. The regression file is registered in both MySQL test lists.

Vue tests pin submitted parent IDs, unassigned controls, mixed-trip selection,
closed-trip reads and native confirmation. Browser fixtures now attach orders
before testing valid writes, and the map spec also proves delivery is absent
before attachment. Existing accounting-ID fixtures use a schema-valid integer,
retaining their exact payload/search assertions on SQLite and MySQL.

**CMR limit left alone:** download still opens only the first upload ID in
`cmr_file`; the driver API stores one. Printing still uses the browser's print
layout. No CMR code or behavior changed. No owner decision remains for the D10
write rules; the first-ID CMR behavior is explicitly outside this task.

Verification results are recorded with the PR and final task report.

### Final verification

- Focused SQLite: **251 passed / 1,885 assertions**, including the unchanged
  driver-notification cases; focused MySQL 8.4: **150 passed / 1,124 assertions**,
  no skips. The added map-context payload case runs on both engines.
- Explicit `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
  Wayfinder, whole ESLint/Prettier, frontend types, **338 Vitest files / 2,710
  tests**, Pint, PHPStan (**zero errors**), config clear and parallel Pest pass.
  Pest: **7,971 passed / 44,660 assertions**, plus **three existing MySQL-only
  skips on SQLite** (143.618 seconds). No StoreProductFormFields timeout or
  isolated retry occurred.
- Trip and driver Playwright: **8 passed (39.5 seconds)** across all five
  requested specs; enforced CSP, unchanged assertions/timeouts/retries.
  Focused page Vitest: **43 passed**. Production build, changed-file ESLint and
  Prettier, script selftests, **23 CI planner tests** and `git diff --check` pass.
- Setup repairs used only the task-local runtime: locked npm refresh, stale
  Composer classmap refresh, an ignored stale `public/hot` moved aside, and GD
  rebuilt with WebP. The initial full run's five unrelated upload failures
  disappear with WebP; all **87 unchanged upload tests** pass. No manifest,
  lockfile, upload code, platform requirement or test was altered for setup.
- Whole-file Markdown warnings reproduce on all three unchanged base documents;
  the new guide section passes Prettier without reformatting historical prose.
- Not run locally: the remaining MySQL CI list, the whole application Playwright
  suite, advisory audits or deploy-script tests. Both MySQL lists contain the
  new regression file; the workflow edit runs every CI group on the non-draft PR.
  No bare/attested pre-push run or hand-edited CI-Verified trailer was used.

The change spans **29 files**, above the initial estimate because explicit parent
context reaches five existing FormRequests, three pages, the map payload,
translations and their endpoint/component/browser tests, both MySQL lists and
three required documentation updates. Routes, permissions, migrations and all
other D-items remain untouched.

## 67. D16: persist self-registration business fields (2026-10-26)

Branch `codex/registration-fields` starts at current `origin/develop`
`7f622a71a0a8bc344bfb253885297114708c29c3`. This implements the owner's
2026-10-25 D16 decision. No migration, route, permission, payout, staff, trip or
product-ownership change is involved.

### Characterization before the fix

Both registration pages post to `RegisterController::register`; it has an inline
`validator()` rather than a FormRequest. `create()` writes `StoreUser` (`users`),
then its default `addresses` row. `Customer` only permits `user_id`; it is not the
business-field storage used by `store_customer`.

| Input                                        | Posted before                                                                                                 | Validated before | Stored before                                |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ---------------- | -------------------------------------------- |
| `tax_number` on `/temp/register`             | Yes                                                                                                           | No               | No                                           |
| `company_address` on `/temp/register`        | Yes                                                                                                           | No               | No                                           |
| `trade_license` on `/temp/register`          | The browser sent its filename because multipart encoding was missing; a real multipart POST accepted the file | No               | No                                           |
| `shipping_address` on `/temp/register`       | Yes                                                                                                           | No               | No; its address assignment was commented out |
| `country_code` on `/temp/register`           | Hidden input populated by the phone widget while the OTP addon was enabled                                    | No               | No                                           |
| `country_code` on `/temp/users/registration` | Same                                                                                                          | No               | No                                           |

The grep did find two registration-controller references to `country_code`,
contrary to the earlier audit's shorthand "read by nothing": a pre-validation
phone-duplicate probe for an invalid/missing email, and a phone-only creation
branch. The existing `required|email` rule prevents that creation branch from
running on a valid registration. The email unique rule now returns the requested
422 for duplicates, before any file or account write. The obsolete duplicate
probes and unreachable phone-only branch are removed with both registration
inputs and their local widget assignments. Login/password-reset consumers and
the shared layout's phone widget remain intact. No successful phone-only
registration existed under the previous validator. The reachable email
verification, terms modal, country selection and phone widget behavior are
unchanged.

### Storage and validation

`users.tax_number`, `users.company_address` and `users.trade_license` already
exist and are fillable. Registration now explicitly writes the first two and the
license's generated basename, using the same `TradeLicenseStorage` as
`CustomerController::store_customer`. Files go to the **local private disk**,
`storage/app/private/uploads/licenses/<random 40-character name>.<content extension>`.
There is no upload-table row and no public copy. The existing content checks and
`store:move-trade-licenses` conventions remain authoritative; the migration
command is for old public files, not for new registrations. A supplied string,
URL or existing stored path cannot substitute for an uploaded file. Outside
paths are also refused by the existing WAF (403).

| Rule            | Before                                                 | After                                                                                               |
| --------------- | ------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| Name            | required/string/max 255                                | required/string/max 191                                                                             |
| Company name    | required/string/max 255                                | required/string/max 191                                                                             |
| Email           | required/email; duplicate email flashed and redirected | required/string/email/max 191/unique on store `users`; JSON duplicate is 422                        |
| Phone           | required                                               | required/string/max 20                                                                              |
| Country         | required                                               | required/string/max 30; same country-select behavior                                                |
| Tax number      | No rule                                                | nullable/string/max 100                                                                             |
| Company address | No rule                                                | nullable/string/max 191                                                                             |
| Trade license   | No rule                                                | nullable/file/mimes doc,docx,pdf,png,jpg,jpeg/max 20000 KiB; existing private-storage content check |
| Password        | required/string/min 6/confirmed                        | Unchanged                                                                                           |
| reCAPTCHA       | Existing conditional rules and messages                | Unchanged                                                                                           |

**Why 191 instead of the guide's earlier 255.** Real MySQL 8.4 boundary tests
returned error 1406 at 255 for company name and company address.
`2026_09_30_000000_add_legacy_columns_to_store_tables` uses `string()` without a
length and `AppServiceProvider` sets `Schema::defaultStringLength(191)`.
`db:table users` confirms both are `varchar(191)`. The registration and
representative request, native `maxlength` attributes and boundary tests now
agree at **191**, without widening the existing columns. This corrects §§47–48's
assumption about these two columns. The representative form's other fields and
requiredness are unchanged; its shipping-address rule is outside this change.

**Why optional.** Self-registration already allowed these three inputs to be
omitted, and this task explicitly requires that flow to keep working. They keep
`(optional)`, no required mark and no native `required`, matching `nullable`.
The representative's complete-customer workflow continues to require its twelve
inputs; this is a workflow distinction, not a change to the resulting customer
account type. Its tax/document rules and the two corrected shared column limits
are reused by self-registration. The form gains multipart encoding, matching
`accept`/`maxlength`, associated labels and the corrected company-address old
input key. File controls never repopulate an old filename. The shipping-address
control is removed; registration still makes its original country/phone-only
default address.

### Evidence and audit

The three new real-endpoint cases were run before the implementation: tax number
read back as `null` instead of `NL123456789B01`, company address as `null` instead
of `Damrak 1, Amsterdam`, and the license's stored name was `null`. Both
addon-enabled rendered-page removal cases also failed first (`1` country-code
control instead of `0`). Tests now cover success with and without the optional
fields, each text boundary and boundary+1 (multibyte included), actual document
sizes 20000/20001 KiB, invalid types/content, external paths, duplicate email,
and unchanged guest rate limits. Refusals compare every database table against
its prior contents; successes allow exactly the new customer and default
address, preserving unrelated rows and files. Rendered HTML pins the optional
marks, encoding, field limits, error redisplay and dropped controls; Vite is
disabled for the Blade tests. Playwright submits both pages through the real
terms agreement, including an actual file upload and omitted-field submissions.
The regression file is in both MySQL lists.

`php artisan forms:audit-required --date=20261026`:

| Class |  Before |   After |
| ----- | ------: | ------: |
| A     |      36 |      36 |
| B     |      14 |      14 |
| C     |      38 |      38 |
| D     |       7 |       7 |
| E     |     105 |      99 |
| F     |      23 |      23 |
| Total | **223** | **217** |

P1 falls **8 → 2**; both registration forms have no findings. Reports:
[Markdown](../required-fields-audit-20261026.md) and
[JSON](../required-fields-audit-20261026.json). The ratchet baseline stays **55**:
it covers back-office A–C findings, and none of those was fixed here. Removing a
still-live entry would fail the ratchet; no entry was added or loosened. The six
removed E findings were never baseline entries. Remaining scanner limitations
retain their documented evidence. D16's registration decision is **FIXED**; no
further owner decision is needed for it.

### Final verification

- Final focused SQLite: **177 passed / 1,062 assertions**. Final MySQL 8.4
  regression file: **42 passed / 281 assertions**, using
  `DB_CONNECTION=mysql DB_DATABASE=warehouse_test DB_USERNAME=root DB_PASSWORD=password`.
- Explicit `python3 .claude/scripts/pre_push_check.py --full`: **exit 0**.
  Wayfinder generation, whole ESLint/Prettier, TypeScript, **338 Vitest files /
  2,710 tests**, Pint, PHPStan (**zero errors**), config clear and parallel Pest
  passed. Pest: **8,013 passed / 44,937 assertions**, plus the **three existing
  MySQL-only skips on SQLite**, in **141.684 seconds**. No test was loosened,
  skipped or quarantined to obtain this result; no Vitest timeout retry occurred.
- The new registration browser spec and existing required-mark spec: **8 passed
  (36.7 seconds)**. Both real signup pages submit through the existing terms
  agreement; the long form stores the uploaded PDF and also works without it.
- Production build, changed-file ESLint/Prettier, standalone frontend/backend
  type checks, dirty Pint, script selftests, **23 CI planner tests**, audit,
  required-fields ratchet and `git diff --check` passed. Blade files have no
  configured Prettier parser and are covered by rendered-page tests.
- Setup corrections: locked `npm ci` restored missing fonts/Tiptap/Leaflet,
  Composer's stale classmap was regenerated without scripts, the ignored stale
  `public/hot` marker was moved aside, and the workspace PHP GD extension was
  rebuilt with WebP. No manifest, lockfile, application configuration or platform
  requirement changed for setup. Browser fixture iterations corrected a missing
  active country, a guarded-model fixture assignment and the existing translated
  account-button capitalization; no assertion timeout or retry was raised.
- Not run locally: the remaining MySQL CI list, the whole application Playwright
  suite, dependency advisory audits or deploy-script tests. The workflow change
  runs all CI groups on the non-draft PR. No bare/attested pre-push run or
  hand-edited `CI-Verified` trailer was used.

A separate inherited limit remains outside D16: the representative shipping
address still has a max-255 rule against its default-length 191 column.
Self-registration no longer asks for that value. No owner decision remains for
the D16 registration fields implemented here.

## 68. Editor keyboard-selection readiness (2026-10-26)

Branch `codex/fix-flaky-editor-test` starts at `origin/develop`
`e44eb09b7d95312d8f283cdcf49de12b423c9a3a`. The missing `#description strong`
failure in run **40240871478**, PR #499 attempt 1, was **not reproduced**:
before changing the editor interaction, the product case passed **30/30 normally**
and **30/30 under CPU pressure** (four CPUs, three competing CPU workers,
Playwright `--repeat-each=30 --workers=1`, existing retries/timeouts).

Static diagnosis found a test-readiness gap: `press()` accepted matching browser
and Tiptap positions even when both described an empty caret. Tiptap 3.31.3's
`setMark` adds a stored mark for an empty selection, which produces no `<strong>`
around existing text. The shared helper now waits for focus and typed Tiptap
content before the selection key, then polls the exact expected text in both
selections and their matching positions within the editor. Physical/digital
product, blog, newsletter/ticket and website callers supply the expected text;
all existing formatting assertions, including **"Cold pressed"**, remain.

No component accessibility defect was demonstrated. `StoreRichTextField` already
copies the DOM selection before Alt+F10/Tab; its existing selection-transfer
Vitest passes (**17/17** overall). Toolbar buttons retain keyed DOM nodes and use
native `type="button"` click activation. The component is unchanged. Both the
Actions artifact redirect and the connector's download reference returned 403 in
this runtime, so the failed CI trace could not be inspected. The CI incident's
precise cause remains unconfirmed; successful repeats provide weaker evidence
than reproducing the failure and observing its disappearance.

Repeated runs also exposed duplicate named upload fixtures. The shared fixture
uploader now removes the earlier named copy through the canonical upload service
and the guarded browser-database seeder; photo and digital-document uploads use
it. This setup correction was present in both valid baseline runs. Initial
attempts were excluded: missing GD WebP support (**3 failed, 1 interrupted,
26 not run**), then duplicate uploads (**1 passed, 12 failed, 1 interrupted,
16 not run**). A two-CPU overload trial produced **2 passed, 1 total-test timeout
on the storefront after formatting, 1 interrupted, 26 not run**; the four-CPU
pressure condition above kept the existing 30-second total timeout feasible.

**Browser verification.** With the selection checks in place, the same
four-CPU/three-worker pressure condition passed **60/60** in two unchanged
`--repeat-each=30 --workers=1` runs (**7.8 minutes each**). All five affected specs
(`store-product-create`, `store-digital-product-forms`, `store-blog-editor`,
`store-newsletter-ticket`, `store-website-settings`) also passed **60/60** with
`--repeat-each=3 --workers=1` (**5.9 minutes**), including the mirrored RTL
toolbars. No assertion, retry setting or timeout was weakened.

**Full gate.** `python3 .claude/scripts/pre_push_check.py --full` exited **0**:
Wayfinder, whole ESLint/Prettier, frontend types, **338 Vitest files / 2,710 tests**,
Pint, PHPStan (**zero errors**), config clear and parallel Pest passed. Pest:
**8,013 passed / 44,937 assertions**, with **three existing MySQL-only skips**
on SQLite (**208.981 seconds**). No isolated Vitest timeout rerun was needed.
Standalone frontend and E2E TypeScript checks, changed-code ESLint/Prettier,
production build and `git diff --check` passed. Whole-file Markdown warnings
reproduce on both base documents; the added sections pass formatting separately.

Runtime repairs stayed outside tracked files: locked npm refresh, autoload and
Wayfinder generation, removal of the stale ignored Vite hot marker, and GD rebuilt
with WebP. Three stale Composer packages (SVG sanitizer, Livewire and YAML) were
corrected to the lock after the baselines; editor JavaScript, browser and CPU
settings stayed the same. The entire application browser suite, separate MySQL
suite, dependency advisory audits and deploy-script tests were not run locally.
No bare/attested pre-push run or hand-edited `CI-Verified` trailer was used.

# Store foundation and integration snapshot — 2026-10-07 to 2026-10-20

**Historical evidence.** Status, approvals and verification apply to the recorded
dates and authorize no new action. Later corrections supersede earlier snapshots.

Use the [active guide](../../rules/store-backoffice-migration.md) for current
constraints, the [section index and provenance](README.md) for numbered references
across dates, and the [reconciliation ledger](../../store-backoffice-reconciliation-20261011.md)
for canonical integration/runtime evidence.

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
  [the reconciliation ledger](../../store-backoffice-reconciliation-20261011.md).

<a id="2-foundation-decisions"></a>

## 2. Foundation decisions

<a id="21-shared-props-are-guard-aware"></a>

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

<a id="22-storeadminlayout-and-the-sidebar"></a>

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

<a id="23-translations"></a>

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

<a id="24-uploads-selected-contract-and-coexistence"></a>

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

<a id="25-content-security-policy"></a>

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

<a id="26-coexistence-under-temp"></a>

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

<a id="27-foundation-verification-historical-read-only-pass-2026-10-07"></a>

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

<a id="3-per-module-checklist-the-staff-pattern"></a>

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


<a id="4-integrated-status-2026-10-20"></a>

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

<a id="41-seller-prerequisite-and-role-grants"></a>

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

<a id="5-conflict-decisions-and-retained-work"></a>

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

<a id="51-actual-retained-backend-blade-files"></a>

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

<a id="6-registered-migrated-controllers-and-consumers"></a>

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

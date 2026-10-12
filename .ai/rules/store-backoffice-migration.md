---
paths:
  - 'resources/views/shop/backend/**'
  - 'resources/js/pages/Store/**'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/lib/storeAdminMenu.ts,resources/js/lib/storeAccess.ts'
  - 'resources/js/components/StoreAdminNavLink.vue,resources/js/components/StoreStaffFormFields.vue,resources/js/components/StoreRoleFormFields.vue'
  - 'app/Http/Controllers/Store/**,app/Http/Requests/Store/**,app/Http/Resources/Store/**'
  - 'app/Http/Middleware/HandleInertiaRequests.php,app/Http/Middleware/HandleStoreBladePages.php,app/Http/Middleware/SendSecureHeaders.php'
  - 'routes/shop-admin.php'
  - 'tests/Feature/Store/**,tests/e2e/store-*.spec.ts'
---

# Moving the store back office from Blade to Inertia

Latest setup-settings follow-up: [general settings verification and the Google Map hold](#17-setup-configuration-settings-follow-up-2026-10-12).
Product-list follow-up: [in-house and all-products list verification](#16-in-house-and-all-products-lists-follow-up-2026-10-12).
Digital-product follow-up: [digital product list verification](#15-digital-product-list-follow-up-2026-10-12).
Brand follow-up: [brand migration verification](#14-brand-administration-follow-up-2026-10-12).
Category follow-up: [category migration verification](#13-category-administration-follow-up-2026-10-12).
Customer-package follow-up: [customer package migration verification](#12-customer-package-administration-follow-up-2026-10-12).
Carrier follow-up: [carrier migration verification](#11-carrier-administration-follow-up-2026-10-12).
PR #377 verification: [final all-store browser results](../store-backoffice-reconciliation-20261011.md#final-all-store-browser-verification-2026-10-12)
close the pending browser gate. Earlier dated verification sections remain
historical evidence; §10 records reconciliation results and §§11–17 record
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
use full page loads, including blog create/edit and ticket details.

**How it differs from the Blade sidebar, on purpose:**

- **Dropped:**
  - the 12 removed-addon blocks: their routes no longer exist;
  - the stray duplicate top-level Shipping group;
  - System → Server status: it is a `Route::view` with no Wayfinder action,
    and its removal still requires an owner decision; the route and template remain.
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
  dashboard itself remains Blade until chart dependency approval is verified.

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
  operational review. Seller/classified feature use, server-status deletion and
  new editor/map/chart dependencies retain their owner prerequisites.

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


## 4. Integrated status (2026-10-12)

This reflects integrated code and the carrier, customer-package, category,
brand, digital-product, in-house/all-products list and general-settings
follow-ups in §§11–17. The original
reconciliation was based on `8e3f82b60b2b4dc951b97ccfed985a75e9e6be69` and
merged through PR #377. Carrier administration was then implemented separately
from latest `origin/develop` at `018dd1802c0b2fc99ffbf0e097594fe0f0f3dfef`.
Verification below distinguishes the original reconciliation from this follow-up.

| Module/subset | State | Actual behavior / remaining work |
| --- | --- | --- |
| Staff and roles | Complete pilot | Existing six Inertia pages retained; action gates unchanged |
| Reference/shipping/tax/currency | Complete selected subset | Canonical `35ae3133` controllers/forms/routes; duplicate `e5523338` implementation superseded |
| Carrier administration | Complete | List/create/edit and CRUD/status use Inertia; existing `manage_carriers` gate, tariff contract and public/API consumers preserved (§11) |
| Language/settings | Complete selected subset | Language CRUD and translation-selector validation separated; dedicated settings endpoints with reserved-key protection; general settings use Inertia with a typed writer and a config-managed timezone (§17); the Google Map settings GET stays Blade because its enabled form loads the Google Maps script (map decision, §17) |
| Reports/notifications | Complete selected subset | Inertia report/admin notification routes; public/customer/seller notification consumers remain |
| Uploads/profile | Complete selected subset | Canonical `944e2e1e` backend/picker; per-action authorization and authenticated-user profile |
| Customers/representatives | Partial | Customers, addresses, representatives/packages/payments, customer package administration (§12) and visit list implemented; classified-product activation remains decision-blocked |
| Catalogue | Partial | Attributes/values, colors, brand discount, reviews, bulk import/export and offers catalogue implemented; category (§13) and brand (§14) administration complete; digital product list, deletion, download and switches converted (§15); in-house and all-products lists with their switches, duplicate, delete and bulk delete converted (§16); product and digital-product create/edit stay Blade for the editor; the seller product list stays Blade with seller administration |
| Website/support | Partial | Header/home, conversations, product queries, ticket list and typed ticket replies integrated; ticket detail uses Blade; footer/appearance/custom pages retain Blade editor |
| Marketing/blog | Partial | Coupons, flash/offer deals, campaign products, popups, subscribers and blog categories/list/actions integrated; blog/newsletter editor forms remain Blade; typed newsletter sending/recipient lookup integrated |
| Logistics | Partial | Drivers/trucks converted; visits use customer Inertia list plus hardened Blade maps; trips/maps retain Blade, with compatible hardening |
| Orders/dashboard | Partial | Orders and entry forms converted; stored tax metadata and line-price-only grand total preserved; shell utilities integrated; dashboard/chart conversion held |
| Seller administration | Decision-blocked | Existing seller Blade screens remain, including the seller product list (`products.seller`, §16); feature-use/removal decision is still required |
| Public/seller/auth/PDF consumers | Retained | Blade coexistence, PDF layouts, Inertia root and CSP middleware remain |

### 4.1 Seller prerequisite and role grants

`1c005f6efdddee958ba8d739afd35eb0935007bd` records the prerequisite review.
Feature flags and checked-in owner-approval claims do not establish seller or
classified feature-use approval. Keep the existing seller area and customer
classified-product controllers/routes/views. Customer package administration is converted in §12; its purchase, payment and mobile API consumers remain separate.

Union permission definitions while retaining per-action gates. Idempotent
definition migrations do not assign permissions to existing roles and do not
delete pre-existing grants on rollback. Production role assignments must be
reviewed explicitly; creating a permission does not authorize staff. The seller
commission activation Blade form retains its `{type,value}` contract through a
dedicated allowlisted compatibility endpoint, not a general settings writer.

## 5. Conflict decisions and retained work

- `StoreBooleanField` has one `name`/`value`/`disabled` API. Adapt callers.
- Dedicated settings requests reject foreign/reserved keys before writing,
  including plain/localized forms. Generic settings cannot write migrated
  reference, header/home, general-settings, script, map or
  environment-controlled keys. The general-settings keys
  (`UpdateGeneralSettingsRequest::KEYS`, including `timezone`) are reserved
  there since §17; the footer and appearance Blade forms keep the generic
  writer and its four-permission gate unchanged.
- The timezone is config-managed: `config('app.timezone')` is `'UTC'` in
  `config/app.php`, and no settings screen stores it. The general settings
  writer validates it against PHP's identifiers (plus the configured value)
  and refuses any other value with a field error; nothing writes `.env`.
- Website scripts retain the existing rendering, appearance permission and WAF
  behavior through the narrowly scoped compatibility endpoint. New script policy,
  `manage_website_scripts` activation and broad rich-text WAF exceptions are held.
- Profile and notifications use selected controllers in the Inertia shell;
  public/seller/customer handlers remain without restoring deleted admin Blade.
- `ResourceFormPage` has one `after-form` slot inside its width wrapper outside
  the Form. Shared types, casts/relations, resources and fixtures are additive.
- English/Arabic translation leaf keys are merged, retaining selected upload
  namespace shapes rather than replacing whole translation objects.
- Preserve bounded translated catalogue product search while selected reference
  forms use its existing route name. Preserve upload URL safety and download/PDF
  behavior. No frontend or Composer dependency manifests/locks change.
- Tiptap, Chart.js and new Leaflet dependencies lack independently verified
  approval. Preserve existing Blade editor/chart/map assets.
- Product list switches share one write path. The digital-product and
  in-house/all-products lists publish and feature through
  `Concerns\UpdatesStoreProductFlags`, scoped to the products each list shows.
  The root `ProductController` jQuery endpoints stay unchanged for the Blade
  seller list (§16).
- Order tax changes are held: the established entry handlers stored raw product
  tax metadata and summed line prices for grand total. Percentage conversion and
  tax/shipping/coupon adjustments need a business decision.
- Preserve departure ID 9 behavior, Seller app link behavior, shared-session
  logout semantics and server status. Missing data is handled without inventing
  new business policy. Shared image-normalization changes remain a policy gap.
- Remove static-analysis/legacy-test exclusions only for migrated or cleaned
  handlers. Root controllers still serving public/Blade consumers remain excluded
  where necessary. Do not remove legacy shells/assets/includes/CSP exceptions
  while consumers remain. `HandleStoreBladePages` and `store.blade_csp` are still
  required for storefront, seller and authentication pages.

### 5.1 Actual retained backend Blade files

The current backend inventory contains **60 Blade files**, down from 73 after
removing three screens each for carriers, customer packages and categories, the
two brand screens, the digital product list (§15) and general settings (§17).
The in-house and all-products lists (§16) removed none:
`product/products/index.blade.php` is shared with the retained seller product
list. The Google Map settings screen stays (§17).
These files remain physically present. Hardening a Blade handler does not count
as conversion. Protected shells/downloads/invoices are not conversion targets.

- **blog_system:** `blog_system/blog/create.blade.php`, `blog_system/blog/edit.blade.php`.
- **customer:** `customer/classified_products/index.blade.php`.
- **(root):** `dashboard.blade.php`.
- **downloads:** `downloads/brand.blade.php`, `downloads/category.blade.php`, `downloads/user.blade.php`.
- **inc:** `inc/admin_nav.blade.php`, `inc/admin_sidenav.blade.php`.
- **invoices:** `invoices/invoice.blade.php`.
- **layouts:** `layouts/app.blade.php`, `layouts/blank.blade.php`, `layouts/layout.blade.php`.
- **marketing:** `marketing/newsletters/index.blade.php`.
- **product:** `product/digital_products/create.blade.php`, `product/digital_products/edit.blade.php`, `product/products/choice_options_script.blade.php`, `product/products/create.blade.php`, `product/products/edit.blade.php`, `product/products/index.blade.php`, `product/products/sku_combinations.blade.php`, `product/products/sku_combinations_edit.blade.php`, `product/reviews/index.blade.php`.
- **representatives:** `representatives/visits/map.blade.php`, `representatives/visits/rep-show.blade.php`, `representatives/visits/show.blade.php`.
- **sellers:** `sellers/create.blade.php`, `sellers/edit.blade.php`, `sellers/index.blade.php`, `sellers/payment.blade.php`, `sellers/payment_histories/index.blade.php`, `sellers/payment_modal.blade.php`, `sellers/profile_modal.blade.php`, `sellers/seller_commission/index.blade.php`, `sellers/seller_verification_form/index.blade.php`, `sellers/seller_withdraw_requests/index.blade.php`, `sellers/seller_withdraw_requests/payment_modal.blade.php`, `sellers/seller_withdraw_requests/withdraw_message_modal.blade.php`, `sellers/verification.blade.php`.
- **setup_configurations:** `setup_configurations/google_configuration/google_map.blade.php`.
- **support:** `support/support_tickets/show.blade.php`.
- **system:** `system/server_status.blade.php`.
- **trips:** `trips/checkpoints/index.blade.php`, `trips/components/checkpoint-template.blade.php`, `trips/components/checkpoint.blade.php`, `trips/components/update-checkpoint-status-modal.blade.php`, `trips/components/update-trip-status-modal.blade.php`, `trips/create-from-map.blade.php`, `trips/create.blade.php`, `trips/edit.blade.php`, `trips/inc/map-popups.blade.php`, `trips/inc/map-scripts.blade.php`, `trips/inc/scripts.blade.php`, `trips/index.blade.php`, `trips/orders.blade.php`.
- **website_settings:** `website_settings/appearance.blade.php`, `website_settings/footer.blade.php`, `website_settings/pages/create.blade.php`, `website_settings/pages/edit.blade.php`, `website_settings/pages/index.blade.php`.

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
| `BlogController` | `blog.index`, `blog.store`, `blog.create`, `blog.update`, `blog.destroy`, `blog.edit`, `blog.change-status` | `Store/Admin/Blog/Posts/Index.vue`; create/edit → retained Blade forms |
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
| `DashboardController` | `cache.clear` | Scoped cache clear only; dashboard route → root `AdminController` / retained Blade |
| `DigitalProductController` (Store/Admin) | `digitalproducts.index`, `digitalproducts.destroy`, `digitalproducts.download`, `digitalproducts.published`, `digitalproducts.featured`, `digitalproducts.todays_deal` | `Store/Admin/Product/DigitalProducts/Index.vue`; download → file response; `digitalproducts.create`/`store`/`edit`/`update` → root `DigitalProductController` retained Blade forms (editor-blocked); seller, mobile and storefront download consumers separate |
| `DriverController` | `drivers.create`, `drivers.index`, `drivers.store`, `drivers.update`, `drivers.destroy`, `drivers.edit` | `Store/Admin/Trips/Drivers/Index.vue`, `Store/Admin/Trips/Drivers/Create.vue`, `Store/Admin/Trips/Drivers/Edit.vue` |
| `DynamicPopupController` | `bulk-dynamic-popup-delete`, `dynamic-popups.index`, `dynamic-popups.store`, `dynamic-popups.update-status`, `dynamic-popups.create`, `dynamic-popups.update`, `dynamic-popups.destroy`, `dynamic-popups.edit` | `Store/Admin/Marketing/DynamicPopups/Index.vue`, `Store/Admin/Marketing/DynamicPopups/Create.vue`, `Store/Admin/Marketing/DynamicPopups/Edit.vue` |
| `FlashDealController` | `flash_deals.index`, `flash_deals.store`, `flash_deals.create`, `flash_deals.update`, `flash_deals.destroy`, `flash_deals.edit`, `flash_deals.update_featured`, `flash_deals.update_status` | `Store/Admin/Marketing/FlashDeals/Index.vue`, `Store/Admin/Marketing/FlashDeals/Create.vue`, `Store/Admin/Marketing/FlashDeals/Edit.vue` |
| `GeneralSettingsController` | `general_setting.index`, `general_setting.update` | `Store/Admin/Setup/GeneralSettings/Index.vue` |
| `GoogleMapController` | `google-map.update` | Redirect to the retained Blade `google-map.index` GET (root `BusinessSettingsController::google_map`; map decision, §17) |
| `LanguageController` | `languages.index`, `languages.store`, `app-translations.export`, `app-translations.import`, `app-translations.store`, `app-translations.show`, `languages.create`, `languages.key_value_store`, `languages.update-status`, `languages.update_rtl_status`, `languages.destroy`, `languages.show`, `languages.edit`, `languages.update` | `Store/Admin/Languages/Index.vue`, `Store/Admin/Languages/Create.vue`, `Store/Admin/Languages/Edit.vue`, `Store/Admin/Languages/Translations.vue` |
| `LegacyWebsiteScriptsController` | `website.scripts.compatibility.update` | JSON / redirects / PDF as applicable |
| `NewsletterController` | `newsletters.index`, `newsletters.recipients`, `newsletters.send` | Retained Blade newsletter editor; typed send and bounded JSON recipient search |
| `NotificationController` | `admin.all-notification` | `Store/Admin/Notifications/Index.vue` |
| `OfferController` | `offer_deals.brands.create`, `offer_deals.create`, `offer_deals.index`, `offer_deals.store`, `offer_deals.update`, `offer_deals.destroy`, `offer_deals.edit`, `offer_deals.update_multiple_usage`, `offer_deals.update_repeatable`, `offer_deals.update_status` | `Store/Admin/Marketing/Offers/Index.vue`, `Store/Admin/Marketing/Offers/Create.vue`, `Store/Admin/Marketing/Offers/Edit.vue` |
| `OffersCatalogController` | `offers.destroy`, `(unnamed compatibility)`, `offers.index`, `offers.store` | `Store/Admin/Catalogue/Offers/Index.vue` |
| `OrderConfigurationController` | `order_configuration.index`, `order_configuration.update`, `order_configuration.representative.update` | `Store/Admin/Setup/OrderConfiguration/Index.vue` |
| `OrderController` | `all_orders.index`, `bulk-order-delete`, `inhouse_orders.index`, `inhouse_orders.show`, `orders.details`, `order.item.edit_price`, `orders.update_delivery_status`, `orders.update_manager_notes`, `orders.update_payment_status`, `orders.update_tracking_code`, `orders.show`, `orders.destroy`, `all_orders.show`, `pick_up_point.index`, `pick_up_point.order_show`, `seller_orders.index`, `seller_orders.show` | `Store/Admin/Sales/Orders/Index.vue`, `Store/Admin/Sales/Orders/Show.vue` |
| `OrderEntryController` | `new.feat-order.trip.create`, `new.feat-order.trip.edit`, `new.feat-order.trip.store`, `new.feat-order.trip.update`, `feat-order.create`, `feat-order.store`, `orders.search-options` | `Store/Admin/Sales/Orders/Create.vue`, `Store/Admin/Sales/Orders/Edit.vue` |
| `PickupPointController` | `pick_up_points.index`, `pick_up_points.store`, `pick_up_points.create`, `pick_up_points.update`, `pick_up_points.destroy`, `pick_up_points.edit` | `Store/Admin/Setup/PickupPoints/Index.vue`, `Store/Admin/Setup/PickupPoints/Edit.vue`, `Store/Admin/Setup/PickupPoints/Create.vue` |
| `ProductController` (Store/Admin) | `products.admin`, `products.all`, `products.list.published`, `products.list.featured`, `products.list.for_sale`, `products.list.duplicate`, `products.list.destroy`, `products.list.bulk_destroy` | `Store/Admin/Product/Products/Index.vue` (both lists, `listType`); `products.seller`, `products.create`/`store`, `products.admin.edit`/`seller.edit`/`update`, the jQuery switches, approval, duplicate, destroy, bulk delete, `products.fetch` (JSON) and the form helpers stay on root `ProductController` for the Blade seller list and editor forms |
| `ProductBulkUploadController` | `bulk_product_upload`, `pdf.download_brand`, `pdf.download_category`, `pdf.download_seller`, `product_bulk_export.index`, `product_bulk_upload.index` | `Store/Admin/Product/BulkUpload/Index.vue` |
| `ProductQueryController` | `product_query.index`, `product_query.show`, `product_query.reply` | `Store/Admin/Support/ProductQueries/Index.vue`, `Store/Admin/Support/ProductQueries/Show.vue` |
| `ProductSearchController` | `order_configuration.product-search`, `products.add-more-choice-option` | JSON / redirects / PDF as applicable |
| `ProfileController` | `profile.index`, `profile.update` | `Store/Admin/Profile/Index.vue` |
| `ReportController` | `commission-log.index`, `in_house_sale_report.index`, `seller_sale_report.index`, `stock_report.index`, `user_search_report.index`, `wallet-history.index`, `wish_report.index` | `Store/Admin/Reports/CommissionHistory.vue`, `Store/Admin/Reports/InHouseSales.vue`, `Store/Admin/Reports/Searches.vue`, `Store/Admin/Reports/SellerSales.vue`, `Store/Admin/Reports/Stock.vue`, `Store/Admin/Reports/WalletHistory.vue`, `Store/Admin/Reports/Wishlist.vue` |
| `RepresentativePackageController` | `rep.packages.create`, `rep.packages.destroy`, `rep.packages.index`, `rep.set-package`, `rep.packages.store`, `rep.packages.update`, `rep.packages.edit` | `Store/Admin/Representatives/Packages/Index.vue`, `Store/Admin/Representatives/Packages/Create.vue`, `Store/Admin/Representatives/Packages/Edit.vue` |
| `RepresentativePaymentController` | `admin.rep.payments.accept`, `admin.rep.payments.export`, `admin.rep.payments.index`, `admin.rep.payments.reject` | `Store/Admin/Representatives/Payments/Index.vue` |
| `ReviewController` | `admin.reviews.index`, `reviews.published` | `Store/Admin/Product/Reviews/Index.vue` |
| `RoleController` | `roles.index`, `roles.store`, `roles.create`, `roles.update`, `roles.destroy`, `roles.edit` | `Store/Admin/Staff/Roles/Index.vue`, `Store/Admin/Staff/Roles/Create.vue`, `Store/Admin/Staff/Roles/Edit.vue` |
| `SettingsController` | `activation.index`, `business_settings.update.activation`, `facebook_chat.index`, `facebook_chat.update`, `facebook-comment`, `facebook-comment.update`, `file_system.index`, `google_analytics.index`, `google_analytics.update`, `facebook_pixel.update`, `google-firebase.index`, `google-firebase.update`, `google_recaptcha.index`, `google_recaptcha.update`, `payment_method.index`, `payment_method.update`, `smtp_settings.index`, `test.smtp`, `social_login.index` | `Store/Admin/Settings/Index.vue` |
| `ShippingConfigurationController` | `shipping_configuration.index`, `shipping_configuration.update` | `Store/Admin/Setup/ShippingConfiguration/Index.vue` |
| `StaffController` | `staffs.index`, `staffs.store`, `staffs.create`, `staffs.update`, `staffs.destroy`, `staffs.edit` | `Store/Admin/Staff/Staffs/Index.vue`, `Store/Admin/Staff/Staffs/Create.vue`, `Store/Admin/Staff/Staffs/Edit.vue` |
| `StateController` | `states.index`, `states.store`, `states.update`, `states.destroy`, `states.edit`, `states.status` | `Store/Admin/Setup/States/Index.vue`, `Store/Admin/Setup/States/Edit.vue` |
| `SubscriberController` | `admin.subscribers.index`, `subscriber.destroy` | `Store/Admin/Marketing/Subscribers/Index.vue` |
| `SupportTicketController` | `support_ticket.admin_index`, `support_ticket.admin_store`, `support_ticket.admin_show` | `Store/Admin/Support/Tickets/Index.vue`; show → retained Blade detail; reply → redirect |
| `TaxController` | `tax.index`, `tax.store`, `tax.update`, `tax.destroy`, `tax.edit`, `taxes.tax-status` | `Store/Admin/Setup/Taxes/Index.vue`, `Store/Admin/Setup/Taxes/Edit.vue` |
| `TruckController` | `trucks.create`, `trucks.index`, `trucks.photos`, `trucks.store`, `trucks.update`, `trucks.edit`, `trucks.qr` | `Store/Admin/Trips/Trucks/Index.vue`, `Store/Admin/Trips/Trucks/Create.vue`, `Store/Admin/Trips/Trucks/Edit.vue` |
| `UploadController` | `all-file`, `bulk-uploaded-files-delete`, `uploaded-files.index`, `uploaded-files.store`, `uploaded-files.create`, `uploaded-files.files`, `uploaded-files.info`, `uploaded-files.destroy` | `Store/Admin/Uploads/Index.vue`, `Store/Admin/Uploads/Create.vue` |
| `VisitController` | `bulk-visit-delete`, `admin.rep.visits.destroy`, `admin.rep.visits.index` | `Store/Admin/Representatives/Visits/Index.vue` |
| `WebsiteHeaderController` | `website.header`, `website.header.update` | `Store/Admin/Website/Header.vue` |
| `WebsiteHomeController` | `website.home.edit`, `website.home.update` | `Store/Admin/Website/Home.vue` |
| `ZoneController` | `zones.index`, `zones.store`, `zones.create`, `zones.update`, `zones.destroy`, `zones.edit` | `Store/Admin/Setup/Zones/Index.vue`, `Store/Admin/Setup/Zones/Edit.vue`, `Store/Admin/Setup/Zones/Create.vue` |

Visit details/day maps are served by retained root `VisitController`; trips and
Google map settings also retain their Blade GET consumers. Root
`BusinessSettingsController` keeps the Google Map GET, the generic settings
writer, the seller commission/verification screens and `env_key_update`, so it
stays in both analysis exclusion lists. Website footer,
appearance and custom pages, seller/classified screens use retained root handlers. Newsletters use a typed
admin controller with their retained Blade editor. Explicit approval and implementation gaps are tracked
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
  request; foreign visits retain the established `0` response. No departure,
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
schema change); the populated retained
dashboard lacks product-translation eager loading; legacy API/seller resource
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
Map settings screen stays Blade. Work is on `ccr-b39fe4ad-n2p9xs`, branched from
`develop` at `e3eb0f5` (the PR #390 merge, after PR #389 at `a14c659`). The
owner authorized this session's PR, its merge into `develop` and `[skip ci]` on
both. Every commit, the PR and its merge commit carry `[skip ci]`, so no PR CI,
no PR or merge SAST and no `develop` deploy ran for them.

**Google Map settings: held, map decision.** The screen's enabled state loads a
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

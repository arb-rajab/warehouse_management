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
    root view, i.e. the view has no `page` data.

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


## 4. Integrated status (2026-10-11)

This records actual implementation, not final combined verification. All work
uses a separate integration worktree based on `origin/develop` at
`8e3f82b60b2b4dc951b97ccfed985a75e9e6be69`. Nothing has been pushed or deployed.

| Module/subset | State | Actual behavior / remaining work |
| --- | --- | --- |
| Staff and roles | Complete pilot | Existing six Inertia pages retained; action gates unchanged |
| Reference/shipping/tax/currency | Complete selected subset | Canonical `35ae3133` controllers/forms/routes; duplicate `e5523338` implementation superseded |
| Language/settings | Complete selected subset | Language CRUD and translation-selector validation separated; dedicated settings endpoints with reserved-key protection |
| Reports/notifications | Complete selected subset | Inertia report/admin notification routes; public/customer/seller notification consumers remain |
| Uploads/profile | Complete selected subset | Canonical `944e2e1e` backend/picker; per-action authorization and authenticated-user profile |
| Customers/representatives | Partial | Customers, addresses, representatives/packages/payments and visit list implemented; customer packages pending; classifieds decision-blocked |
| Catalogue | Partial | Attributes/values, colors, brand discount, reviews, bulk import/export and offers catalogue implemented; core brands/categories/products/digital-product conversion pending |
| Website/support | Partial | Header/home, conversations, product queries, ticket list and typed ticket replies integrated; ticket detail uses Blade; footer/appearance/custom pages retain Blade editor |
| Marketing/blog | Partial | Coupons, flash/offer deals, campaign products, popups, subscribers and blog categories/list/actions integrated; blog/newsletter editor forms remain Blade; typed newsletter sending/recipient lookup integrated |
| Logistics | Partial | Drivers/trucks converted; visits use customer Inertia list plus hardened Blade maps; trips/maps retain Blade, with compatible hardening |
| Orders/dashboard | Partial | Orders and entry forms converted; stored tax metadata and line-price-only grand total preserved; shell utilities integrated; dashboard/chart conversion held |
| Seller administration | Decision-blocked | Existing seller Blade screens remain; feature-use/removal decision is still required |
| Public/seller/auth/PDF consumers | Retained | Blade coexistence, PDF layouts, Inertia root and CSP middleware remain |

### 4.1 Seller prerequisite and role grants

`1c005f6efdddee958ba8d739afd35eb0935007bd` records the prerequisite review.
Feature flags and checked-in owner-approval claims do not establish seller or
classified feature-use approval. Keep the existing seller area and customer
classified-product controllers/routes/views. Customer packages are not converted.

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
  reference, header/home, script, map or environment-controlled keys.
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

These files remain physically present. Hardening a Blade handler does not count
as conversion. Protected shells/downloads/invoices are not conversion targets.

- **blog_system:** `blog_system/blog/create.blade.php`, `blog_system/blog/edit.blade.php`.
- **customer:** `customer/classified_products/index.blade.php`, `customer/customer_packages/create.blade.php`, `customer/customer_packages/edit.blade.php`, `customer/customer_packages/index.blade.php`.
- **(root):** `dashboard.blade.php`.
- **downloads:** `downloads/brand.blade.php`, `downloads/category.blade.php`, `downloads/user.blade.php`.
- **inc:** `inc/admin_nav.blade.php`, `inc/admin_sidenav.blade.php`.
- **invoices:** `invoices/invoice.blade.php`.
- **layouts:** `layouts/app.blade.php`, `layouts/blank.blade.php`, `layouts/layout.blade.php`.
- **marketing:** `marketing/newsletters/index.blade.php`.
- **product:** `product/brands/edit.blade.php`, `product/brands/index.blade.php`, `product/categories/create.blade.php`, `product/categories/edit.blade.php`, `product/categories/index.blade.php`, `product/digital_products/create.blade.php`, `product/digital_products/edit.blade.php`, `product/digital_products/index.blade.php`, `product/products/choice_options_script.blade.php`, `product/products/create.blade.php`, `product/products/edit.blade.php`, `product/products/index.blade.php`, `product/products/sku_combinations.blade.php`, `product/products/sku_combinations_edit.blade.php`, `product/reviews/index.blade.php`.
- **representatives:** `representatives/visits/map.blade.php`, `representatives/visits/rep-show.blade.php`, `representatives/visits/show.blade.php`.
- **sellers:** `sellers/create.blade.php`, `sellers/edit.blade.php`, `sellers/index.blade.php`, `sellers/payment.blade.php`, `sellers/payment_histories/index.blade.php`, `sellers/payment_modal.blade.php`, `sellers/profile_modal.blade.php`, `sellers/seller_commission/index.blade.php`, `sellers/seller_verification_form/index.blade.php`, `sellers/seller_withdraw_requests/index.blade.php`, `sellers/seller_withdraw_requests/payment_modal.blade.php`, `sellers/seller_withdraw_requests/withdraw_message_modal.blade.php`, `sellers/verification.blade.php`.
- **setup_configurations:** `setup_configurations/carriers/create.blade.php`, `setup_configurations/carriers/edit.blade.php`, `setup_configurations/carriers/index.blade.php`, `setup_configurations/general_settings.blade.php`, `setup_configurations/google_configuration/google_map.blade.php`.
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
| `BrandDiscountController` | `brands_wise_product_discount`, `set_product_discount` | `Store/Admin/Product/BrandDiscounts/Index.vue` |
| `CampaignProductController` | `admin.campaign-products.brands`, `admin.campaign-products.index` | JSON / redirects / PDF as applicable |
| `CityController` | `cities.index`, `cities.store`, `cities.update`, `cities.destroy`, `cities.edit`, `cities.status` | `Store/Admin/Setup/Cities/Index.vue`, `Store/Admin/Setup/Cities/Edit.vue` |
| `ColorController` | `colors`, `colors.destroy`, `colors.edit`, `colors.store`, `colors.update` | `Store/Admin/Product/Colors/Index.vue`, `Store/Admin/Product/Colors/Edit.vue` |
| `ConversationController` | `conversations.admin_index`, `conversations.admin_destroy`, `conversations.admin_reply`, `conversations.admin_show` | `Store/Admin/Support/Conversations/Index.vue`, `Store/Admin/Support/Conversations/Show.vue` |
| `CountryController` | `countries.index`, `countries.status` | `Store/Admin/Setup/Countries/Index.vue` |
| `CouponController` | `coupon.index`, `coupon.store`, `coupon.create`, `coupon.product-options`, `coupon.update`, `coupon.destroy`, `coupon.edit` | `Store/Admin/Marketing/Coupons/Index.vue`, `Store/Admin/Marketing/Coupons/Create.vue`, `Store/Admin/Marketing/Coupons/Edit.vue` |
| `CurrencyController` | `currency.index`, `currency.create`, `currency.settings.update`, `currency.store`, `currency.edit`, `currency.update_status`, `your_currency.update` | `Store/Admin/Setup/Currencies/Index.vue` |
| `CustomerController` | `bulk-customer-delete`, `change_password`, `customers.important`, `customers.not_important`, `customers.store`, `customers.whatsapp_contact`, `customers.page-views`, `customers.index`, `customers.archive`, `customers.create`, `download.trade_license`, `customers.login`, `customers.restore`, `customers.sendAgreement`, `customers.verify`, `customers.update`, `customers.destroy`, `customers.change_activity_status`, `customers.edit`, `customers.update_customer_notes`, `update_password`, `customers.ban`, `representatives.index`, `representatives.create`, `representatives.store` | `Store/Admin/Customers/Customers/Index.vue`, `Store/Admin/Customers/Customers/Create.vue`, `Store/Admin/Customers/Customers/Edit.vue`, `Store/Admin/Customers/Customers/Views.vue` |
| `CustomerNotificationController` | `admin.notifications.all`, `admin.notifications.create`, `admin.notifications.send`, `admin.notifications.delete` | `Store/Admin/Notifications/Customers/Index.vue`, `Store/Admin/Notifications/Customers/Create.vue` |
| `DashboardController` | `cache.clear` | Scoped cache clear only; dashboard route → root `AdminController` / retained Blade |
| `DriverController` | `drivers.create`, `drivers.index`, `drivers.store`, `drivers.update`, `drivers.destroy`, `drivers.edit` | `Store/Admin/Trips/Drivers/Index.vue`, `Store/Admin/Trips/Drivers/Create.vue`, `Store/Admin/Trips/Drivers/Edit.vue` |
| `DynamicPopupController` | `bulk-dynamic-popup-delete`, `dynamic-popups.index`, `dynamic-popups.store`, `dynamic-popups.update-status`, `dynamic-popups.create`, `dynamic-popups.update`, `dynamic-popups.destroy`, `dynamic-popups.edit` | `Store/Admin/Marketing/DynamicPopups/Index.vue`, `Store/Admin/Marketing/DynamicPopups/Create.vue`, `Store/Admin/Marketing/DynamicPopups/Edit.vue` |
| `FlashDealController` | `flash_deals.index`, `flash_deals.store`, `flash_deals.create`, `flash_deals.update`, `flash_deals.destroy`, `flash_deals.edit`, `flash_deals.update_featured`, `flash_deals.update_status` | `Store/Admin/Marketing/FlashDeals/Index.vue`, `Store/Admin/Marketing/FlashDeals/Create.vue`, `Store/Admin/Marketing/FlashDeals/Edit.vue` |
| `GoogleMapController` | `google-map.update` | JSON / redirects / PDF as applicable |
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
Google map settings also retain their Blade GET consumers. Website footer,
appearance and custom pages, seller/classified screens use retained root handlers. Newsletters use a typed
admin controller with their retained Blade editor. Explicit approval and implementation gaps are tracked
by source/path in the reconciliation ledger. The unavailable audit commit
`aed3feab5b6edd95fb941ee92a455e058403def5` is excluded and was not recovered or included.

# Branch audit, 2026-11-04

Historical evidence (not current guidance). Records which remote branches ahead
of `develop` were deleted, merged or kept, and why. Filled in by the work on this
PR; `main`, `staging` and `develop` are never touched.

## Step 1: classification (no branch deleted or merged yet)

Measured against `origin/develop` at `d01ae260` after `git fetch origin --prune`
on an unshallowed clone. The earlier shallow clone had no merge base for six
branches and reported 12-64 commits ahead; with full history every branch below
is 1-3 non-merge commits ahead. Content was judged by `git cherry`, patch ids,
`git range-diff`, `git merge-tree` and reading the diffs, then searching
`origin/develop` for the same or superseding behaviour. `main`, `staging`,
`develop` and this PR's branch (`ccr-d4f7d9d5-ux9et2`) are excluded. Every
develop counterpart of a `codex/*` commit cited below reached develop through
PR #377 (the 2026-10-12 reconciliation); the ledger's source table
([store-backoffice-reconciliation-20261011.md](store-backoffice-reconciliation-20261011.md))
records how each branch was included.

| Branch | Tip | PR | Classification | Evidence |
| --- | --- | --- | --- | --- |
| `ccr-0a36e700-97061b` | `35db967581` | none | MERGED (re-applied by hand) | Still-useful parts re-applied on this PR; `getFileBaseURL()`, Trip edits and its exclusions test skipped (see below) |
| `ccr-29ac9920-278u18` | `b36cf15561` | none | SAFE_DELETE | Adds `database/sql/delete_aaaaa_products.sql` then removes it; net diff against its merge base is empty |
| `ccr-34c0aa9d-p4yimk` | `6b04fbd14c` | #519 merged at `afa4d38160` | MERGED (cherry-picked) | `6b04fbd1` cherry-picked onto this PR with `-x`; applied cleanly |
| `ccr-463ef82d-ek2dhm` | `f96fcf8d87` | none | SAFE_DELETE | Earlier uploads-to-Inertia attempt; superseded by `6c21e5a4` (codex uploads/profile, integrated via #377) |
| `claude/legacy-albaraka-holland-setup-2q7c6h` | `7c445f331c` | none | SAFE_DELETE | Vendors the store under `legacy/`; develop chose one integrated app instead and never had `legacy/` |
| `claude/min-pallet-threshold-u6gcmh` | `7757879d4e` | #282 merged at `ef664fe90d` | SAFE_DELETE | Post-merge commit is patch-identical to develop `592c98b6` (#283) |
| `claude/qr-codes-pdf-export-ddwy10` | `7b04bb21c5` | #284 merged at `87d99cd6c5` | SAFE_DELETE | Post-merge commit is patch-identical to develop `f8d9b2ff` |
| `claude/sqlite-mysql-migration-dvupgn` | `1ec47c558b` | none | KEEP_ASK | One-off `db:import-sqlite` command never reached develop; only the owner knows whether the import is still needed |
| `claude/store-backoffice-trip-checkpoints-2ga29g` | `0523efb154` | #421 merged at `314a20cea7` | SAFE_DELETE | Post-merge docs commit is patch-identical to develop `d164cb3d` (#422) |
| `claude/warehouse-test-coverage-gaps-c7afr6` | `3ee5ad96da` | #238 merged at `476a99cef5` | SAFE_DELETE | Post-merge commit is patch-identical to develop `9248e4b5` (#239) |
| `codex/seller-admin-prerequisite-review-20261011` | `1c005f6efd` | none | SAFE_DELETE | Patch-identical to develop `b234db68`; ledger row "Included" |
| `codex/store-backoffice-customers-representatives` | `218f05f156` | none | SAFE_DELETE | Integrated as `99a14828` (#377); held classified part later converted (`Store/Admin/ClassifiedProductController` in develop) |
| `codex/store-backoffice-foundation-verification-20261011` | `67b621635e` | none | SAFE_DELETE | Patch-identical to develop `3ff704ff`; ledger row "Included" |
| `codex/store-backoffice-language-settings` | `465578796f` | none | SAFE_DELETE | Integrated, adapted, as `9b61a389`; store guide section 4 lists languages/settings as migrated |
| `codex/store-backoffice-reference-config-2026-10-11` | `35ae3133e0` | none | SAFE_DELETE | Integrated as canonical `9645e25d` (#377); range-diff differs only in the store guide |
| `codex/store-backoffice-reports-notifications-20261011` | `7970a407ce` | none | SAFE_DELETE | Integrated, adapted, as `b4f7bd62`; guide section 4 lists reports/notifications |
| `codex/store-backoffice-shipping-tax-batch-1` | `e552333810` | none | SAFE_DELETE | Ledger: superseded by `35ae3133`, useful tests adapted; develop has `Store/Admin/CurrencyController`, `StoreBackOfficeShellTest` |
| `codex/store-catalogue-admin-migration` | `143c5693ab` | none | SAFE_DELETE | Integrated subset as `29ada8cd`; core catalogue converted later; guide section 4 lists catalogue |
| `codex/store-logistics-partial-migration-2026-10-11` | `468d235ca1` | none | SAFE_DELETE | Integrated subset as `de582875`; trips/maps later converted (`Store/Admin/TripController`, `TripMapController`) |
| `codex/store-marketing-blog-migration` | `cf5ef305e9` | none | SAFE_DELETE | Integrated as `23c7619f`; ledger: duplicate uploader/editor superseded, blog/newsletter forms done |
| `codex/store-orders-dashboard-2026-10-11` | `7ab019263f` | none | SAFE_DELETE | Integrated as `3bece94c`; Inertia dashboard since converted; held tax formula replaced by owner VAT decision (`18379618`) |
| `codex/store-uploads-profile-migration` | `944e2e1ed5` | none | SAFE_DELETE | Integrated as canonical `6c21e5a4` (#377); guide section 4 lists uploads/profile |
| `codex/store-website-support-migration-20261011` | `e3995898cd` | none | KEEP_ASK | Integrated as `d0809734` except the still-held website scripts feature, whose only copy is this branch |

Dependabot branches (handled in a later step; none of these bumps is in develop):

| Branch | Tip | PR | Status |
| --- | --- | --- | --- |
| `dependabot/composer/dedoc/scramble-0.13.47` | `d59f92dbaa` | #372 open | develop locks 0.13.39; not in develop |
| `dependabot/composer/intervention/image-4.3.4` | `798f97c221` | #374 open | develop locks 2.7.2 (`^2.5`); major bump, not in develop |
| `dependabot/composer/laravel/boost-2.10.2` | `4cebf38982` | #368 open | PR now bumps to 2.10.3; develop locks 2.5.3; not in develop |
| `dependabot/composer/laravel/telescope-5.25.0` | `f06ab12f6e` | #370 open | develop locks 5.22.1; not in develop |
| `dependabot/composer/spatie/laravel-backup-10.3.3` | `bf5355f19b` | #369 open | develop locks 10.3.1; not in develop |
| `dependabot/npm_and_yarn/three-0.186.1` | `8008a3f901` | #375 open | develop locks 0.185.1; not in develop |
| `dependabot/npm_and_yarn/tiptap/extension-table-3.32.0` | `198d65066e` | #523 open | develop pins exactly 3.31.3 (owner-approved Tiptap set); not in develop |
| `dependabot/npm_and_yarn/typescript-7.0.2` | `4c4416d0a2` | #373 open | develop locks 5.9.3; major bump, not in develop |
| `dependabot/npm_and_yarn/vue-tsc-3.3.13` | `68a5b415e8` | #524 open | develop locks 2.2.12; major bump, not in develop |
| `dependabot/npm_and_yarn/vue/test-utils-2.5.1` | `eaac4d90d8` | #371 open | develop locks 2.4.11; not in develop |

### MERGE: `ccr-34c0aa9d-p4yimk`

- Commit `6b04fbd1` "Add a 'Keeping a session cheap' section to the shared agent
  instructions", made 9 minutes after #519 merged.
- Files: `.ai/agent-conventions.md` (+32: new section after "Keeping
  review/survey tasks cheap"), `AGENTS.md` (narrow tasks may read only the
  applicable sections; push after each commit once pushing is authorized).
- Develop has none of the section's text (`rg` for "outlive", "One task per
  session", "Probe once", "Wait once" finds nothing), and neither file changed
  in develop since #519. `git merge-tree` against develop reports no conflicts.
  Conflict difficulty: none. Documentation only.

### KEEP_ASK: `ccr-0a36e700-97061b`

One commit, `35db9675` "Tidy WMS/store seams" (12 files). Piece by piece:

- Still absent from develop: `StoreUser::ADMIN_USER_TYPES` and
  `hasAdminAccess()`, used by `IsAdmin` and the `isAdmin()` helper (develop
  still repeats `'admin', 'staff', 'accountant'` in `IsAdmin`,
  `IsRepresentativeOrAdmin` and `isAdmin()`), with `StoreAdminAccessTest`;
  removing `app/Providers/AppServiceProvider.php` from the Pint and PHPStan
  exclusion lists (develop still excludes it); `translate()` instead of `__()`
  for `Barcodes` in `shop/frontend/product_details.blade.php` and the default
  checkpoint note (now in `Concerns/WritesTripCheckpoints.php`), with
  `ShopTextTranslationTest`.
- Moot or superseded: the `getFileBaseURL()` fix (develop has no caller left:
  the Blade layouts with the `file-base-url` meta tag are gone); the
  `create-from-map.blade.php` edits and `Trip/TripController.php` (both deleted
  in develop); its `StaticAnalysisExclusionsTest` (develop has its own, stricter
  version).
- Conflicts: `git merge-tree` reports 4 (two modify/delete, `pint.json`, add/add
  on `StaticAnalysisExclusionsTest.php`). Medium effort; the useful parts are
  better re-applied by hand than cherry-picked, and un-excluding
  `AppServiceProvider` needs PHPStan and Pint to pass on its current content.
- Question: re-apply the remaining refactors as a new change, or drop them?

#### Outcome

The unique work of this branch and of `ccr-34c0aa9d-p4yimk` is now on this PR; neither branch is deleted yet.

- Cherry-picked `6b04fbd1` onto this PR (`git cherry-pick -x`); the new section's
  anchor and the `AGENTS.md` link resolve, nothing duplicates existing wording.
- Re-applied by hand against current develop (`35db9675` itself was not picked):
  - `StoreUser::ADMIN_USER_TYPES` and `hasAdminAccess()`, used by `IsAdmin`,
    `IsRepresentativeOrAdmin`, `isAdmin()`, `HandleInertiaRequests::storeNotifications()`
    and `CreateStoreAdminCommand::promote()`. The commit knew of two literals;
    develop has five, so all five now read the list. `isAdmin()` returns false
    for anything but a `StoreUser`, as in the commit (a warehouse `User` has no
    `user_type`). Test: `tests/Feature/StoreAdminAccessTest.php`.
  - `app/Providers/AppServiceProvider.php` removed from `pint.json` `notPath` and
    `phpstan.neon` `excludePaths` (the existing `StaticAnalysisExclusionsTest`
    keeps the two lists equal). Pint reports it clean unchanged; PHPStan was
    not run in this step, the file was read against level 7 and needed no edit.
    The commit's other change, dropping `RateLimitMiddleware.php` from `notPath`,
    is already done in develop.
  - Shop text: the default checkpoint note in `Concerns/WritesTripCheckpoints.php`
    briefly used `translate()`; the final verification run failed
    `LocalizationBoundaryTest` on it (static file text must never call
    `translate()`, `.ai/localization-migration.md`), so it is back to develop's
    `__()`, and the test in `Store/Admin/TripControllerTest.php` now asserts the
    note and that no database translation row is registered.
    The `Barcodes` heading in `product_details.blade.php` uses the existing
    `storefront.product.barcodes` key instead of `translate()`: develop converted
    the rest of that file to `lang/*/storefront.php` keys, the key exists in `en`
    and `ar`, and the commit's rule reference (legacy guide section 5) is now the
    asset pipeline. Asserted in `StorefrontProductDetailsFieldsTest.php`.
- Skipped: `getFileBaseURL()` fix (no caller), `create-from-map.blade.php` and
  `Trip/TripController.php` edits (files deleted), the commit's
  `StaticAnalysisExclusionsTest` (develop's own is stricter), and the commit's
  `ShopTextTranslationTest` and `LegacyAssetHelpersTest` (their subjects are gone
  or replaced by the assertions above).

### KEEP_ASK: `claude/sqlite-mysql-migration-dvupgn`

- Commit `1ec47c55` adds `app/Console/Commands/ImportSqliteDataCommand.php`
  (`db:import-sqlite {path}`) and `tests/Feature/Console/ImportSqliteDataCommandTest.php`
  (390 lines, no conflicts: new files only).
- Purpose: a one-off copy of a VPS SQLite backup of the warehouse tables into a
  fresh MySQL database. Nothing in develop, the README or `.ai/` mentions it or
  records whether that import happened, and develop has no other SQLite import
  command.
- Stale: its table list predates the store integration (it copies `products`
  and `uploads`, which are now shared with the store), so it would need review
  against the current schema before any use.
- Question: has the SQLite-to-MySQL data import been done (or is it no longer
  needed)? If so, SAFE_DELETE; if not, it needs updating before merge.

### KEEP_ASK: `codex/store-website-support-migration-20261011`

- Integrated as `d0809734` (#377) and later follow-ups; the store guide lists
  website/support as migrated.
- Deliberately not integrated, and present only on this branch:
  `app/Http/Controllers/Store/Admin/WebsiteScriptsController.php`,
  `app/Http/Requests/Store/Admin/UpdateWebsiteScriptsRequest.php`,
  `database/migrations/2026_10_10_000001_add_manage_website_scripts_permission.php`,
  `resources/js/components/StoreWebsiteScriptsForm.vue` and their tests.
  The ledger's deferred-changes table marks this "Still held" and go-live
  checklist R8 still awaits the owner's accept/change.
- Question: keep the branch (or an archive tag) until R8 is decided, or drop
  the held implementation?

### Note for the deletion step

The reconciliation ledger's source table cites the codex branch tips by full
SHA and says the deferred changes "remain available in Git". Once a branch is
deleted those commits are no longer reachable from any ref. If those citations
must stay resolvable, push archive tags for the codex tips before deleting.

## Dependabot minor bumps

Re-done on this branch (not cherry-picked: the dependabot lockfile hunks
conflict with develop), one commit per bump.

| # | Bump | Dependabot PR | Commit | Status |
| --- | --- | --- | --- | --- |
| 1 | dedoc/scramble 0.13.39 -> 0.13.47 | #372 | `87fd47e2` | done |
| 2 | laravel/telescope 5.22.1 -> 5.25.0 | #370 | `f62fa652` | done |
| 3 | spatie/laravel-backup 10.3.1 -> 10.3.3 | #369 | `6f858cdb` | done |
| 4 | laravel/boost 2.5.3 -> 2.10.3 | #368 | `ce566f9a` | done |
| 5 | three 0.185.1 -> 0.186.1 (+ @types/three 0.185.4 -> 0.186.0) | #375 | `64b92a4b` | done |
| 6 | @vue/test-utils 2.4.11 -> 2.5.1 | #371 | `d4d605b3` | done |

- Composer bumps (1-4): re-done lock-only with `composer update <pkg>:<version>
  --with-dependencies --no-install --no-scripts --no-plugins`. The sub-agent was
  first blocked by `.claude/hooks/install-retry-guard.sh`; with the owner's
  approval the guard now exempts `--no-install` (commit `0160afb6`). Transitive
  moves are patch-level except boost, which pulls `laravel/mcp` 0.9.2 -> 1.1.2
  (major): the final verification must exercise the Boost/MCP tooling.
- three: `@types/three` is not touched by the dependabot PR but follows three's
  minor (types for 0.185 against 0.186 can drift), so it moved with it; its only
  transitive change is fflate 0.8.2 -> 0.8.3. three is imported by
  `resources/js/warehouse-map/scene3d/**`; three releases routinely deprecate or
  remove APIs between minors, so rely on the vitest/types/build run (not run
  here) to confirm.
- @vue/test-utils: 2.5.1 depends on js-beautify ^2, which moves its transitive
  tree (glob 13, nopt 10, editorconfig 3, ...). Some of those require Node
  `^22.22.2 || ^24.15.0 || >=26`; CI uses Node 22 (resolves to the latest 22.x),
  and the sandbox runs 22.23.4. Affects tests only (283 files use test-utils).

## Dependabot major/tooling bumps

Re-done on this branch, one commit per bump, so each can be reverted alone.
Not verified here: vitest, vue-tsc, the build and e2e run once at the end.

| # | Bump | Dependabot PR | Commit | Status |
| --- | --- | --- | --- | --- |
| 1 | vue-tsc 2.2.12 -> 3.3.13 | #524 | `0f4da2b9`, reverted | REVERTED: `npm run types:check` fails on valid templates (vue-tsc 3.3.13 bug, see below); PR #524 stays open |
| 2 | typescript 5.9.3 -> 7.0.2 | #373 | none | SKIPPED: typescript-eslint and vue-tsc do not support TypeScript 7 |
| 3 | @tiptap/* 3.31.3 -> 3.32.0 (all eight, not only extension-table) | #523 | `a9cb2047` | done |
| 4 | intervention/image 2.7.2 -> 4.3.4 (+ intervention/gif 5.0.1) | #374 | `0e63ca81` (lock), port in the next commit | done, tests not run here |

- vue-tsc, final verification: REVERTED. With 3.3.13, `npm run types:check`
  reported 16 errors, all in `StoreWebsiteFooterFormFields.vue` (TS2304 "Cannot
  find name 'key'" and TS7053), on the `FormField v-for="key in appLinks"` and
  `socialLinks` lists; develop's 2.2.12 reports none on the same file. Minimal
  probes isolated it to `@vue/language-core` 3.3.13: inside a `v-for` on a
  component, any attribute value containing `//` (`placeholder="https://"`,
  `title="https://"`, even `:placeholder="'https://'"`) makes the loop variable
  unresolved; a plain `<input>` or a non-`v-for` component is fine, and moving
  the literal into a script constant avoids it. The template is valid, so the
  bump was reverted rather than bending the code around the tool. 3.3.14
  (2026-10-29) was not tried: it is under two weeks old. Re-check on a later
  release with `npm run types:check`.
- vue-tsc: 3.3.13 peers on `typescript >=5.0.0` and `@vue/language-core`
  3.3.13 needs `@vue/compiler-dom ^3.5.0`, so typescript 5.9.3 and vue 3.5.x
  stay. The lock delta equals the dependabot branch (the vue-tsc tree only:
  `@volar/*` 2.4.28, `@vue/language-core` 3.3.13, `alien-signals` 3.2.1,
  `vscode-uri` 3.2.0; the Vue 2 compiler is dropped). 3.3.14 (2026-10-29) is
  under two weeks old. `tsconfig.json` has no `vueCompilerOptions`, so nothing
  needed adapting; vue-tsc 3 checks templates more strictly, so the final
  `npm run types:check` may report new errors.
- typescript 7.0.2: not applied, PR #373 stays open. TypeScript 7 ships the Go
  compiler behind a launcher: `require('typescript')` exports only `version`
  and `versionMajorMinor`, and `lib/tsc.js` starts the native binary.
  Blockers:
  - `typescript-eslint` (and every `@typescript-eslint/*` package) 8.66.0, and
    the latest 8.72.1, peer on `typescript >=4.8.4 <6.1.0`;
    `npm install typescript@7.0.2 --dry-run` reports the ERESOLVE conflict.
    The current canary (8.72.2-alpha.11) has the same range.
  - vue-tsc 3.3.13 patches the JavaScript source of `typescript/lib/tsc`, which
    TypeScript 7 no longer ships; its only escape hatch is aliasing
    `typescript` to `@typescript/typescript6`. `@volar/typescript` 2.4.30 (the
    newest) declares `typescript <7`.
  - `@vue/eslint-config-typescript` (`>=4.8.4`) and `ts-api-utils`
    (`>=4.8.4`) accept 7 on paper but use the JavaScript API. Nothing else in
    `package.json` peers on typescript.
  The newest version every consumer accepts is TypeScript 6.0.x (6.0.3,
  2026-04-16); that is a separate decision, not done here.
- Tiptap: develop pins the whole set to exactly 3.31.3 (`6d67230a`, owner
  approved, kept in lockstep because each package peers on the exact same
  `@tiptap/core`/`@tiptap/pm`). `@tiptap/extension-table` 3.32.0 peers on core
  and pm exactly 3.32.0, so the dependabot PR's table-only bump breaks the
  peers. All eight direct packages exist at 3.32.0 (2026-10-13, over two
  weeks old), so they moved together, still exact (`--save-exact
  --before=2026-10-21`). npm's in-place upgrade hit ERESOLVE on the exact
  peer set, so the set was uninstalled and reinstalled; no `--force` or
  `--legacy-peer-deps`. All 32 `@tiptap` lock entries are 3.32.0; the only
  other move is `prosemirror-history` 1.5.0 -> 1.5.1, required by
  `@tiptap/pm` 3.32.0. Since 3.31.4 table cells no longer render the default
  `colspan="1"`/`rowspan="1"`, so `StoreRichTextField.test.ts` and
  `store-blog-editor.spec.ts` now expect plain `<td>`/`<th>`. 3.31.4 also
  validates inline `style` values (`isValidCSSStyleValue`); the existing
  colour, alignment, indent and image-width assertions should still match,
  but only the final run confirms it. PR #523 can be closed once this lands.
- intervention/image 4.3.4: `composer update intervention/image:4.3.4
  --with-dependencies --no-install --no-scripts --no-plugins`; the lock delta
  equals the dependabot branch (only `intervention/gif` 5.0.1 is new; v4 needs
  PHP ^8.3 and ext-mbstring, GD stays the driver, ext-exif is optional). The
  API was read from the 4.3.4 tag (`b70df03b`), not guessed. v4 has no
  `read()` or `toJpeg()`; the mapping used:
  - `ImageManagerStatic::make()` -> `ImageManager::decodeBinary()` (optimizer)
    and `decodePath()` (thumbnails), from one `ImageManager` singleton bound in
    `AppServiceProvider::register()` and constructor-injected into both
    services. It uses the GD `Driver` with `autoOrientation: false` (the
    optimizer keeps its own EXIF parser, which works without ext-exif; v4's
    default would rotate twice) and `decodeAnimation: false` (a GIF decodes
    to its first frame through `imagecreatefromstring`, as in v2).
  - `resize($w, $h, aspectRatio + upsize)` -> `scaleDown($w, $h)`, and
    `resize($w, null, ...)` -> `scaleDown(width: $w)`; both round the short
    side with `round()`, as v2 did. The shrink loop's plain `resize()` is
    unchanged.
  - `rotate()`: v2 turned anticlockwise, v4 turns clockwise, so orientations
    5-8 swap 90/270. `flip()` still mirrors left to right.
  - `encode($format, $q)` -> `encodeUsingFileExtension($format, quality: $q)
    ->toString()`; v4 drops `quality` for PNG/GIF, which v2 ignored too. JPEG
    stays baseline (not progressive), PNG uses zlib level -1, WebP the same
    quality. `destroy()` is gone (GD images are freed with the object;
    `imagedestroy()` is deprecated on PHP 8.5).
  - Removed: the `Image` facade alias in `config/app.php` (the class no longer
    exists; nothing used it) and `config/image.php` (only v2's Laravel
    provider read it), plus its `pint.json`/`phpstan.neon` exclusions.
  Behaviour notes: sizes, formats, qualities, the input/pixel/output guards,
  animation refusal and the `InvalidArgumentException`/`false` returns on
  unreadable input are unchanged. One difference: v4's JPEG encoder blends
  transparency onto white, where v2 wrote the hidden colour of transparent
  pixels (usually black). It only shows when a transparent PNG/WebP/GIF is
  stored as JPEG (warehouse reference images, thumbnails on a host without
  WebP). New tests: requested-format conversion with aspect ratio and
  rounding, all eight EXIF orientations, thumbnail height/mime, and a narrow
  heavy upload that must not be enlarged. Residual risk: GD pixel output can
  differ slightly, since v4 resamples with alpha blending on (v2 had it off)
  and copies onto a new canvas before encoding, so semi-transparent edges may
  move by a few levels; the existing test only covers full transparency.
  `vendor/` in this container still holds 2.7.2, so the final run needs a
  `composer install` first. PR #374 can be closed once this lands.

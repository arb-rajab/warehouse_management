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
    now uses `translate()` (test added to `Store/Admin/TripControllerTest.php`).
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
| 1 | dedoc/scramble 0.13.39 -> 0.13.47 | #372 | not done | blocked, see below |
| 2 | laravel/telescope 5.22.1 -> 5.25.0 | #370 | not done | blocked, see below |
| 3 | spatie/laravel-backup 10.3.1 -> 10.3.3 | #369 | not done | blocked, see below |
| 4 | laravel/boost 2.5.3 -> 2.10.3 | #368 | not done | blocked, see below |
| 5 | three 0.185.1 -> 0.186.1 (+ @types/three 0.185.4 -> 0.186.0) | #375 | `64b92a4b` | done |
| 6 | @vue/test-utils 2.4.11 -> 2.5.1 | #371 | `d4d605b3` | done |

- Composer bumps (1-4): the PreToolUse guard `.claude/hooks/install-retry-guard.sh`
  denies any `composer update` that is not `--no-dev` in this remote sandbox, so
  `composer.lock` was not touched and nothing was edited by hand. The dependabot
  branches change only `composer.lock` (the existing `composer.json` ranges
  already allow the new versions), so each is a lock-only update, e.g.
  `composer update <pkg> --with-dependencies --no-install --no-scripts --no-plugins`
  from an environment where that is permitted. For boost, restore any
  `AGENTS.md`, `CLAUDE.md` or `.ai/` change afterwards.
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

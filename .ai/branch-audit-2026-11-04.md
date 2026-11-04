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
| `ccr-0a36e700-97061b` | `35db967581` | none | KEEP_ASK | Small refactors still absent from develop, others moot; cherry-pick conflicts in 4 files (see below) |
| `ccr-29ac9920-278u18` | `b36cf15561` | none | SAFE_DELETE | Adds `database/sql/delete_aaaaa_products.sql` then removes it; net diff against its merge base is empty |
| `ccr-34c0aa9d-p4yimk` | `6b04fbd14c` | #519 merged at `afa4d38160` | MERGE | Post-merge commit adds "Keeping a session cheap"; not in develop, merges cleanly |
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

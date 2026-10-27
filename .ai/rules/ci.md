---
paths:
  - '.github/**,.claude/scripts/pre_push_check.py,.claude/scripts/install_playwright.py,.claude/hooks/session-start.sh'
---

# CI check selection and attested pre-push verification

## Sandbox browser setup is idempotent and shared across agents

After frontend dependencies are ready, the existing SessionStart hook calls
`python3 .claude/scripts/install_playwright.py`. On Linux it checks an explicit
browser path, `/opt/pw-browsers/chromium`, system browsers, and Playwright's cache;
it downloads Chromium only if none is installed. Cached FFmpeg is reused, a system
encoder is linked into the matching cache, and only a missing encoder is downloaded.
The default cache and shell exports (`node_modules/.playwright-runtime.env`) stay
under ignored node_modules. Explicit browser/cache settings and inherited proxy/CA
settings are preserved. A failed or blocked download is reported as unavailable
in the startup summary and log; it must never be reported as ready.

The pre-push E2E runner uses the helper's read-only discovery instead of assuming
the `/opt` path exists. For direct runs, source the generated environment file
before `npm run test:e2e`. Codex does not execute Claude hooks automatically:
AGENTS.md requires invoking this standalone helper before browser tests after
dependencies are ready. Do not force `CLAUDE_CODE_REMOTE` or run the full hook to
prepare a Codex session. The idempotent checks/install paths and hook wiring are
covered by `.claude/scripts/selftest.py`.

## Where each check runs

| Check | Sandbox (`pre_push_check.py`, default scoped mode) | CI (`tests.yml`) |
| --- | --- | --- |
| ESLint, Prettier | changed files only | whole repo, when `js` is planned |
| vue-tsc | whole project, when `js` applies | when `js` is planned |
| Vitest | tests related to changed `resources/js` files | full suite, when `js` is planned |
| Pint | changed PHP files (needs the dev tools) | whole repo, when `php` is planned |
| PHPStan, Pest | full, `--parallel` (needs the dev tools) | full, `--parallel`, when `php` is planned |
| Pest on MySQL (only for schema-related paths), Playwright, dependency audit, deploy-script test | only in the attested run (the default on a clean tree) | when planned, unless attested |

The `Plan CI jobs` gate requires a complete local attestation for ordinary code
PRs before expensive jobs can start. `pre_push_check.py --full` runs the
whole-repo sequence but does not satisfy that gate. In the
Claude web sandbox, dev dists are 403, so Pint, Pest and PHPStan come from
`install_dev_tools.py` (shallow git fetches). Repair unavailable tools before
pushing an ordinary code PR; missing groups fail the attestation gate.

## `--attest` lets CI skip what already passed here

`pre_push_check.py` attests **by default** when its preconditions hold (clean work
tree, no hidden skip-worktree files, unpushed head commit, a change outside
`.github/` that some CI group reads); otherwise it runs the quick scoped checks
and prints why. `--quick` forces the scoped checks and `--attest` forces
attesting. The auto-selection is `attest_blocker` in the script.

`pre_push_check.py --attest` runs every group the planner says the branch needs,
in full, including Playwright (preinstalled Chromium), the migration tests on
the local MySQL that `install_mysql.sh` provides, the composer and npm audits
and the deploy-script test. When they pass it amends the *unpushed* head commit
with `CI-Verified: tree=<head tree> groups=<passed groups>`. The `plan` job
reads that trailer from the PR head commit and skips exactly those groups, but
only when:

- the PR's merged tree equals its head tree (the base adds nothing), and the
  trailer's tree equals that head tree, so any later content change voids it;
- no path is under `.github/`, so a workflow change still runs everything.

The groups run as **parallel lanes** (about 10 minutes, against about 25 one after
another). `attest_prerequisites` runs first, alone: Wayfinder generation, the Vite
build and `config:clear`, which write into the tree or cache every lane reads. Then
`run_lanes` runs js, php, mysql, e2e, audit and deploy-script together, one log per
lane (printed when the lane ends), and the first failure stops the other lanes and
everything they started. Constraints this imposes:

- Playwright's output directory is outside the work tree (`--output=<tmp>`):
  Playwright empties `test-results/` when it starts, and Pint walking the repo in
  another lane dies on the vanished directory.
- Stopping a lane kills its whole process tree, not just the process group: the
  Playwright web server (`node` plus `php artisan serve` on 8010/8011) runs in its
  own session and would otherwise keep the port, failing the next run.
- E2E keeps normal CPU priority and the other lanes run under `nice`: its 5s
  expectations are timing-sensitive and the machine has 4 cores that Pest, Vitest
  and the browser all want. Do not add Playwright workers (measured: flaky).
- A new group or a step that writes into the work tree during the lanes needs the
  same thought: it must not race a lane that scans or reads that path.
  `--serial` is the fallback for a machine with too few cores.

Constraints that keep it honest:

- It refuses unless the work tree is clean and nothing is skip-worktree, so what
  ran is what the commit holds. A group that cannot run here is left out of the
  trailer; ordinary code PRs reject that partial coverage. A failing check
  records nothing.
- It is self-attested: CI cannot prove the local run happened, and the sandbox
  differs from CI (local MySQL 8.0 against `mysql:8.4`). Do not amend a trailer
  in by hand.
- Jobs that `needs` Pint or Prettier must accept a skipped result, or an
  attested php/js group would silently skip Pest, MySQL and E2E too.
- A run that skipped on a trailer still concludes `success`, so `deploy.yml`'s
  `verify-tested` counts that tree as tested.
- Add a CI-run group to `ATTEST_ORDER` in `pre_push_check.py` when you add one to
  `ci_plan.py`; the selftest compares them.

## The `plan` job decides what a pull request runs

`.github/scripts/ci_plan.py` is the single classifier for both callers; change
its rules there, with a case in `ci_plan_test.py` (the `plan` job runs that test
before planning). Its docstring is the rule list. The constraints behind it:

- Pest must run for every non-docs change, including frontend-only ones:
  `config/inertia.php` sets `ensure_pages_exist`, and
  `tests/Feature/Store/Admin/SettingsReconciliationTest.php` scans every file
  under `resources/js`. A "frontend-only skips Pest" rule would be wrong.
- `tests/Fixtures/` reaches the browser (`playwright.config.ts` seeds from it),
  so it is not a PHP-test-only path.
- The MySQL job runs only the migration/schema tests, so it has its own `mysql`
  group (`database/`, `tests/Feature/Database/`, `config/database.php`,
  composer manifests) instead of riding on `php`.
- Billing rounds every job up to a whole minute, and sharding repeats setup per
  shard, so it costs more minutes than it saves: Playwright stays one job and
  Vitest runs inside `js-checks` (Wayfinder types, ESLint, vue-tsc, Vitest in
  one job, each step reporting even if an earlier one failed). Prettier and Pint
  stay separate cheap gates ahead of Pest and Playwright. Prefer more workers on
  one runner to more jobs.
- Any `.github/` change runs everything, so a workflow change is exercised by
  the run that introduces it.
- `Plan CI jobs` requires `--require-attestation` for PRs: the head's trailer
  must cover every planned group on the exact head and merged tree. Missing,
  malformed, stale or partial attestations fail before any expensive job starts.
  This also applies to release PRs; a previous successful run does not replace
  the required trailer. Update the branch and attest again if the base adds
  content. Require `Plan CI jobs` in branch protection; repository code alone
  cannot configure that merge requirement.
- Docs-only PRs need no trailer. Changes under `.github/` ignore it and run
  everything. `workflow_dispatch` and deployment workflow calls run everything
  without requiring a trailer, retaining a way to validate merged content.
- PR API failures, an empty file list and the API's 3000-file limit fail closed
  instead of falling back to paid CI. The three jobs using `!cancelled()` also
  require a successful plan. Skipping uses job-level `if:`, never narrower `on:`
  filters. Do not rename jobs: branch protection may require their check names.

## Permissions under `workflow_call`

`deploy.yml` calls `tests.yml` to test merged content no PR run covered. A
called workflow's jobs cannot hold more permissions than the calling job
grants, so `test-merged-content` must grant everything any `tests.yml` job
declares (currently `contents`, `actions` and `pull-requests`, all `read`). Add
to both when a job in `tests.yml` needs a new permission.

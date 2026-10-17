---
paths:
  - '.github/**,.claude/scripts/pre_push_check.py'
---

# CI

## A green local report gates PR publication

Run `python3 .claude/scripts/pre_push_check.py --for-pr` on the committed tree
before pushing or creating, reopening, or updating a PR. This runs every planned
CI group in full without amending the commit, and records the result, commit,
tree, and completed groups in the git directory's `codex-pre-push-report.json`.
Only an exit-zero GREEN report matching the current commit and tree permits
publication. Failures in unchanged code, missing tools, skipped groups, failed
setup, and changes during verification are blockers, not exceptions. A scoped
run or partial attestation is not a PR gate. CI verifies the result again.
After successful browser tests, the gate removes only untracked files under
`public/uploads/all` owned by the dedicated `database/e2e.sqlite` upload table.
Tracked assets, source files, and unrelated uploads are never removed. Any
remaining worktree changes invalidate the report.

## Where each check runs

| Check | Sandbox (`pre_push_check.py`, default scoped mode) | CI (`tests.yml`) |
| --- | --- | --- |
| ESLint, Prettier | changed files only | whole repo, when `js` is planned |
| vue-tsc | whole project, when `js` applies | when `js` is planned |
| Vitest | tests related to changed `resources/js` files | full suite, when `js` is planned |
| Pint | changed PHP files (needs the dev tools) | whole repo, when `php` is planned |
| PHPStan, Pest | full, `--parallel` (needs the dev tools) | full, `--parallel`, when `php` is planned |
| Pest on MySQL (only for schema-related paths), Playwright, dependency audit, deploy-script test | in `--for-pr` and the attested run | when planned, unless attested |

CI is the gate; the sandbox run exists to catch the common failure before it
costs a CI round (or, with `--attest`, to replace the run). `pre_push_check.py --full` runs the whole-repo sequence. In the
Claude web sandbox, dev dists are 403, so Pint, Pest and PHPStan come from
`install_dev_tools.py` (shallow git fetches); when that install fails, they come
from CI only.

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
  trailer and CI runs it; a failing check records nothing.
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
- A release PR whose merged tree equals a head the suite already passed on
  skips every job. That mirrors `deploy.yml`'s `verify-tested`, and both rely
  on a skipped-everything run concluding `success`. Use the workflow's
  `workflow_dispatch` to force a full run.
- Every lookup fails open (an API error runs everything), and skipping is done
  with job-level `if:`, never by narrowing `on:`, so required checks still
  report a conclusion. Do not rename jobs: their names are the check names
  branch protection may require.

## Permissions under `workflow_call`

`deploy.yml` calls `tests.yml` to test merged content no PR run covered. A
called workflow's jobs cannot hold more permissions than the calling job
grants, so `test-merged-content` must grant everything any `tests.yml` job
declares (currently `contents`, `actions` and `pull-requests`, all `read`). Add
to both when a job in `tests.yml` needs a new permission.

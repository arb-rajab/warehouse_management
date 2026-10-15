---
paths:
  - '.github/**,.claude/scripts/pre_push_check.py'
---

# CI

## Where each check runs

| Check | Sandbox (`pre_push_check.py`, default scoped mode) | CI (`tests.yml`) |
| --- | --- | --- |
| ESLint, Prettier | changed files only | whole repo, when `js` is planned |
| vue-tsc | whole project, when `js` applies | when `js` is planned |
| Vitest | tests related to changed `resources/js` files | full suite, when `js` is planned |
| Pint | changed PHP files (needs the dev tools) | whole repo, when `php` is planned |
| PHPStan, Pest | full, `--parallel` (needs the dev tools) | full, `--parallel`, when `php` is planned |
| Pest on MySQL (only for schema-related paths), Playwright, dependency audit, deploy-script test | never | when planned |

CI is the gate; the sandbox run exists to catch the common failure before it
costs a CI round. `pre_push_check.py --full` runs the whole-repo sequence. The
Claude web sandbox cannot install Pint, Pest or PHPStan (dev dists are 403), so
there those come from CI only.

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

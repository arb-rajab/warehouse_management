"""Run the CI checks a branch's changes can affect, as the one command before a push.

By default the checks are scoped to the files changed since the branch left
its base (`origin/develop`, or `--base <ref>`), committed or not, classified by
the same `.github/scripts/ci_plan.py` the `plan` job in
`.github/workflows/tests.yml` uses, so the sandbox and CI agree on what a
change touches:

  - Only agent/docs files (.ai/, .claude/, root *.md): nothing runs.
  - JS group:  ESLint and Prettier on the changed files they cover; vue-tsc over
               the whole project (it has no per-file mode); Vitest on the tests
               related to the changed resources/js files.
  - PHP group: Pint on the changed PHP files; PHPStan; Pest (`--parallel`).

CI stays the full gate: it runs every check in the planned groups over the
whole repository, and the E2E, MySQL and audit jobs only run there. Scoping
here trades a rare CI round (an unchanged file broken by a changed one, which
ESLint/Prettier/Vitest cannot see from the changed files alone) for not
re-running whole-repo suites on every push.

`--full` runs every step unscoped, in CI's order (also the fallback when the
base cannot be resolved, e.g. a clone too shallow to reach it):
  1. `npm run lint:check`      (ESLint)
  2. `npm run format:check`    (Prettier)
  3. `npm run types:check`     (vue-tsc)
  4. `npm run test:unit`       (Vitest, full run)
  5. `composer lint:check`     (Pint)
  6. `composer types:check`    (PHPStan/Larastan)
  7. `php artisan test --parallel` (Pest, with composer's process timeout off:
     `composer test:pest` hits the 300s default on this suite)

The PHP steps need the PHP dev tools, which the SessionStart hook installs
through `.claude/scripts/install_dev_tools.py`. When `vendor/bin/pint` is absent
(that install failed), they are skipped with a note rather than failed: read
them from CI instead. Pest also needs `.env` with an APP_KEY and
`public/build/manifest.json`; the same hook provides both.

Usage:
    python3 .claude/scripts/pre_push_check.py [--full] [--base <ref>]

It first waits for the background SessionStart setup (wait_for_setup.py) so the
PHP steps are chosen from a finished install, not a half-installed one.

Exits non-zero, and stops at the first failing step, if any check fails.
"""

import argparse
import importlib.util
import os
import subprocess
import sys
from pathlib import Path

import wait_for_setup

ROOT = Path(__file__).resolve().parent.parent.parent

PHP_ENV = {**os.environ, 'COMPOSER_ALLOW_SUPERUSER': '1', 'COMPOSER_PROCESS_TIMEOUT': '0'}

DEFAULT_BASE = 'origin/develop'

ESLINT_SUFFIXES = ('.js', '.mjs', '.cjs', '.ts', '.tsx', '.vue')

_spec = importlib.util.spec_from_file_location('ci_plan', ROOT / '.github' / 'scripts' / 'ci_plan.py')
ci_plan = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(ci_plan)

Step = tuple[str, list[str], dict[str, str] | None]


def run_step(label: str, command: list[str], *, env: dict[str, str] | None = None) -> bool:
    """Run one command, printing a header, and return whether it passed."""
    print(f'\n=== {label} ===')
    result = subprocess.run(command, cwd=ROOT, env=env)

    if result.returncode == 0:
        return True

    print(f'--- {label}: FAILED (exit {result.returncode})')
    return False


def php_tools_installed() -> bool:
    """Whether the dev tools the PHP steps need are present."""
    return (ROOT / 'vendor' / 'bin' / 'pint').exists()


def git_lines(*args: str) -> list[str] | None:
    """Return a git command's output lines, or None when it fails."""
    result = subprocess.run(['git', *args], cwd=ROOT, capture_output=True, text=True)

    if result.returncode != 0:
        return None

    return [line for line in result.stdout.splitlines() if line]


def changed_files(base: str) -> list[str] | None:
    """Paths changed since the branch left `base`, committed or not.

    Renames are listed as a deletion plus an addition, so the old path is
    classified too. None when the merge base cannot be found.
    """
    merge_base = git_lines('merge-base', base, 'HEAD')

    if not merge_base:
        return None

    committed = git_lines('diff', '--name-only', '--no-renames', merge_base[0], 'HEAD') or []
    uncommitted = git_lines('diff', '--name-only', '--no-renames', 'HEAD') or []
    untracked = git_lines('ls-files', '--others', '--exclude-standard') or []

    return sorted({*committed, *uncommitted, *untracked})


def full_steps() -> list[Step]:
    """Every CI step, unscoped, in CI's order."""
    steps: list[Step] = [
        ('ESLint', ['npm', 'run', 'lint:check'], None),
        ('Prettier', ['npm', 'run', 'format:check'], None),
        ('vue-tsc', ['npm', 'run', 'types:check'], None),
        ('Vitest', ['npm', 'run', 'test:unit'], None),
    ]

    if not php_tools_installed():
        return steps

    return steps + [('Pint', ['composer', 'lint:check'], PHP_ENV)] + php_suite_steps()


def php_suite_steps() -> list[Step]:
    """The PHP steps with no per-file mode: PHPStan and Pest."""
    return [
        ('PHPStan', ['composer', 'types:check'], PHP_ENV),
        ('Clear config cache', ['php', 'artisan', 'config:clear'], PHP_ENV),
        ('Pest', ['php', 'artisan', 'test', '--parallel', '--compact'], PHP_ENV),
    ]


def scoped_steps(files: list[str]) -> list[Step]:
    """The steps the changed files need, each scoped as far as the tool allows."""
    needed = ci_plan.plan(files)
    existing = [path for path in files if (ROOT / path).is_file()]
    steps: list[Step] = []

    if needed['js']:
        lintable = [path for path in existing if path.endswith(ESLINT_SUFFIXES)]
        formattable = [path for path in existing if path.startswith('resources/')]
        frontend = [path for path in existing if path.startswith('resources/js/')]

        if lintable:
            steps.append(('ESLint', ['npx', 'eslint', '--no-warn-ignored', *lintable], None))

        if formattable:
            steps.append(('Prettier', ['npx', 'prettier', '--check', '--ignore-unknown', *formattable], None))

        steps.append(('vue-tsc', ['npm', 'run', 'types:check'], None))

        if frontend:
            steps.append(('Vitest', ['npx', 'vitest', 'related', '--run', '--passWithNoTests', *frontend], None))

    if needed['php'] and php_tools_installed():
        php_files = [path for path in existing if path.endswith('.php')]

        if php_files:
            steps.append(('Pint', ['vendor/bin/pint', '--test', *php_files], PHP_ENV))

        steps += php_suite_steps()

    return steps


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description='Run the CI checks this branch needs before a push.')
    parser.add_argument('--full', action='store_true', help='run every check unscoped, in CI order')
    parser.add_argument('--base', default=DEFAULT_BASE, help=f'the ref the branch is compared with (default {DEFAULT_BASE})')
    args = parser.parse_args(argv)

    # The SessionStart setup runs in the background: steps are chosen from what
    # is installed, so wait for it first. A failed or interrupted setup is not
    # fatal here; the steps simply reflect whatever did get installed.
    print('\n=== Session setup ===')
    print(wait_for_setup.wait(wait_for_setup.state_dir(), wait_for_setup.DEFAULT_TIMEOUT_SECONDS)[1])

    files = None if args.full else changed_files(args.base)

    if files is None:
        if not args.full:
            print(f'\nCould not find where this branch left {args.base}; running every check.')

        steps = full_steps()
        needed = dict.fromkeys(ci_plan.GROUPS, True)
    else:
        steps = scoped_steps(files)
        needed = ci_plan.plan(files)
        groups = ', '.join(group for group, value in needed.items() if value) or 'none'
        print(f'\n{len(files)} changed file(s) since {args.base}; CI groups needed: {groups}.')

    if not any(needed.values()):
        print('Nothing to check: no CI check reads the changed files.')
        return 0

    for label, command, env in steps:
        if not run_step(label, command, env=env):
            print(f'\nStopping: "{label}" failed. Fix it and re-run '
                  f'`python3 .claude/scripts/pre_push_check.py` before pushing.')
            return 1

    if needed['php'] and not php_tools_installed():
        print('\nEvery check that ran passed. Pint, PHPStan and Pest were skipped: '
              'vendor/bin/pint is missing, so the dev-tools install did not complete. '
              'Read them from CI.')

        return 0

    print('\nAll applicable checks passed locally.' if files is not None else '\nAll CI checks passed locally.')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

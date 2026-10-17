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
whole repository, and the E2E, MySQL and audit jobs only run there unless
`--attest` ran them here. Scoping
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

`--attest` is the mode that lets CI skip work. It runs every group the planner
says the branch needs, unscoped and in full, *including* the ones the modes
above leave to CI: the migration tests on a local MySQL (install_mysql.sh),
Playwright on the preinstalled Chromium, the composer/npm audits and the
deploy-script test. When all of them pass it amends the unpushed head commit with

    CI-Verified: tree=<head tree sha> groups=<the groups that ran and passed>

and the `plan` job skips exactly those groups for a pull request whose merged
tree equals that head tree (see .github/scripts/ci_plan.py). It refuses unless
the work tree is clean (what ran is what the commit holds) and nothing is hidden
with skip-worktree/assume-unchanged. A group that cannot run here (no PHP dev
tools, no database, no app) is left out of the trailer and CI runs it; a check
that fails stops the run and records nothing. The trailer is self-attested: it
is only as good as the local run, so a commit amended by hand is trusted too.

Usage:
    python3 .claude/scripts/pre_push_check.py [--full] [--attest] [--base <ref>]

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

MYSQL_ENV = {
    **PHP_ENV,
    'DB_CONNECTION': 'mysql',
    'DB_HOST': '127.0.0.1',
    'DB_PORT': '3306',
    'DB_DATABASE': 'warehouse_test',
    'DB_USERNAME': 'root',
    'DB_PASSWORD': 'password',
}

# The files the `Pest (MySQL migrations)` job runs (.github/workflows/tests.yml).
MYSQL_TESTS = [
    'tests/Feature/Database/DropRedundantForeignKeyIndexesTest.php',
    'tests/Feature/Database/SchemaIdentifierLengthTest.php',
    'tests/Feature/Database/AddFulltextIndexToProductsTableTest.php',
    'tests/Feature/Database/AddIndexesToStoreTablesTest.php',
]

# The sandbox ships Chromium at a fixed path that playwright.config.ts reads.
CHROMIUM = Path('/opt/pw-browsers/chromium')

# Order the groups run in under --attest; `build` is inserted before the first
# group that needs the Vite manifest.
ATTEST_ORDER = ('js', 'php', 'mysql', 'e2e', 'audit', 'deploy-script')

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


def js_steps() -> list[Step]:
    """ESLint, Prettier, vue-tsc and Vitest, unscoped, as the CI jobs run them."""
    return [step for step in full_steps() if step[0] in ('ESLint', 'Prettier', 'vue-tsc', 'Vitest')]


def build_step() -> Step:
    """The Vite build CI runs before Pest and Playwright, with the font-less fallback."""
    return ('Build assets', ['bash', '-c', 'npm run build || python3 .claude/scripts/build_without_fonts.py'], None)


def wayfinder_step() -> Step:
    """Regenerate the gitignored Wayfinder types, as CI does before ESLint, vue-tsc and Vitest.

    Stale ones (a controller added since the session started) make ESLint report
    import/order errors on `@/actions/...` imports that CI does not have.
    """
    return ('Wayfinder types', ['php', 'artisan', 'wayfinder:generate', '--skip-routes', '--with-form'], PHP_ENV)


def mysql_steps() -> list[Step]:
    return [('Pest (MySQL migrations)', ['php', 'artisan', 'test', *MYSQL_TESTS], MYSQL_ENV)]


def e2e_steps() -> list[Step]:
    """Playwright as the CI job runs it (`npm run test:e2e` is a build plus this)."""
    ensure_databases = (
        "foreach (['database', 'telescope', 'pulse', 'health'] as $name) "
        "{ file_exists(\"database/$name.sqlite\") || touch(\"database/$name.sqlite\"); }"
    )
    env = {**os.environ, 'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH': str(CHROMIUM)} if CHROMIUM.exists() else None

    return [
        ('SQLite database files', ['php', '-r', ensure_databases], None),
        ('Playwright E2E', ['npx', 'playwright', 'test'], env),
    ]


def audit_steps() -> list[Step]:
    return [
        ('Composer audit', ['composer', 'audit', '--locked', '--abandoned=report'], PHP_ENV),
        ('npm audit', ['npm', 'audit', '--omit=dev', '--audit-level=high'], None),
    ]


def deploy_script_steps() -> list[Step]:
    return [('Deploy script', ['bash', '.github/scripts/deploy-remote-test.sh'], None)]


def backend_installed() -> bool:
    return (ROOT / 'vendor' / 'autoload.php').exists()


def mysql_ready() -> bool:
    """Make sure the local MySQL answers (install_mysql.sh installs and starts it)."""
    return subprocess.run(['bash', '.claude/scripts/install_mysql.sh'], cwd=ROOT,
                          stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0


def unavailable_reason(group: str) -> str | None:
    """Why this group cannot run here, or None when it can."""
    if group == 'php' and not php_tools_installed():
        return 'vendor/bin/pint is missing (the dev-tools install did not complete)'

    if group in ('js', 'mysql', 'e2e') and not backend_installed():
        return 'vendor/ is missing (the dependency install did not complete)'

    if group == 'mysql' and not php_tools_installed():
        return 'the Pest dev tools are missing'

    if group == 'mysql' and not mysql_ready():
        return 'no MySQL server answers (.claude/scripts/install_mysql.sh failed)'

    return None


def attest_steps(group: str) -> list[Step]:
    return {
        'js': lambda: [wayfinder_step(), *js_steps()],
        'php': lambda: [step for step in full_steps() if step[0] in ('Pint', 'PHPStan', 'Clear config cache', 'Pest')],
        'mysql': mysql_steps,
        'e2e': e2e_steps,
        'audit': audit_steps,
        'deploy-script': deploy_script_steps,
    }[group]()


def dirty_paths() -> list[str]:
    """Tracked changes and untracked, unignored files: anything the commit does not hold."""
    return git_lines('status', '--porcelain') or []


def tracked_changes() -> list[str]:
    """Tracked files whose content differs from the commit (untracked files are not counted)."""
    return git_lines('status', '--porcelain', '--untracked-files=no') or []


def hidden_paths() -> list[str]:
    """Files git is told to ignore changes to (skip-worktree/assume-unchanged), which `git status` hides."""
    return [line[2:] for line in git_lines('ls-files', '-v') or [] if line[:2] in ('S ', 'h ')]


def head_is_pushed() -> bool:
    return bool(git_lines('branch', '-r', '--contains', 'HEAD'))


def head_tree() -> str:
    return (git_lines('rev-parse', 'HEAD^{tree}') or [''])[0]


def amend_with_trailer(trailer: str) -> bool:
    """Replace any earlier attestation on the head commit with `trailer`, keeping its tree."""
    message = subprocess.run(['git', 'log', '-1', '--format=%B'], cwd=ROOT, capture_output=True, text=True).stdout
    body = '\n'.join(line for line in message.splitlines() if not line.startswith('CI-Verified:')).rstrip() + '\n'
    merged = subprocess.run(['git', 'interpret-trailers', '--trailer', trailer], cwd=ROOT, input=body,
                            capture_output=True, text=True)

    if merged.returncode != 0:
        return False

    return subprocess.run(['git', 'commit', '--amend', '-q', '-F', '-'], cwd=ROOT, input=merged.stdout, text=True).returncode == 0


def attest(base: str) -> int:
    """Run every planned group in full and record what passed on the head commit."""
    files = changed_files(base)

    if files is None:
        print(f'\nCannot attest: no merge base with {base}, so what CI would plan is unknown.')
        return 2

    problems = [f'uncommitted or untracked: {path}' for path in dirty_paths()[:10]]
    problems += [f'hidden from git status (skip-worktree/assume-unchanged): {path}' for path in hidden_paths()]

    if problems:
        print('\nCannot attest: what runs here must be exactly what the commit holds.')
        print('\n'.join(f'  - {problem}' for problem in problems))
        return 2

    needed = ci_plan.plan(files)
    wanted = [group for group in ATTEST_ORDER if needed.get(group)]
    print(f'\n{len(files)} changed file(s) since {base}; CI groups needed: {", ".join(wanted) or "none"}.')

    if not wanted:
        print('Nothing to attest: no CI check reads the changed files.')
        return 0

    attested: list[str] = []
    left_to_ci: list[str] = []
    built = False

    for group in wanted:
        reason = unavailable_reason(group)

        if reason:
            print(f'\n--- {group}: cannot run here ({reason}); CI will run it.')
            left_to_ci.append(group)
            continue

        steps = attest_steps(group)

        if group in ('php', 'e2e') and not built:
            steps = [build_step(), *steps]
            built = True

        for label, command, env in steps:
            if not run_step(f'{group}: {label}', command, env=env):
                print(f'\nStopping: "{label}" failed, so nothing is attested. Fix it and re-run with --attest.')
                return 1

        attested.append(group)

    # Tests write files (E2E uploads under public/uploads) that no commit holds
    # and CI's fresh checkout would also start without; those are reported, not
    # fatal. A change to a tracked file is: what ran would no longer be the commit.
    if tracked_changes():
        print('\nThe checks changed tracked files, so what ran is not what the commit holds; nothing is attested:')
        print('\n'.join(f'  - {path}' for path in tracked_changes()[:10]))
        return 1

    if dirty_paths():
        print('\nThe checks left untracked files behind (not part of the commit):')
        print('\n'.join(f'  - {path}' for path in dirty_paths()[:10]))

    if not attested:
        print('\nNothing could be attested here; CI runs everything.')
        return 0

    tree = head_tree()
    trailer = ci_plan.format_attestation(tree, attested)
    note = f'; CI still runs: {", ".join(left_to_ci)}' if left_to_ci else ''

    if head_is_pushed():
        print(f'\nEvery attested group passed, but HEAD is already on a remote branch and amending it would '
              f'need a force-push. Add this trailer to your next commit message instead:\n\n  {trailer}\n{note}')
        return 0

    if not amend_with_trailer(trailer) or head_tree() != tree:
        print(f'\nCould not amend HEAD. Add this trailer to the commit message yourself:\n\n  {trailer}')
        return 1

    print(f'\nAttested and amended HEAD:\n\n  {trailer}\n\nPush as usual; CI skips {", ".join(attested)}{note}.')

    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description='Run the CI checks this branch needs before a push.')
    parser.add_argument('--full', action='store_true', help='run every check unscoped, in CI order')
    parser.add_argument('--attest', action='store_true',
                        help='run every planned group in full (E2E, MySQL and audits included) and, when they pass, '
                             'record a CI-Verified trailer on the unpushed head commit')
    parser.add_argument('--base', default=DEFAULT_BASE, help=f'the ref the branch is compared with (default {DEFAULT_BASE})')
    args = parser.parse_args(argv)

    # The SessionStart setup runs in the background: steps are chosen from what
    # is installed, so wait for it first. A failed or interrupted setup is not
    # fatal here; the steps simply reflect whatever did get installed.
    print('\n=== Session setup ===')
    print(wait_for_setup.wait(wait_for_setup.state_dir(), wait_for_setup.DEFAULT_TIMEOUT_SECONDS)[1])

    if args.attest:
        return attest(args.base)

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
    print('E2E, MySQL and the audits run only in CI. `--attest` runs them here too and lets CI skip what passed.')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

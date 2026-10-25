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

Attesting is automatic: with no flag, a clean work tree, an unpushed head commit
and a change outside .github/ select `--attest` below; anything else (edits not
committed yet, a pushed head, a workflow change) runs the scoped checks above
and says why. `--quick` forces the scoped checks; `--attest` forces attesting
and fails loudly when it cannot.

`--attest` is the mode that lets CI skip work. It runs every group the planner
says the branch needs, unscoped and in full, *including* the ones the modes
above leave to CI: the migration tests on a local MySQL (install_mysql.sh),
Playwright on the preinstalled Chromium, the composer/npm audits and the
deploy-script test. When all of them pass it amends the unpushed head commit with

    CI-Verified: tree=<head tree sha> groups=<the groups that ran and passed>

and the `plan` job skips exactly those groups for a pull request whose merged
tree equals that head tree (see .github/scripts/ci_plan.py). It refuses unless
the work tree is clean (what ran is what the commit holds) and nothing is hidden
with skip-worktree/assume-unchanged. The groups run as parallel lanes (about 10
minutes, against about 25 one after another): the Wayfinder files, the Vite build
and the config cache run first, alone, then js, php, mysql, e2e, audit and
deploy-script run at the same time, each logging to its own file. Playwright keeps
normal CPU priority and the other lanes run niced; the first failing lane stops
the rest and everything they started. `--serial` runs them one at a time. A group that cannot run here (no PHP dev
tools, no database, no app) is left out of the trailer and CI runs it; a check
that fails stops the run and records nothing. The trailer is self-attested: it
is only as good as the local run, so a commit amended by hand is trusted too.

Usage:
    python3 .claude/scripts/pre_push_check.py [--full] [--quick] [--attest] [--base <ref>]

It first waits for the background SessionStart setup (wait_for_setup.py) so the
PHP steps are chosen from a finished install, not a half-installed one.

Exits non-zero, and stops at the first failing step, if any check fails.
"""

import argparse
import concurrent.futures
import importlib.util
import os
import shutil
import signal
import subprocess
import sys
import tempfile
import threading
import time
from pathlib import Path

import wait_for_setup
import install_playwright

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
    'tests/Feature/Database/WidenPaymentTransactionCodeTest.php',
    'tests/Feature/Store/Admin/SellerPayoutDefectsTest.php',
    'tests/Feature/SellerDashboardTest.php',
    'tests/Feature/Store/Admin/ProductEditRoutingTest.php',
    'tests/Feature/StorefrontNullSlugTest.php',
    'tests/Feature/StorefrontRemainingBladeUrlTest.php',
    'tests/Feature/Database/DropRedundantForeignKeyIndexesTest.php',
    'tests/Feature/Database/SchemaIdentifierLengthTest.php',
    'tests/Feature/Database/AddFulltextIndexToProductsTableTest.php',
    'tests/Feature/Database/AddIndexesToStoreTablesTest.php',
    'tests/Feature/Database/LoadLegacyReferenceDataTest.php',
    'tests/Feature/Database/WidenMapLongitudeColumnsTest.php',
    'tests/Feature/Database/AddSyncedAtToProductsTableTest.php',
    'tests/Feature/Console/SyncProductsCommandTest.php',
    'tests/Feature/ProductColumnOwnershipTest.php',
    'tests/Feature/SellerProductPersistenceTest.php',
    'tests/Feature/SellerDigitalProductPersistenceTest.php',
    'tests/Feature/SellerCatalogueRowRulesTest.php',
    'tests/Feature/Store/Admin/ProductMySqlEditTest.php',
]

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
        *wayfinder_steps(),
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
        steps += wayfinder_steps()

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


def wayfinder_steps() -> list[Step]:
    """The Wayfinder regeneration, when the app is installed to generate from."""
    return [wayfinder_step()] if backend_installed() else []


def wayfinder_step() -> Step:
    """Regenerate the gitignored Wayfinder types, as CI does before ESLint, vue-tsc and Vitest.

    Stale ones (a controller added since the session started) make ESLint report
    import/order errors on `@/actions/...` imports that CI does not have, so every
    mode that runs a JS check regenerates them first, not only --attest.
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
    env = install_playwright.browser_environment(ROOT)

    return [
        ('SQLite database files', ['php', '-r', ensure_databases], None),
        # Playwright empties and recreates its output directory when it starts. In
        # the repository that is `test-results/`, which Pint walks at the same time
        # in another lane and dies on ("Failed to open directory"), so keep it out
        # of the work tree.
        ('Playwright E2E', ['npx', 'playwright', 'test', f'--output={tempfile.mkdtemp(prefix="pw-results-")}'], env),
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
    """One group's steps, run in order inside its lane (prerequisites excluded)."""
    return {
        'js': js_steps,
        'php': lambda: [step for step in full_steps() if step[0] in ('Pint', 'PHPStan', 'Pest')],
        'mysql': mysql_steps,
        'e2e': e2e_steps,
        'audit': audit_steps,
        'deploy-script': deploy_script_steps,
    }[group]()


def attest_prerequisites(groups: list[str]) -> list[Step]:
    """What must finish, alone, before the lanes start.

    The Wayfinder files and the Vite build are written into the work tree that
    ESLint, vue-tsc, Vitest, Pest and Playwright all read, and `config:clear`
    deletes a cache every `php artisan` process reads, so none of them can run
    beside those lanes.
    """
    steps: list[Step] = []

    if backend_installed() and {'js', 'php', 'e2e'} & set(groups):
        steps.append(wayfinder_step())

    if {'php', 'e2e'} & set(groups):
        steps.append(build_step())

    if 'php' in groups:
        steps.append(('Clear config cache', ['php', 'artisan', 'config:clear'], PHP_ENV))

    return steps


# The lane that holds Playwright is the long pole and its 5s expectations are
# timing-sensitive, so every other lane runs at lower CPU priority beside it.
PRIORITY_LANE = 'e2e'


def run_lane(name: str, steps: list[Step], log_path: Path, stop: threading.Event,
             running: dict[str, subprocess.Popen]) -> tuple[bool | None, str, float]:
    """Run one lane's steps in order, writing everything to `log_path`.

    Returns (passed, failed step label, seconds); passed is None when another
    lane's failure stopped this one before it finished.
    """
    started = time.monotonic()
    nice = ['nice', '-n', '10'] if name != PRIORITY_LANE and shutil.which('nice') else []

    with log_path.open('w') as log:
        for label, command, env in steps:
            if stop.is_set():
                return None, label, time.monotonic() - started

            log.write(f'\n=== {label} ===\n')
            log.flush()
            process = subprocess.Popen([*nice, *command], cwd=ROOT, env=env, stdout=log, stderr=subprocess.STDOUT,
                                       start_new_session=True)
            running[name] = process

            # A failure elsewhere may have stopped the lanes between the check above
            # and this registration, when stop_lanes could not see this process yet.
            if stop.is_set():
                stop_lanes(stop, running)

            code = process.wait()

            if stop.is_set() and code != 0:
                return None, label, time.monotonic() - started

            if code != 0:
                log.write(f'\n--- {label}: FAILED (exit {code})\n')
                return False, label, time.monotonic() - started

    return True, '', time.monotonic() - started


def descendants(pid: int) -> list[int]:
    """Every process below `pid`, found through /proc (Linux), children before parents."""
    children: dict[int, list[int]] = {}

    for entry in Path('/proc').iterdir():
        if not entry.name.isdigit():
            continue

        try:
            fields = (entry / 'stat').read_text().rsplit(')', 1)[1].split()
            children.setdefault(int(fields[1]), []).append(int(entry.name))
        except (OSError, IndexError, ValueError):
            continue

    found: list[int] = []
    pending = [pid]

    while pending:
        for child in children.get(pending.pop(), []):
            found.append(child)
            pending.append(child)

    return list(reversed(found))


def stop_lanes(stop: threading.Event, running: dict[str, subprocess.Popen]) -> None:
    """Stop every lane still running, and everything its commands started.

    A process group is not enough: Playwright starts its web server (`node` and
    `php artisan serve` on port 8010/8011) in a session of its own, and killing
    only the group leaves that server holding the port, so the next run fails
    with "already used". So the whole tree is collected first (once a parent dies
    its children are re-parented and can no longer be found) and terminated,
    with a SIGKILL for whatever has not exited a few seconds later.
    """
    stop.set()
    doomed: list[int] = []

    for process in list(running.values()):
        if process.poll() is None:
            doomed += [*descendants(process.pid), process.pid]

    def alive() -> list[int]:
        return [pid for pid in doomed if Path(f'/proc/{pid}').exists()]

    for sig in (signal.SIGTERM, signal.SIGKILL):
        for pid in alive():
            try:
                os.kill(pid, sig)
            except (ProcessLookupError, PermissionError):
                pass

        if sig == signal.SIGTERM:
            deadline = time.monotonic() + 5

            while time.monotonic() < deadline and alive():
                time.sleep(0.2)


def tail(path: Path, lines: int) -> str:
    return '\n'.join(path.read_text(errors='replace').splitlines()[-lines:])


def run_lanes(lanes: dict[str, list[Step]], *, serial: bool = False, log_dir: Path | None = None) -> str | None:
    """Run the lanes at the same time and return the first lane that failed, else None.

    Each lane's output goes to its own log, so parallel runs never interleave:
    a lane's tail is printed when it ends, and a failing lane's log is printed
    in full (last 120 lines). The first failure stops the other lanes, since an
    attestation needs every lane to pass.
    """
    log_dir = log_dir or Path(tempfile.mkdtemp(prefix='pre-push-'))
    stop = threading.Event()
    running: dict[str, subprocess.Popen] = {}
    failed: str | None = None
    names = list(lanes)
    print(f'\nRunning {len(names)} lane(s){" one at a time" if serial else " at the same time"}: {", ".join(names)}. Logs: {log_dir}')

    with concurrent.futures.ThreadPoolExecutor(max_workers=1 if serial else max(1, len(names))) as pool:
        futures = {pool.submit(run_lane, name, lanes[name], log_dir / f'{name}.log', stop, running): name for name in names}

        for future in concurrent.futures.as_completed(futures):
            name = futures[future]
            passed, label, seconds = future.result()
            log = log_dir / f'{name}.log'

            if passed is None:
                print(f'--- {name}: stopped after {seconds:.0f}s (another lane failed)')
            elif passed:
                print(f'--- {name}: passed in {seconds:.0f}s')
            else:
                print(f'--- {name}: FAILED at "{label}" after {seconds:.0f}s; its log (last 120 lines):\n')
                print(tail(log, 120))

                if failed is None:
                    failed = name
                    stop_lanes(stop, running)

    return failed


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


def attest_blocker(files: list[str] | None) -> str | None:
    """Why the default run should not attest, or None when it should.

    Attesting needs a clean work tree and an unpushed head commit to amend, and
    is pointless when CI ignores the trailer (a change under .github/) or has
    nothing to run. Anything else attests, so there is no flag to forget.
    """
    if files is None:
        return 'the branch base could not be found'

    if not any(ci_plan.plan(files).values()):
        return 'no CI check reads the changed files'

    if any(path.startswith('.github/') for path in files):
        return 'a change under .github/ runs every CI job and ignores the trailer'

    if dirty_paths() or hidden_paths():
        return 'there are uncommitted, untracked or hidden changes (commit them first)'

    if head_is_pushed():
        return 'the head commit is already on a remote branch, so it cannot be amended'

    return None


def attest(base: str, *, serial: bool = False) -> int:
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

    for group in wanted:
        reason = unavailable_reason(group)

        if reason:
            print(f'\n--- {group}: cannot run here ({reason}); CI will run it.')
            left_to_ci.append(group)
        else:
            attested.append(group)

    for label, command, env in attest_prerequisites(attested):
        if not run_step(f'before the lanes: {label}', command, env=env):
            print(f'\nStopping: "{label}" failed, so nothing is attested. Fix it and re-run with --attest.')
            return 1

    started = time.monotonic()
    failed = run_lanes({group: attest_steps(group) for group in attested}, serial=serial)

    if failed:
        print(f'\nStopping: the "{failed}" lane failed, so nothing is attested. Fix it and re-run with --attest.')
        return 1

    print(f'\nAll {len(attested)} lane(s) passed in {time.monotonic() - started:.0f}s.')

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
    parser.add_argument('--quick', action='store_true',
                        help='never attest: run the scoped checks only (the default does this itself when the tree is '
                             'dirty, the head commit is pushed, or the change is under .github/)')
    parser.add_argument('--attest', action='store_true',
                        help='run every planned group in full (E2E, MySQL and audits included) and, when they pass, '
                             'record a CI-Verified trailer on the unpushed head commit')
    parser.add_argument('--serial', action='store_true',
                        help='run the attested groups one after another instead of at the same time '
                             '(for a machine with too few cores)')
    parser.add_argument('--base', default=DEFAULT_BASE, help=f'the ref the branch is compared with (default {DEFAULT_BASE})')
    args = parser.parse_args(argv)

    # The SessionStart setup runs in the background: steps are chosen from what
    # is installed, so wait for it first. A failed or interrupted setup is not
    # fatal here; the steps simply reflect whatever did get installed.
    print('\n=== Session setup ===')
    print(wait_for_setup.wait(wait_for_setup.state_dir(), wait_for_setup.DEFAULT_TIMEOUT_SECONDS)[1])

    if args.attest:
        return attest(args.base, serial=args.serial)

    files = None if args.full else changed_files(args.base)

    if not args.full and not args.quick:
        blocker = attest_blocker(files)

        if blocker is None:
            print('\nClean tree, unpushed head commit: running the full attested run '
                  '(about 10 minutes). Use --quick for the scoped checks only.')
            return attest(args.base, serial=args.serial)

        print(f'\nNot attesting: {blocker}. Running the scoped checks.')

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
    print('E2E, MySQL and the audits run only in CI. Commit, then re-run this on a clean tree to attest '
          'them here and let CI skip what passed.')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

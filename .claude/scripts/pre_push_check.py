"""Run every locally-available check, in the order CI runs its equivalents.

This is the single command a session should run before pushing, so pre-push
verification doesn't depend on a session remembering to assemble it from the
sandbox section of CLAUDE.md by hand. It is NOT a substitute for CI: Pint,
PHPStan/Larastan and Pest cannot run here (see CLAUDE.md's "Sandbox" section)
and are read from `composer ci:check` in CI, never locally.

What it runs, in this order (matching `.github/workflows/tests.yml`):
  1. `npm run lint:check`   (ESLint)
  2. `npm run format:check` (Prettier)
  3. `npm run types:check`  (vue-tsc)
  4. `npm run test:unit`    (Vitest, full run)
  5. `php -l` on every changed PHP file (syntax only — not Pint/PHPStan)
  6. `.claude/scripts/php_import_order.py` on every changed PHP file
  7. `.claude/scripts/php_pint_style.py` on every changed PHP file
  8. `.claude/scripts/js_balance.py` on every changed .ts/.js/.vue file
  9. `.claude/scripts/vue_unused_imports.py` on every changed .vue file
     (advisory — never fails the run)
 10. `.claude/scripts/vue_prettier_hints.py` on every changed .vue file
     (advisory — never fails the run)

"Changed" files are those differing from the merge-base with `origin/develop`
(the script fetches it first) plus any untracked new files, falling back to
uncommitted changes against HEAD if that remote branch or a merge-base with
it isn't available (e.g. a fresh clone with only the current branch fetched).
Steps 1-4 always run against the
whole project, matching what CI actually gates on; the changed-file list only
scopes the per-file stand-in scripts, which are too slow/noisy to run over
every file in the repo on each push.

Anything under `legacy/` is left out of the changed-file list: it is a
vendored copy of a separate PHP 7 / Laravel 8 app with its own toolchain
(see `.ai/rules/legacy-albaraka-holland.md`), not this app's code.

`php_duplicate_blocks.py` is intentionally NOT included here — its own
docstring says it is for a deliberate cross-cutting review against the
duplication threshold in CLAUDE.md, not a per-push gate.

Usage:
    python3 .claude/scripts/pre_push_check.py

Exits non-zero (and stops at the first failing step) if any blocking check
fails. Advisory steps print their output but never fail the run.
"""

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
SCRIPTS = Path(__file__).parent

VENDORED_PREFIXES = ('legacy/',)


def is_root_project_path(path: str) -> bool:
    """Whether a repo-relative path belongs to this app rather than a vendored legacy app."""
    return not path.startswith(VENDORED_PREFIXES)


def changed_files() -> list[str]:
    """Return paths (relative to ROOT) that differ from the develop baseline.

    Includes untracked new files (excluded by .gitignore), since a brand new
    file is exactly the kind of thing a pre-push check must not skip.
    """
    subprocess.run(
        ['git', 'fetch', 'origin', 'develop'],
        cwd=ROOT, capture_output=True, text=True,
    )

    merge_base = subprocess.run(
        ['git', 'merge-base', 'HEAD', 'origin/develop'],
        cwd=ROOT, capture_output=True, text=True,
    )
    diff_base = merge_base.stdout.strip() if merge_base.returncode == 0 else 'HEAD'

    diff = subprocess.run(
        ['git', 'diff', '--name-only', '--diff-filter=d', diff_base],
        cwd=ROOT, capture_output=True, text=True,
    )

    if diff.returncode != 0:
        # No baseline reachable (e.g. shallow clone without develop) — fall
        # back to whatever is uncommitted against the working tree.
        diff = subprocess.run(
            ['git', 'diff', '--name-only', '--diff-filter=d', 'HEAD'],
            cwd=ROOT, capture_output=True, text=True,
        )

    tracked = [line for line in diff.stdout.splitlines() if line]

    untracked = subprocess.run(
        ['git', 'ls-files', '--others', '--exclude-standard'],
        cwd=ROOT, capture_output=True, text=True,
    )

    paths = set(tracked) | {line for line in untracked.stdout.splitlines() if line}

    return sorted(path for path in paths if is_root_project_path(path))


def run_step(label: str, command: list[str], *, advisory: bool = False) -> bool:
    """Run one command, printing a header, and return whether it passed."""
    print(f'\n=== {label} ===')
    result = subprocess.run(command, cwd=ROOT)

    if result.returncode == 0:
        return True

    if advisory:
        print(f'--- {label}: advisory step reported findings above (non-blocking)')
        return True

    print(f'--- {label}: FAILED (exit {result.returncode})')
    return False


def main() -> int:
    files = changed_files()
    php_files = [f for f in files if f.endswith('.php') and (ROOT / f).exists()]
    js_files = [
        f for f in files
        if f.endswith(('.ts', '.js', '.vue')) and (ROOT / f).exists()
    ]
    vue_files = [f for f in js_files if f.endswith('.vue')]

    steps: list[tuple[str, list[str], bool]] = [
        ('ESLint', ['npm', 'run', 'lint:check'], False),
        ('Prettier', ['npm', 'run', 'format:check'], False),
        ('vue-tsc', ['npm', 'run', 'types:check'], False),
        ('Vitest', ['npm', 'run', 'test:unit'], False),
    ]

    for php_file in php_files:
        steps.append((f'php -l {php_file}', ['php', '-l', php_file], False))

    if php_files:
        steps.append((
            'Pint import order (stand-in)',
            [sys.executable, str(SCRIPTS / 'php_import_order.py'), *php_files],
            False,
        ))
        steps.append((
            'Pint style (stand-in)',
            [sys.executable, str(SCRIPTS / 'php_pint_style.py'), *php_files],
            False,
        ))

    for js_file in js_files:
        steps.append((
            f'bracket balance {js_file}',
            [sys.executable, str(SCRIPTS / 'js_balance.py'), js_file],
            False,
        ))

    for vue_file in vue_files:
        steps.append((
            f'unused imports {vue_file}',
            [sys.executable, str(SCRIPTS / 'vue_unused_imports.py'), vue_file],
            True,
        ))
        steps.append((
            f'prettier hints {vue_file}',
            [sys.executable, str(SCRIPTS / 'vue_prettier_hints.py'), vue_file],
            True,
        ))

    for label, command, advisory in steps:
        if not run_step(label, command, advisory=advisory):
            print(f'\nStopping: "{label}" failed. Fix it and re-run '
                  f'`python3 .claude/scripts/pre_push_check.py` before pushing.')
            return 1

    print('\nAll available local checks passed.')
    print('Reminder: Pint, PHPStan/Larastan and Pest still only run in CI '
          '(`composer ci:check`) — this does not guarantee CI is green.')

    return 0


if __name__ == '__main__':
    sys.exit(main())

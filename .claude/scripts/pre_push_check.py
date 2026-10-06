"""Run the checks CI runs, in CI's order, as the one command before a push.

What it runs (matching `.github/workflows/tests.yml`):
  1. `npm run lint:check`      (ESLint)
  2. `npm run format:check`    (Prettier)
  3. `npm run types:check`     (vue-tsc)
  4. `npm run test:unit`       (Vitest, full run)
  5. `composer lint:check`     (Pint)
  6. `composer types:check`    (PHPStan/Larastan)
  7. `php artisan test --parallel` (Pest, with composer's process timeout off:
     `composer test:pest` hits the 300s default on this suite)

Steps 5-7 need the PHP dev tools, which the SessionStart hook installs through
`.claude/scripts/install_dev_tools.py`. When `vendor/bin/pint` is absent (that
install failed), they are skipped with a note rather than failed — read them
from `composer ci:check` in CI instead. Pest also needs `.env` with an APP_KEY
and `public/build/manifest.json`; the same hook provides both.

Usage:
    python3 .claude/scripts/pre_push_check.py

It first waits for the background SessionStart setup (wait_for_setup.py) so the
PHP steps are chosen from a finished install, not a half-installed one.

Exits non-zero, and stops at the first failing step, if any check fails.
"""

import os
import subprocess
import sys
from pathlib import Path

import wait_for_setup

ROOT = Path(__file__).resolve().parent.parent.parent

PHP_ENV = {**os.environ, 'COMPOSER_ALLOW_SUPERUSER': '1', 'COMPOSER_PROCESS_TIMEOUT': '0'}


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


def build_steps() -> list[tuple[str, list[str], dict[str, str] | None]]:
    """Return (label, command, env) for every step that can run here."""
    steps: list[tuple[str, list[str], dict[str, str] | None]] = [
        ('ESLint', ['npm', 'run', 'lint:check'], None),
        ('Prettier', ['npm', 'run', 'format:check'], None),
        ('vue-tsc', ['npm', 'run', 'types:check'], None),
        ('Vitest', ['npm', 'run', 'test:unit'], None),
    ]

    if not php_tools_installed():
        return steps

    return steps + [
        ('Pint', ['composer', 'lint:check'], PHP_ENV),
        ('PHPStan', ['composer', 'types:check'], PHP_ENV),
        ('Clear config cache', ['php', 'artisan', 'config:clear'], PHP_ENV),
        ('Pest', ['php', 'artisan', 'test', '--parallel', '--compact'], PHP_ENV),
    ]


def main() -> int:
    # The SessionStart setup runs in the background: steps are chosen from what
    # is installed, so wait for it first. A failed or interrupted setup is not
    # fatal here; the steps simply reflect whatever did get installed.
    print('\n=== Session setup ===')
    print(wait_for_setup.wait(wait_for_setup.state_dir(), wait_for_setup.DEFAULT_TIMEOUT_SECONDS)[1])

    for label, command, env in build_steps():
        if not run_step(label, command, env=env):
            print(f'\nStopping: "{label}" failed. Fix it and re-run '
                  f'`python3 .claude/scripts/pre_push_check.py` before pushing.')
            return 1

    if not php_tools_installed():
        print('\nJS checks passed. Pint, PHPStan and Pest were skipped: vendor/bin/pint is '
              'missing, so the dev-tools install did not complete. Read them from '
              '`composer ci:check` in CI.')

        return 0

    print('\nAll CI checks passed locally.')

    return 0


if __name__ == '__main__':
    sys.exit(main())

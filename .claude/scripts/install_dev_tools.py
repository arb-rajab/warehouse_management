"""Install the full composer dependency set (Pint, Pest, PHPStan, Larastan) in
a Claude Code web session, where `composer install` alone cannot.

Why a plain install fails there: every locked dev package's dist URL is
`api.github.com/repos/<vendor>/<package>/zipball/...`, and the egress proxy
returns 403 for any repository the session does not have attached. Anonymous
git clones of public repositories are served, though, so:

  * `--prefer-source` makes composer `git clone` the packages that carry a git
    `source` in composer.lock (Pint, Pest, Larastan and the rest).
  * `phpstan/phpstan` is the exception: it is locked dist-only, and a full
    mirror clone of its repository (which keeps every release phar in history)
    exceeds composer's process timeout. So this script shallow-clones the
    locked tag and points that one lock entry at the clone as a path dist.

composer.lock is edited in memory for the install and restored byte for byte
afterwards, whatever the outcome, so nothing here can reach a commit. Usage:

    python3 .claude/scripts/install_dev_tools.py [clone-dir]

The clone directory defaults to <system temp>/phpstan-src and is reused when it
already holds the locked commit. Exits with composer's own exit code.
"""

import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path

PACKAGE = 'phpstan/phpstan'


def patch_lock(lock: dict, clone_dir: str) -> dict:
    """Return the lock with PACKAGE's dist swapped for a path to clone_dir."""
    for package in lock['packages-dev']:
        if package['name'] != PACKAGE:
            continue

        package['dist'] = {
            'type': 'path',
            'url': clone_dir,
            'reference': package['dist']['reference'],
        }
        package.pop('source', None)

        return lock

    raise SystemExit(f'{PACKAGE} is not in composer.lock packages-dev; nothing to patch.')


def locked_package(lock: dict) -> dict:
    """Return PACKAGE's entry from the lock's dev packages."""
    return next(package for package in lock['packages-dev'] if package['name'] == PACKAGE)


def clone_locked_commit(version: str, reference: str, clone_dir: Path) -> None:
    """Shallow-clone `version` into clone_dir unless it already holds `reference`."""
    head = subprocess.run(
        ['git', '-C', str(clone_dir), 'rev-parse', 'HEAD'],
        capture_output=True,
        text=True,
    )
    if head.returncode == 0 and head.stdout.strip() == reference:
        return

    subprocess.run(['rm', '-rf', str(clone_dir)], check=True)
    subprocess.run(
        ['git', 'clone', '--depth', '1', '--branch', version, f'https://github.com/{PACKAGE}', str(clone_dir)],
        check=True,
        env={**os.environ, 'GIT_LFS_SKIP_SMUDGE': '1'},
    )

    cloned = subprocess.run(
        ['git', '-C', str(clone_dir), 'rev-parse', 'HEAD'],
        capture_output=True,
        text=True,
        check=True,
    ).stdout.strip()
    if cloned != reference:
        raise SystemExit(f'Tag {version} is at {cloned}, but composer.lock pins {reference}.')


def main() -> int:
    lock_path = Path('composer.lock')
    clone_dir = Path(sys.argv[1] if len(sys.argv) > 1 else Path(tempfile.gettempdir()) / 'phpstan-src')

    original = lock_path.read_bytes()
    lock = json.loads(original)
    package = locked_package(lock)

    clone_locked_commit(package['version'], package['dist']['reference'], clone_dir)

    lock_path.write_text(json.dumps(patch_lock(lock, str(clone_dir)), indent=4))
    try:
        return subprocess.run(
            ['composer', 'install', '--prefer-source', '--no-interaction', '--no-progress'],
            env={**os.environ, 'COMPOSER_ALLOW_SUPERUSER': '1', 'COMPOSER_PROCESS_TIMEOUT': '0'},
        ).returncode
    finally:
        lock_path.write_bytes(original)


if __name__ == '__main__':
    sys.exit(main())

"""Install the full composer dependency set (Pint, Pest, PHPStan, Larastan) in
a Claude Code web session, where `composer install` alone cannot.

Why a plain install fails there: every locked dev package's dist URL is
`api.github.com/repos/<vendor>/<package>/zipball/...`, and the egress proxy
returns 403 for any repository the session does not have attached. Anonymous
git clones of public repositories are served, though, so:

  * `--prefer-source` makes composer `git clone` the packages that carry a git
    `source` in composer.lock (Pint, Pest, Larastan and the rest). It covers
    every package, not just the dev ones: a cold run's 150 failed downloads were
    149 dist attempts, so a runtime-only dist pass first saves nothing and
    composer silently falls back to cloning anyway. Cost on a cold container:
    ~9 minutes and ~7 GB (about 4.6 GB of full-history clones in composer's cache
    plus ~2.8 GB in vendor/), so the SessionStart hook's timeout must allow for it.
  * `phpstan/phpstan` is the exception: it is locked dist-only, and a full
    mirror clone of its repository (which keeps every release phar in history)
    exceeds composer's process timeout. So this script shallow-clones the
    locked tag and points that one lock entry at the clone as a path dist.

composer.lock is patched on disk for the install and restored byte for byte
afterwards, whatever the outcome (including SIGTERM/SIGHUP), so nothing here can
reach a commit; git is told to ignore the lock's working-tree changes
(`--skip-worktree`) for as long as the patch is on disk, so the ~10 minute
install never shows a dirty tree that something might commit. The pristine copy
is also kept in the system temp directory
while the install runs: a SIGKILL, such as a hook timeout, skips every cleanup,
and the next run uses that copy (or, failing that, `git show HEAD:composer.lock`)
to undo the leftover patch instead of mistaking it for the original. Usage:

    python3 .claude/scripts/install_dev_tools.py [clone-dir]

The clone directory defaults to <system temp>/phpstan-src and is reused when it
already holds the locked commit. Exits with composer's own exit code.
"""

import hashlib
import json
import os
import signal
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


def is_patched(lock: dict) -> bool:
    """Whether the lock still carries this script's path dist for PACKAGE."""
    return locked_package(lock)['dist']['type'] == 'path'


def backup_path_for(lock_path: Path) -> Path:
    """Where the pristine lock is kept while a run is in flight, outside the repo."""
    digest = hashlib.sha1(str(lock_path.resolve()).encode()).hexdigest()[:12]

    return Path(tempfile.gettempdir()) / f'composer.lock.original.{digest}'


def set_skip_worktree(lock_path: Path, enabled: bool) -> None:
    """Hide (or stop hiding) the lock's working-tree changes from git.

    The patch lives on disk for the whole install, ~10 minutes and in the
    background, so `git status` would show a modified composer.lock the entire
    time and anything that commits "uncommitted changes" (the Stop hook does)
    would commit the patched lock, a path into /tmp, and break CI. With the flag
    set, git treats the file as unchanged. Failures are ignored: outside a git
    repository there is nothing to hide.
    """
    subprocess.run(
        ['git', 'update-index', '--skip-worktree' if enabled else '--no-skip-worktree', lock_path.name],
        cwd=lock_path.parent,
        capture_output=True,
    )


def recover_original(lock_path: Path, backup: Path) -> None:
    """Undo what an earlier run left behind when it was killed before restoring.

    A SIGKILL (a hook timeout, a reclaimed container) skips every `finally`, so
    the lock can be left patched. Without this, the next run would take that
    patched file for the original and faithfully restore it. It also clears the
    skip-worktree flag such a run leaves set, so git sees the restored lock.
    """
    set_skip_worktree(lock_path, False)

    if backup.exists():
        lock_path.write_bytes(backup.read_bytes())
        backup.unlink()

        return

    if not is_patched(json.loads(lock_path.read_bytes())):
        return

    committed = subprocess.run(
        ['git', 'show', 'HEAD:composer.lock'],
        capture_output=True,
        cwd=lock_path.parent,
    )
    if committed.returncode != 0 or is_patched(json.loads(committed.stdout)):
        raise SystemExit('composer.lock is left patched by an interrupted run and HEAD has no clean copy; restore it by hand.')

    lock_path.write_bytes(committed.stdout)


def install_with_patched_lock(lock_path: Path, backup: Path, clone_dir: Path, run_install) -> int:
    """Patch the lock, run `run_install()`, and put the original bytes back.

    The original is saved to `backup` first, so a run killed past saving can be
    recovered by the next one; SIGTERM and SIGHUP are turned into a normal exit
    so the `finally` below still runs for them.
    """
    original = lock_path.read_bytes()
    lock = json.loads(original)

    backup.write_bytes(original)

    previous = {signum: signal.signal(signum, lambda *_: sys.exit(128 + signum)) for signum in (signal.SIGTERM, signal.SIGHUP)}
    try:
        set_skip_worktree(lock_path, True)
        lock_path.write_text(json.dumps(patch_lock(lock, str(clone_dir)), indent=4, ensure_ascii=False))

        return run_install()
    finally:
        lock_path.write_bytes(original)
        set_skip_worktree(lock_path, False)
        backup.unlink(missing_ok=True)

        for signum, handler in previous.items():
            signal.signal(signum, handler)


def main() -> int:
    lock_path = Path('composer.lock')
    clone_dir = Path(sys.argv[1] if len(sys.argv) > 1 else Path(tempfile.gettempdir()) / 'phpstan-src')
    backup = backup_path_for(lock_path)

    recover_original(lock_path, backup)

    package = locked_package(json.loads(lock_path.read_bytes()))
    clone_locked_commit(package['version'], package['dist']['reference'], clone_dir)

    return install_with_patched_lock(
        lock_path,
        backup,
        clone_dir,
        lambda: subprocess.run(
            ['composer', 'install', '--prefer-source', '--no-interaction', '--no-progress'],
            env={**os.environ, 'COMPOSER_ALLOW_SUPERUSER': '1', 'COMPOSER_PROCESS_TIMEOUT': '0'},
        ).returncode,
    )


if __name__ == '__main__':
    sys.exit(main())

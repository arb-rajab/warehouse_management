"""Install the full composer dependency set (Pint, Pest, PHPStan, Larastan) in
a Claude Code web session, where `composer install` alone cannot.

Why a plain install fails there: every locked package's dist URL is
`api.github.com/repos/<owner>/<repo>/zipball/<ref>`, and the egress proxy
returns 403 for any repository the session does not have attached (so do
codeload.github.com and github.com/<owner>/<repo>/archive, measured). Anonymous
git fetches of public repositories are served, though, and GitHub answers a
shallow fetch of a single commit by its sha. So this script:

  * fetches every locked commit with `git fetch --depth 1 <repo> <ref>`, many at
    once, and turns each into the zip GitHub would have served: `git archive`
    applies the same export-ignore rules the zipball does, and the commit is
    addressed by the sha composer.lock pins, so the content is what the lock
    says. Fetched archives are kept in the archive directory and reused, and
    packages vendor/ already holds at the locked reference are not fetched.
  * points each lock entry's dist at its local zip and runs
    `composer install --prefer-dist`, which installs them as it would any
    download. The git `source` entries stay, so the installed references match
    the real lock and a later `composer install` finds nothing to do.

It replaces `composer install --prefer-source`, which cloned the full history of
every package one after another into composer's cache (~9 minutes and ~7 GB on a
cold container; phpstan's history, which keeps every release phar, did not even
fit composer's process timeout). A package whose fetch fails keeps its original
dist, so composer falls back to cloning its source, the old route, for that one
package only.

composer.lock is patched on disk for the install and restored byte for byte
afterwards, whatever the outcome (including SIGTERM/SIGHUP), so nothing here can
reach a commit; git is told to ignore the lock's working-tree changes
(`--skip-worktree`) for as long as the patch is on disk, so the background
install never shows a dirty tree that something might commit. The pristine copy
is also kept in the system temp directory
while the install runs: a SIGKILL, such as a hook timeout, skips every cleanup,
and the next run uses that copy (or, failing that, `git show HEAD:composer.lock`)
to undo the leftover patch instead of mistaking it for the original. Usage:

    python3 .claude/scripts/install_dev_tools.py [archive-dir]

The archive directory defaults to <system temp>/composer-git-dists. Exits with
composer's own exit code.
"""

import hashlib
import json
import os
import re
import signal
import subprocess
import sys
import tempfile
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

GITHUB = 'https://github.com'
GITHUB_ZIPBALL = re.compile(r'^https://api\.github\.com/repos/([^/]+)/([^/]+)/zipball/([0-9a-f]{40})$')

# Concurrent fetches. Each is a few small requests through the proxy, so the run
# is bound by round trips, not bandwidth: at 16, the ~210 packages of the lock
# installed in ~35s on a cold container.
FETCH_WORKERS = 16
FETCH_TIMEOUT_SECONDS = 300


def locked_packages(lock: dict) -> list[dict]:
    """Every package entry in the lock, runtime and dev."""
    return lock.get('packages', []) + lock.get('packages-dev', [])


def github_commit(package: dict) -> tuple[str, str, str] | None:
    """(owner, repo, sha) for a package locked to a GitHub zipball, else None."""
    match = GITHUB_ZIPBALL.match(package.get('dist', {}).get('url', ''))

    return match.groups() if match else None


def archive_path(archive_dir: Path, owner: str, repo: str, reference: str) -> Path:
    """Where the zip of one locked commit is kept."""
    return archive_dir / owner / repo / f'{reference}.zip'


def archive_locked_commit(owner: str, repo: str, reference: str, archive: Path, base_url: str = GITHUB) -> bool:
    """Write the zip of `reference` to `archive` unless it is already there.

    Returns whether the archive exists afterwards. Failures are not raised: the
    package then keeps its original dist and composer clones its source instead.
    """
    if archive.exists():
        return True

    archive.parent.mkdir(parents=True, exist_ok=True)
    partial = archive.with_name(archive.name + '.partial')
    environment = {**os.environ, 'GIT_TERMINAL_PROMPT': '0'}

    with tempfile.TemporaryDirectory(prefix='composer-git-') as git_dir:
        commands = [
            ['git', 'init', '-q', '--bare', git_dir],
            ['git', '-C', git_dir, 'fetch', '-q', '--depth', '1', f'{base_url}/{owner}/{repo}.git', reference],
            # --prefix gives the single top-level directory a GitHub zipball has,
            # which composer strips; -0 stores rather than deflates, since the zip
            # is unpacked once, locally.
            ['git', '-C', git_dir, 'archive', '--format=zip', '-0', '--prefix=package/', '-o', str(partial), reference],
        ]
        for command in commands:
            try:
                result = subprocess.run(command, capture_output=True, env=environment, timeout=FETCH_TIMEOUT_SECONDS)
            except subprocess.TimeoutExpired:
                result = None

            if result is None or result.returncode != 0:
                partial.unlink(missing_ok=True)

                return False

    partial.replace(archive)

    return True


def installed_references(installed_json: Path) -> dict[str, str]:
    """{package name: dist reference} for what vendor/ already holds; empty without it."""
    try:
        installed = json.loads(installed_json.read_bytes())
    except (OSError, ValueError):
        return {}

    packages = installed.get('packages', []) if isinstance(installed, dict) else installed

    return {package['name']: package.get('dist', {}).get('reference', '') for package in packages}


def fetch_archives(lock: dict, archive_dir: Path, installed: dict[str, str], base_url: str = GITHUB) -> dict[str, str]:
    """Archive every GitHub-locked commit at once; return {package name: zip path}.

    Packages vendor/ already holds at the locked reference are skipped: composer
    leaves them alone, so a warm container or a one-package lock bump fetches
    only what changed.
    """
    jobs = {}
    for package in locked_packages(lock):
        commit = github_commit(package)
        if commit is not None and installed.get(package['name']) != commit[2]:
            jobs[package['name']] = (*commit, archive_path(archive_dir, *commit))

    with ThreadPoolExecutor(max_workers=FETCH_WORKERS) as pool:
        results = pool.map(lambda job: archive_locked_commit(*job, base_url=base_url), jobs.values())

        return {name: str(job[3]) for (name, job), ok in zip(jobs.items(), results) if ok}


def patch_lock(lock: dict, archives: dict[str, str]) -> dict:
    """Return the lock with each archived package's dist swapped for its local zip."""
    for package in locked_packages(lock):
        if package['name'] not in archives:
            continue

        package['dist'] = {
            'type': 'zip',
            'url': archives[package['name']],
            'reference': package['dist']['reference'],
            'shasum': '',
        }

    return lock


def is_patched(lock: dict) -> bool:
    """Whether any dist in the lock still points at a local archive of this script's."""
    return any(not package.get('dist', {}).get('url', 'https://').startswith('https://') for package in locked_packages(lock))


def backup_path_for(lock_path: Path) -> Path:
    """Where the pristine lock is kept while a run is in flight, outside the repo."""
    digest = hashlib.sha1(str(lock_path.resolve()).encode()).hexdigest()[:12]

    return Path(tempfile.gettempdir()) / f'composer.lock.original.{digest}'


def set_skip_worktree(lock_path: Path, enabled: bool) -> None:
    """Hide (or stop hiding) the lock's working-tree changes from git.

    The patch lives on disk for the whole install, in the background, so
    `git status` would show a modified composer.lock the entire time and
    anything that commits "uncommitted changes" (the Stop hook does) would
    commit the patched lock, with paths into /tmp, and break CI. With the flag
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


def install_with_patched_lock(lock_path: Path, backup: Path, archives: dict[str, str], run_install) -> int:
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
        lock_path.write_text(json.dumps(patch_lock(lock, archives), indent=4, ensure_ascii=False))

        return run_install()
    finally:
        lock_path.write_bytes(original)
        set_skip_worktree(lock_path, False)
        backup.unlink(missing_ok=True)

        for signum, handler in previous.items():
            signal.signal(signum, handler)


def main() -> int:
    lock_path = Path('composer.lock')
    archive_dir = Path(sys.argv[1] if len(sys.argv) > 1 else Path(tempfile.gettempdir()) / 'composer-git-dists')
    backup = backup_path_for(lock_path)

    recover_original(lock_path, backup)

    archives = fetch_archives(
        json.loads(lock_path.read_bytes()),
        archive_dir,
        installed_references(Path('vendor/composer/installed.json')),
    )

    return install_with_patched_lock(
        lock_path,
        backup,
        archives,
        # --no-cache: composer would otherwise copy every local zip into its own
        # cache, a second ~550 MB of the same files on a capped disk.
        lambda: subprocess.run(
            ['composer', 'install', '--prefer-dist', '--no-cache', '--no-interaction', '--no-progress'],
            env={**os.environ, 'COMPOSER_ALLOW_SUPERUSER': '1', 'COMPOSER_PROCESS_TIMEOUT': '0'},
        ).returncode,
    )


if __name__ == '__main__':
    sys.exit(main())

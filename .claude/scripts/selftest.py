"""Self-test for the scripts and hooks in this directory.

Nothing else verifies them, and a checker that quietly stops working is worse
than none, because it is trusted. Run this after changing any script here or
the install guard hook:

    python3 .claude/scripts/selftest.py

Exits non-zero if any case failed.
"""

import json
import os
import re
import signal
import subprocess
import sys
import tempfile
from pathlib import Path

SCRIPTS = Path(__file__).parent

DUPLICATED_PHP = """<?php

class Example
{
    public function build(): array
    {
        return [
            'letter' => $this->letter,
            'cells' => $this->cells_count,
            'flats' => $this->flats_count,
            'active' => $this->is_active,
        ];
    }
}
"""

failures: list[str] = []


def run(script: str, *args: str) -> tuple[int, str]:
    """Run a script and return its exit code and combined output."""
    result = subprocess.run(
        [sys.executable, str(SCRIPTS / script), *args],
        capture_output=True,
        text=True,
    )

    return result.returncode, result.stdout + result.stderr


def check(case: str, condition: bool, detail: str = '') -> None:
    """Record a pass or failure for one named case."""
    if condition:
        print(f'  pass  {case}')
        return

    failures.append(case)
    print(f'  FAIL  {case}')
    if detail:
        print(f'        {detail.strip()}')


def main() -> int:
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory)

        duplicated = root / 'duplicated'
        duplicated.mkdir()
        (duplicated / 'One.php').write_text(DUPLICATED_PHP)
        (duplicated / 'Two.php').write_text(DUPLICATED_PHP)

        # The same block twice inside ONE file: a legitimate repeated list far
        # more often than shared logic, so it must not be reported.
        alone = root / 'alone'
        alone.mkdir()
        (alone / 'Solo.php').write_text(DUPLICATED_PHP + DUPLICATED_PHP)

        print('php_duplicate_blocks.py')
        code, out = run('php_duplicate_blocks.py', str(duplicated))
        check('block shared by two files reported', 'One.php' in out and 'Two.php' in out, out)

        code, out = run('php_duplicate_blocks.py', str(alone))
        check('single-file repetition not reported', '0 block(s)' in out, out)

        print('pre_push_check.py')
        sys.path.insert(0, str(SCRIPTS))
        import pre_push_check

        pre_push_check.php_tools_installed = lambda: True
        labels = [label for label, _, _ in pre_push_check.build_steps()]
        check(
            'with the PHP tools installed, every CI step runs in CI order',
            labels == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest', 'Pint', 'PHPStan', 'Clear config cache', 'Pest'],
            str(labels),
        )

        pre_push_check.php_tools_installed = lambda: False
        labels = [label for label, _, _ in pre_push_check.build_steps()]
        check(
            'without the PHP tools, only the JS steps run',
            labels == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest'],
            str(labels),
        )

        print('install-retry-guard.sh')
        guard = SCRIPTS.parent / 'hooks' / 'install-retry-guard.sh'

        def guard_verdict(command: str) -> str:
            result = subprocess.run(
                ['bash', str(guard), 'check'],
                input=json.dumps({'tool_input': {'command': command}, 'session_id': 'selftest-guard'}),
                capture_output=True,
                text=True,
                env={**os.environ, 'CLAUDE_CODE_REMOTE': 'true'},
            )

            return result.stdout

        verdict = guard_verdict('composer install')
        check(
            'bare composer install is denied and points at install_dev_tools.py',
            '"deny"' in verdict and 'install_dev_tools.py' in verdict,
            verdict,
        )
        check('composer install --no-dev is allowed', guard_verdict('composer install --no-dev') == '')
        check('running install_dev_tools.py itself is allowed', guard_verdict('python3 .claude/scripts/install_dev_tools.py') == '')

        print('install_php.sh')
        php_script = SCRIPTS / 'install_php.sh'
        syntax = subprocess.run(['bash', '-n', str(php_script)], capture_output=True, text=True)
        check('script parses', syntax.returncode == 0, syntax.stderr)

        fingerprint = re.search(r"^PPA_KEY_FINGERPRINT='([^']*)'", php_script.read_text(), re.MULTILINE)
        check(
            'PPA key is pinned by a full 40-hex fingerprint',
            fingerprint is not None and re.fullmatch(r'[0-9A-F]{40}', fingerprint.group(1)) is not None,
            str(fingerprint and fingerprint.group(1)),
        )

        # With no php on PATH the check must report "not satisfied" (exit 1) and
        # must not try to install anything: it runs --check, which never does.
        no_php = subprocess.run(
            ['/bin/bash', str(php_script), '--check'],
            capture_output=True,
            text=True,
            env={'PATH': '/nonexistent'},
        )
        check('--check without php is unsatisfied and silent', no_php.returncode == 1 and no_php.stdout == '', no_php.stdout + no_php.stderr)

        print('install_dev_tools.py')
        import install_dev_tools

        lock = {
            'packages-dev': [
                {'name': 'laravel/pint', 'source': {'type': 'git'}, 'dist': {'type': 'zip', 'reference': 'aaa'}},
                {'name': 'phpstan/phpstan', 'source': {'type': 'git'}, 'dist': {'type': 'zip', 'reference': 'bbb'}},
            ],
        }
        patched = install_dev_tools.patch_lock(lock, '/tmp/phpstan-src')
        phpstan, pint = patched['packages-dev'][1], patched['packages-dev'][0]
        check(
            'phpstan dist becomes a path to the clone at the locked reference',
            phpstan['dist'] == {'type': 'path', 'url': '/tmp/phpstan-src', 'reference': 'bbb'},
            str(phpstan),
        )
        check('phpstan source is dropped', 'source' not in phpstan, str(phpstan))
        check('other packages keep their git source and zip dist', pint['source'] == {'type': 'git'} and pint['dist']['type'] == 'zip', str(pint))

        try:
            install_dev_tools.patch_lock({'packages-dev': [pint]}, '/tmp/phpstan-src')
            check('lock without phpstan is refused', False)
        except SystemExit as error:
            check('lock without phpstan is refused', 'not in composer.lock' in str(error), str(error))

        # The lock-restore guarantees, exercised against a real file with a
        # non-ASCII author (the escaping a re-serialised lock would change).
        pristine_lock = json.dumps(
            {
                'packages-dev': [
                    {
                        'name': 'phpstan/phpstan',
                        'version': '2.2.8',
                        'authors': [{'name': 'Márk Sági-Kazár'}],
                        'dist': {'type': 'zip', 'reference': 'bbb'},
                    },
                ],
            },
            indent=4,
            ensure_ascii=False,
        ).encode() + b'\n'
        lock_file = root / 'composer.lock'
        backup_file = root / 'composer.lock.original'
        clone = root / 'phpstan-src'

        def fresh_lock() -> None:
            lock_file.write_bytes(pristine_lock)
            backup_file.unlink(missing_ok=True)

        seen: dict[str, bool] = {}

        def peek_then_return() -> int:
            seen['patched'] = install_dev_tools.is_patched(json.loads(lock_file.read_bytes()))

            return 7

        fresh_lock()
        code = install_dev_tools.install_with_patched_lock(lock_file, backup_file, clone, peek_then_return)
        check('the install runs against the patched lock', seen.get('patched') is True)
        check('the install exit code is returned', code == 7)
        check('the lock is restored byte for byte afterwards', lock_file.read_bytes() == pristine_lock)
        check('the backup is removed after a clean run', not backup_file.exists())

        def explode() -> int:
            raise RuntimeError('composer blew up')

        fresh_lock()
        try:
            install_dev_tools.install_with_patched_lock(lock_file, backup_file, clone, explode)
        except RuntimeError:
            pass
        check('the lock is restored when the install raises', lock_file.read_bytes() == pristine_lock)
        check('the backup is removed when the install raises', not backup_file.exists())

        def terminated() -> int:
            os.kill(os.getpid(), signal.SIGTERM)

            return 0

        fresh_lock()
        try:
            install_dev_tools.install_with_patched_lock(lock_file, backup_file, clone, terminated)
        except SystemExit:
            pass
        check('the lock is restored when the run is sent SIGTERM', lock_file.read_bytes() == pristine_lock)

        # A run killed with SIGKILL leaves the patched lock and the backup.
        lock_file.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), str(clone)), indent=4))
        backup_file.write_bytes(pristine_lock)
        install_dev_tools.recover_original(lock_file, backup_file)
        check('a stale backup restores the lock a killed run left patched', lock_file.read_bytes() == pristine_lock)
        check('the stale backup is consumed', not backup_file.exists())

        # No backup (e.g. the temp directory was wiped): fall back to HEAD.
        repo = root / 'repo'
        repo.mkdir()
        repo_lock = repo / 'composer.lock'
        repo_lock.write_bytes(pristine_lock)
        git_env = {**os.environ, 'GIT_AUTHOR_NAME': 't', 'GIT_AUTHOR_EMAIL': 't@t', 'GIT_COMMITTER_NAME': 't', 'GIT_COMMITTER_EMAIL': 't@t'}
        for command in (['init', '-q'], ['add', 'composer.lock'], ['commit', '-q', '-m', 'lock']):
            subprocess.run(['git', *command], cwd=repo, env=git_env, check=True, capture_output=True)

        repo_lock.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), str(clone)), indent=4))
        install_dev_tools.recover_original(repo_lock, root / 'no-such-backup')
        check('a patched lock with no backup is restored from HEAD', repo_lock.read_bytes() == pristine_lock)

        install_dev_tools.recover_original(repo_lock, root / 'no-such-backup')
        check('a clean lock is left alone', repo_lock.read_bytes() == pristine_lock)

    print()
    if failures:
        print(f'{len(failures)} case(s) failed: {", ".join(failures)}')
        return 1

    print('all cases passed')

    return 0


if __name__ == '__main__':
    sys.exit(main())

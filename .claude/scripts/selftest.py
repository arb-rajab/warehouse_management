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

        print('build_without_fonts.py')
        import build_without_fonts

        stripped = build_without_fonts.fontless_config(build_without_fonts.CONFIG.read_text())
        check(
            'the real vite.config.ts loses the font plugin and keeps the rest',
            'bunny' not in stripped and 'fonts' not in stripped and 'laravel({' in stripped and 'input:' in stripped,
            stripped,
        )

        try:
            build_without_fonts.fontless_config('export default {};\n')
            check('a config without the font plugin is rejected, not built', False)
        except ValueError:
            check('a config without the font plugin is rejected, not built', True)

        print('pre_push_check.py')
        sys.path.insert(0, str(SCRIPTS))
        import pre_push_check

        pre_push_check.php_tools_installed = lambda: True
        labels = [label for label, _, _ in pre_push_check.full_steps()]
        check(
            'with the PHP tools installed, --full runs every CI step in CI order',
            labels == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest', 'Pint', 'PHPStan', 'Clear config cache', 'Pest'],
            str(labels),
        )

        def scoped(*files: str) -> dict[str, list[str]]:
            return {label: command for label, command, _ in pre_push_check.scoped_steps(list(files))}

        check(
            'a docs-only change runs nothing',
            scoped('.ai/rules/index.md', 'AGENTS.md', '.claude/scripts/selftest.py') == {},
        )

        steps = scoped('resources/js/app.ts', 'app/Models/Pallet.php', 'resources/js/deleted-in-this-branch.ts')
        check(
            'a mixed change scopes ESLint, Prettier, Vitest and Pint to the changed files that exist',
            list(steps) == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest', 'Pint', 'PHPStan', 'Clear config cache', 'Pest']
            and steps['ESLint'][-1] == 'resources/js/app.ts'
            and steps['Prettier'][-1] == 'resources/js/app.ts'
            and steps['Vitest'][-1] == 'resources/js/app.ts'
            and steps['Pint'][-1] == 'app/Models/Pallet.php',
            str(steps),
        )

        labels = list(scoped('tests/Pest.php'))
        check(
            'a PHP-test-only change runs only the PHP steps',
            labels == ['Pint', 'PHPStan', 'Clear config cache', 'Pest'],
            str(labels),
        )

        labels = list(scoped('routes/web.php'))
        check(
            'a route change still type-checks the frontend against the regenerated Wayfinder types',
            'vue-tsc' in labels and 'ESLint' not in labels,
            str(labels),
        )

        pre_push_check.php_tools_installed = lambda: False
        labels = [label for label, _, _ in pre_push_check.full_steps()]
        check(
            'without the PHP tools, only the JS steps run',
            labels == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest'],
            str(labels),
        )

        labels = list(scoped('resources/js/app.ts', 'app/Models/Pallet.php'))
        check(
            'without the PHP tools, a scoped run drops the PHP steps',
            labels == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest'],
            str(labels),
        )

        changed = pre_push_check.changed_files('HEAD')
        check('changed_files resolves against a reachable base', isinstance(changed, list), str(changed))
        check(
            'changed_files reports None for a base it cannot reach',
            pre_push_check.changed_files('refs/does-not-exist') is None,
        )

        print('pre_push_check.py --attest')
        workflow = (SCRIPTS.parent.parent / '.github' / 'workflows' / 'tests.yml').read_text()
        check(
            'the MySQL files attest runs are the ones the CI job runs',
            all(path in workflow for path in pre_push_check.MYSQL_TESTS),
        )
        check(
            'attest runs groups in dependency order',
            pre_push_check.ATTEST_ORDER == ('js', 'php', 'mysql', 'e2e', 'audit', 'deploy-script')
            and set(pre_push_check.ATTEST_ORDER) == set(pre_push_check.ci_plan.GROUPS),
        )
        pre_push_check.php_tools_installed = lambda: True
        check(
            'the php group attest runs Pint, PHPStan and Pest in full',
            [label for label, _, _ in pre_push_check.attest_steps('php')] == ['Pint', 'PHPStan', 'Clear config cache', 'Pest'],
        )
        check(
            'the js group attest regenerates the Wayfinder types, then runs the four JS checks unscoped',
            [label for label, _, _ in pre_push_check.attest_steps('js')] == ['Wayfinder types', 'ESLint', 'Prettier', 'vue-tsc', 'Vitest'],
        )
        pre_push_check.php_tools_installed = lambda: False
        check(
            'without the PHP tools the php group is left to CI, not attested',
            pre_push_check.unavailable_reason('php') is not None,
        )

        with tempfile.TemporaryDirectory() as repo_dir:
            repo = Path(repo_dir)
            env = {**os.environ, 'GIT_AUTHOR_NAME': 't', 'GIT_AUTHOR_EMAIL': 't@t', 'GIT_COMMITTER_NAME': 't', 'GIT_COMMITTER_EMAIL': 't@t'}
            git = lambda *args, **kw: subprocess.run(['git', *args], cwd=repo, env=env, capture_output=True, text=True, **kw)
            git('init', '-q')
            (repo / 'a.txt').write_text('one\n')
            git('add', '-A')
            git('commit', '-q', '-m', 'Subject\n\nBody\n\nCo-Authored-By: Someone <s@s>')

            saved_root, saved_env = pre_push_check.ROOT, dict(os.environ)
            pre_push_check.ROOT = repo
            os.environ.update(env)

            try:
                tree = pre_push_check.head_tree()
                trailer = pre_push_check.ci_plan.format_attestation(tree, ['js', 'php'])
                check('a clean work tree has no dirty paths', pre_push_check.dirty_paths() == [])
                check('an unpushed commit is not reported as pushed', not pre_push_check.head_is_pushed())

                check('amending adds the trailer and keeps the tree', pre_push_check.amend_with_trailer(trailer) and pre_push_check.head_tree() == tree)
                message = git('log', '-1', '--format=%B').stdout
                check(
                    'the trailer sits in the existing trailer block and CI reads it back',
                    trailer in message and 'Co-Authored-By: Someone' in message
                    and pre_push_check.ci_plan.attested_groups(message, tree) == {'js', 'php'},
                    message,
                )

                newer = pre_push_check.ci_plan.format_attestation(tree, ['js'])
                pre_push_check.amend_with_trailer(newer)
                message = git('log', '-1', '--format=%B').stdout
                check('re-attesting replaces the earlier trailer', message.count('CI-Verified:') == 1 and newer in message, message)

                (repo / 'a.txt').write_text('two\n')
                (repo / 'b.txt').write_text('new\n')
                check('modified and untracked files make the tree dirty', len(pre_push_check.dirty_paths()) == 2)
                check(
                    'only the modified file counts as a tracked change, not the untracked one',
                    len(pre_push_check.tracked_changes()) == 1 and pre_push_check.tracked_changes()[0].endswith('a.txt'),
                )

                git('add', 'a.txt')
                git('update-index', '--skip-worktree', 'a.txt')
                check('skip-worktree files are reported even though git status hides them', pre_push_check.hidden_paths() == ['a.txt'])
            finally:
                pre_push_check.ROOT = saved_root
                os.environ.clear()
                os.environ.update(saved_env)

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

        # While the lock is patched, git must see a clean tree: the install runs
        # for ~10 minutes in the background and a "commit uncommitted changes"
        # Stop hook would otherwise commit the patched lock.
        def git_output(*arguments: str) -> str:
            return subprocess.run(['git', *arguments], cwd=repo, capture_output=True, text=True).stdout

        during: dict[str, str] = {}

        def inspect_tree_mid_install() -> int:
            during['patched'] = str(install_dev_tools.is_patched(json.loads(repo_lock.read_bytes())))
            during['status'] = git_output('status', '--porcelain')
            during['diff'] = git_output('diff', '--stat')

            return 0

        install_dev_tools.install_with_patched_lock(repo_lock, root / 'repo-backup', clone, inspect_tree_mid_install)
        check('the lock really is patched mid-install', during.get('patched') == 'True', str(during))
        check('git reports a clean tree while the lock is patched', during.get('status') == '' and during.get('diff') == '', str(during))
        check('the lock is back to HEAD afterwards', git_output('status', '--porcelain') == '' and repo_lock.read_bytes() == pristine_lock)
        check('the skip-worktree flag is cleared afterwards', git_output('ls-files', '-v', 'composer.lock').startswith('H'), git_output('ls-files', '-v', 'composer.lock'))

        # A run killed with SIGKILL leaves the flag set and the lock patched.
        subprocess.run(['git', 'update-index', '--skip-worktree', 'composer.lock'], cwd=repo, check=True)
        repo_lock.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), str(clone)), indent=4))
        install_dev_tools.recover_original(repo_lock, root / 'no-such-backup')
        check(
            'recovery clears a stale skip-worktree flag and restores the lock',
            git_output('ls-files', '-v', 'composer.lock').startswith('H') and repo_lock.read_bytes() == pristine_lock,
            git_output('ls-files', '-v', 'composer.lock'),
        )

        print('wait_for_setup.py')
        import wait_for_setup

        def write_state(directory: Path, state: str, pid: int | None = None, summary: str | None = None) -> Path:
            directory.mkdir(parents=True, exist_ok=True)
            (directory / 'state').write_text(state + '\n')
            if pid is not None:
                (directory / 'pid').write_text(f'{pid}\n')
            if summary is not None:
                (directory / 'summary.txt').write_text(summary + '\n')

            return directory

        finished = subprocess.Popen(['true'])
        finished.wait()

        code, message = wait_for_setup.wait(root / 'no-state-here', 5)
        check('no recorded setup is not a failure', code == 0 and 'No session setup' in message, message)

        code, message = wait_for_setup.wait(write_state(root / 's-done', 'done', summary='tools ready'), 5)
        check('a finished setup exits 0 and prints the summary', code == 0 and message == 'tools ready', message)

        code, message = wait_for_setup.wait(write_state(root / 's-failed', 'failed'), 5)
        check('a failed setup exits 1', code == 1 and 'failed' in message, message)

        code, message = wait_for_setup.wait(write_state(root / 's-dead', 'running', pid=finished.pid), 5)
        check('a running marker with a dead pid is reported as interrupted', code == 1 and 'interrupted' in message, message)

        running_dir = write_state(root / 's-live', 'running', pid=os.getpid())
        polls: list[float] = []

        def finish_after_two_polls(seconds: float) -> None:
            polls.append(seconds)
            if len(polls) == 2:
                write_state(running_dir, 'done', summary='finished while waiting')

        code, message = wait_for_setup.wait(running_dir, 600, sleep=finish_after_two_polls)
        check('it waits while the setup is alive, then reports the result', code == 0 and message == 'finished while waiting' and len(polls) == 2, f'{code} {message} {polls}')

        ticks = iter(range(0, 10_000, 5))
        code, message = wait_for_setup.wait(write_state(root / 's-stuck', 'running', pid=os.getpid()), 20, sleep=lambda _: None, clock=lambda: float(next(ticks)))
        check('it gives up at its own timeout', code == 1 and 'Timed out' in message, message)

        cli = subprocess.run(
            [sys.executable, str(SCRIPTS / 'wait_for_setup.py'), '5'],
            capture_output=True,
            text=True,
            env={**os.environ, 'CLAUDE_SETUP_STATE_DIR': str(root / 's-done')},
        )
        check('the command line exits 0 with the summary for a finished setup', cli.returncode == 0 and cli.stdout.strip() == 'tools ready', cli.stdout + cli.stderr)

        print('session-setup-notice.sh')
        notice_script = SCRIPTS.parent / 'hooks' / 'session-setup-notice.sh'

        def notice(state_dir: Path, remote: bool = True) -> str:
            environment = {**os.environ, 'CLAUDE_SETUP_STATE_DIR': str(state_dir)}
            environment.pop('CLAUDE_CODE_REMOTE', None)
            if remote:
                environment['CLAUDE_CODE_REMOTE'] = 'true'

            return subprocess.run(['bash', str(notice_script)], capture_output=True, text=True, env=environment).stdout

        check('a local checkout gets no notice', notice(root / 's-done', remote=False) == '')
        started = notice(root / 'never-started')
        check('a setup that has not started yet gets the wait instructions', 'wait_for_setup.py' in started and '10 minutes' in started, started)
        check('a finished setup gets its summary instead', notice(root / 's-done').strip() == 'tools ready')
        check('a failed setup is called out', 'FAILED' in notice(root / 's-failed'))

        print('session-start.sh (state recording)')
        hook = SCRIPTS.parent / 'hooks' / 'session-start.sh'
        project = root / 'fake-project'
        stub_bin = root / 'stub-bin'
        project.mkdir()
        stub_bin.mkdir()
        (project / '.env.example').write_text('APP_KEY=\n')
        for stub in ('php', 'composer'):
            (stub_bin / stub).write_text('#!/bin/sh\nexit 0\n')
            (stub_bin / stub).chmod(0o755)
        (stub_bin / 'npm').write_text('#!/bin/sh\n[ -n "$STUB_NPM_SLEEP" ] && sleep "$STUB_NPM_SLEEP"\nexit 0\n')
        (stub_bin / 'npm').chmod(0o755)

        def hook_environment(state_dir: Path, **extra: str) -> dict[str, str]:
            return {
                **os.environ,
                'PATH': f'{stub_bin}:{os.environ["PATH"]}',
                'CLAUDE_CODE_REMOTE': 'true',
                'CLAUDE_PROJECT_DIR': str(project),
                'CLAUDE_SETUP_STATE_DIR': str(state_dir),
                **extra,
            }

        done_dir = root / 'hook-done'
        subprocess.run(['bash', str(hook)], capture_output=True, text=True, env=hook_environment(done_dir))
        check('a completed run records done', (done_dir / 'state').read_text().strip() == 'done')
        check('a completed run records the summary the waiter prints', 'Vite build' in (done_dir / 'summary.txt').read_text())
        check('a completed run records its pid', (done_dir / 'pid').read_text().strip().isdigit())

        killed_dir = root / 'hook-killed'
        process = subprocess.Popen(
            ['bash', str(hook)],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            env=hook_environment(killed_dir, STUB_NPM_SLEEP='30'),
            start_new_session=True,
        )
        for _ in range(100):
            if (killed_dir / 'state').exists() and (killed_dir / 'state').read_text().strip() == 'running':
                break
            subprocess.run(['sleep', '0.1'])
        check('a run in progress records running', (killed_dir / 'state').read_text().strip() == 'running')
        process.terminate()
        process.wait(timeout=60)
        check('a run sent SIGTERM records failed, not running', (killed_dir / 'state').read_text().strip() == 'failed', (killed_dir / 'state').read_text())
        try:
            os.killpg(process.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass

    print()
    if failures:
        print(f'{len(failures)} case(s) failed: {", ".join(failures)}')
        return 1

    print('all cases passed')

    return 0


if __name__ == '__main__':
    sys.exit(main())

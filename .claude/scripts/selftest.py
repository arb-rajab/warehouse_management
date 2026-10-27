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
import shlex
import signal
import subprocess
import sys
import tempfile
from pathlib import Path
from unittest.mock import patch

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

        source = build_without_fonts.CONFIG.read_text()
        check('bundled local fonts are preserved', build_without_fonts.fontless_config(source) == source)
        for weight in (400, 500, 600):
            font = SCRIPTS.parents[1] / f'resources/fonts/instrument-sans/instrument-sans-{weight}-normal.woff2'
            check(f'Instrument Sans {weight} is bundled as WOFF2', font.read_bytes()[:4] == b'wOF2')

        legacy = """import { bunny } from 'laravel-vite-plugin/fonts';
export default laravel({
    input: ['resources/js/app.ts'],
    fonts: [
        bunny('Instrument Sans', { weights: [400, 500, 600] }),
    ],
});
"""
        stripped = build_without_fonts.fontless_config(legacy)
        check(
            'legacy Bunny config loses the remote font provider and keeps the rest',
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

        print('install_playwright.py')
        import install_playwright

        browser_root = root / 'browser-project'
        (browser_root / 'node_modules').mkdir(parents=True)
        chromium = browser_root / 'cache/chromium/chrome'
        ffmpeg = browser_root / 'cache/ffmpeg-1011/ffmpeg-linux'
        browser_env = {'PATH': '', 'PLAYWRIGHT_BROWSERS_PATH': str(browser_root / 'cache')}
        installs: list[list[str]] = []

        def make_executable(path: Path) -> None:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('#!/bin/sh\nexit 0\n')
            path.chmod(0o755)

        def install_browser(command: list[str], **kwargs: object) -> subprocess.CompletedProcess:
            installs.append(command)
            make_executable(chromium if command[-1] == 'chromium' else ffmpeg)
            return subprocess.CompletedProcess(command, 0)

        with patch.object(install_playwright, 'SHIPPED_BROWSERS', browser_root / 'shipped'), \
                patch.object(install_playwright, 'runtime_paths', return_value=(chromium, ffmpeg)), \
                patch.object(install_playwright.shutil, 'which', return_value=None), \
                patch.object(install_playwright.subprocess, 'run', side_effect=install_browser):
            ready = install_playwright.ensure_installed(browser_root, browser_env)
            check('a fresh sandbox installs Chromium and FFmpeg', installs == [
                ['npx', '--no-install', 'playwright', 'install', 'chromium'],
                ['npx', '--no-install', 'playwright', 'install', 'ffmpeg'],
            ], str(installs))
            install_playwright.ensure_installed(browser_root, browser_env)
            check('a warm setup installs nothing again', len(installs) == 2, str(installs))
            check('the runner receives the installed Chromium path', ready['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] == str(chromium))
            check('browser discovery does not mutate caller environment', 'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' not in browser_env)

            ffmpeg.unlink()
            installs.clear()
            install_playwright.ensure_installed(browser_root, browser_env)
            check('a missing video encoder installs only FFmpeg', installs == [['npx', '--no-install', 'playwright', 'install', 'ffmpeg']], str(installs))

        ffmpeg.unlink()
        system_chromium = browser_root / 'system chrome'
        system_ffmpeg = browser_root / 'system ffmpeg'
        make_executable(system_chromium)
        make_executable(system_ffmpeg)
        which_browser = lambda name, **kwargs: str(system_chromium if name == 'chromium' else system_ffmpeg) if name in ('chromium', 'ffmpeg') else None
        with patch.object(install_playwright, 'SHIPPED_BROWSERS', browser_root / 'shipped'), \
                patch.object(install_playwright, 'runtime_paths', return_value=(chromium, ffmpeg)), \
                patch.object(install_playwright.shutil, 'which', side_effect=which_browser), \
                patch.object(install_playwright.subprocess, 'run') as installer:
            ready = install_playwright.ensure_installed(browser_root, browser_env)
            check('installed system binaries require no download', installer.call_count == 0)
            check('system FFmpeg is available in the revision-specific cache', ffmpeg.is_symlink() and ffmpeg.resolve() == system_ffmpeg)
            check('a system Chromium path reaches the browser runner', ready['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] == str(system_chromium))
            explicit = install_playwright.browser_environment(browser_root, {**browser_env, 'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH': str(chromium)})
            check('an explicit installed browser takes precedence over system binaries', explicit['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] == str(chromium))
            check('the generated shell exports quote paths with spaces', shlex.quote(str(system_chromium)) in (browser_root / 'node_modules/.playwright-runtime.env').read_text())

        chromium.unlink()
        ffmpeg.unlink()
        env_file = browser_root / 'node_modules/.playwright-runtime.env'
        env_file.unlink()
        with patch.object(install_playwright, 'SHIPPED_BROWSERS', browser_root / 'shipped'), \
                patch.object(install_playwright, 'runtime_paths', return_value=(chromium, ffmpeg)), \
                patch.object(install_playwright.shutil, 'which', return_value=None), \
                patch.object(install_playwright.subprocess, 'run', side_effect=subprocess.CalledProcessError(1, ['npx', 'playwright', 'install'])):
            try:
                install_playwright.ensure_installed(browser_root, browser_env)
                check('a refused download never reports setup ready', False)
            except subprocess.CalledProcessError:
                check('a refused download never reports setup ready', not env_file.exists())

        check('a directory cannot be mistaken for an installed browser', install_playwright.executable(browser_root) is None)

        print('pre_push_check.py')
        pre_push_check.php_tools_installed = lambda: True
        pre_push_check.backend_installed = lambda: False
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

        pre_push_check.php_tools_installed = lambda: True
        pre_push_check.backend_installed = lambda: True
        check(
            'with the app installed, --full regenerates the Wayfinder types before the JS checks',
            [label for label, _, _ in pre_push_check.full_steps()][:2] == ['Wayfinder types', 'ESLint'],
        )
        check(
            'with the app installed, a JS change regenerates the Wayfinder types first',
            list(scoped('resources/js/app.ts'))[:2] == ['Wayfinder types', 'ESLint'],
        )
        check(
            'a PHP-test-only change does not regenerate them (no JS check runs)',
            'Wayfinder types' not in scoped('tests/Pest.php'),
        )
        pre_push_check.backend_installed = lambda: False

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
            'the php lane runs Pint, PHPStan and Pest in full',
            [label for label, _, _ in pre_push_check.attest_steps('php')] == ['Pint', 'PHPStan', 'Pest'],
        )
        e2e = {label: command for label, command, _ in pre_push_check.attest_steps('e2e')}
        with patch.object(pre_push_check.install_playwright, 'browser_environment', return_value={'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH': '/system/chromium'}) as discover:
            env = pre_push_check.e2e_steps()[-1][2]
            check('E2E uses the shared browser discovery rather than a fixed /opt path', env == {'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH': '/system/chromium'} and discover.call_count == 1)
        check(
            'Playwright writes its output outside the work tree so a lane scanning the repo cannot race it',
            any(arg.startswith('--output=') and not arg.startswith('--output=' + str(pre_push_check.ROOT))
                for arg in e2e['Playwright E2E']),
            str(e2e),
        )
        check(
            'the js lane runs the four JS checks unscoped',
            [label for label, _, _ in pre_push_check.attest_steps('js')] == ['ESLint', 'Prettier', 'vue-tsc', 'Vitest'],
        )
        pre_push_check.backend_installed = lambda: True
        prereq = lambda *groups: [label for label, _, _ in pre_push_check.attest_prerequisites(list(groups))]
        check(
            'php and e2e need the Wayfinder files, the Vite build and a cleared config before any lane starts',
            prereq('php', 'e2e') == ['Wayfinder types', 'Build assets', 'Clear config cache'],
            str(prereq('php', 'e2e')),
        )
        check('a js-only attest regenerates the Wayfinder types and builds nothing', prereq('js') == ['Wayfinder types'])
        check('a mysql-only attest generates Wayfinder types and builds assets', prereq('mysql') == ['Wayfinder types', 'Build assets'])
        mysql_job = workflow.split('  test-php-mysql:', 1)[1].split('\n  audit:', 1)[0]
        check(
            'the MySQL CI job installs frontend dependencies and builds before testing',
            mysql_job.index('uses: ./.github/actions/setup-frontend')
            < mysql_job.index('run: npm run build')
            < mysql_job.index('- name: Run database Pest tests on MySQL'),
        )
        check('audit and deploy-script need no prerequisite', prereq('audit', 'deploy-script') == [])
        pre_push_check.backend_installed = lambda: False

        with tempfile.TemporaryDirectory() as lane_dir:
            import time as _time

            sleep = lambda seconds: ('sleep', ['sleep', str(seconds)], None)
            started = _time.monotonic()
            failed = pre_push_check.run_lanes({'a': [sleep(1.5)], 'b': [sleep(1.5)], 'c': [sleep(1.5)]}, log_dir=Path(lane_dir))
            took = _time.monotonic() - started
            check('lanes run at the same time: three 1.5s lanes finish in well under 4.5s', failed is None and took < 3.2, f'{took:.1f}s')

            started = _time.monotonic()
            failed = pre_push_check.run_lanes({'a': [sleep(1.5)], 'b': [sleep(1.5)]}, serial=True, log_dir=Path(lane_dir))
            took = _time.monotonic() - started
            check('--serial runs them one after another', failed is None and took > 2.8, f'{took:.1f}s')

            started = _time.monotonic()
            failed = pre_push_check.run_lanes(
                {'slow': [sleep(30)], 'bad': [sleep(0.5), ('false', ['false'], None), sleep(30)]}, log_dir=Path(lane_dir))
            took = _time.monotonic() - started
            check('a failing lane is reported and stops the others at once', failed == 'bad' and took < 6, f'{failed} {took:.1f}s')

            # A command that starts a child in a session of its own (as Playwright does its web server)
            # must not leave that child behind when another lane fails.
            orphan = ('orphan', ['sh', '-c', 'setsid sleep 47.123 & sleep 47.123'], None)
            pre_push_check.run_lanes({'tree': [orphan], 'bad': [sleep(0.5), ('false', ['false'], None)]}, log_dir=Path(lane_dir))
            _time.sleep(0.5)
            left = subprocess.run(['pgrep', '-f', r'sleep 47\.12[3]'], capture_output=True, text=True).stdout.split()
            check('stopping a lane also stops the children it started in other sessions', left == [], str(left))

            echo = ('echo', ['sh', '-c', 'echo first; echo second'], None)
            pre_push_check.run_lanes({'x': [echo, ('false', ['false'], None)]}, log_dir=Path(lane_dir))
            log = (Path(lane_dir) / 'x.log').read_text()
            check(
                "each lane writes its own log with its steps and the failing step",
                '=== echo ===' in log and 'second' in log and '--- false: FAILED (exit 1)' in log,
                log,
            )

        pre_push_check.php_tools_installed = lambda: False
        check(
            'without the PHP tools the php group is left to CI, not attested',
            pre_push_check.unavailable_reason('php') is not None,
        )

        saved = (pre_push_check.dirty_paths, pre_push_check.hidden_paths, pre_push_check.head_is_pushed)
        pre_push_check.dirty_paths, pre_push_check.hidden_paths, pre_push_check.head_is_pushed = (lambda: []), (lambda: []), (lambda: False)
        try:
            blocker = pre_push_check.attest_blocker
            check('a clean tree, unpushed head and an app change attest by default', blocker(['resources/js/app.ts']) is None)
            check('no known base means no default attest', blocker(None) is not None)
            check('docs-only changes have nothing to attest', blocker(['AGENTS.md', '.ai/rules/ci.md']) is not None)
            check('a change under .github/ never attests, CI ignores the trailer', blocker(['.github/workflows/tests.yml', 'app/Models/Pallet.php']) is not None)

            pre_push_check.dirty_paths = lambda: [' M app/Models/Pallet.php']
            check('uncommitted changes fall back to the scoped checks', blocker(['app/Models/Pallet.php']) is not None)
            pre_push_check.dirty_paths = lambda: []

            pre_push_check.hidden_paths = lambda: ['composer.lock']
            check('a skip-worktree file blocks the default attest', blocker(['app/Models/Pallet.php']) is not None)
            pre_push_check.hidden_paths = lambda: []

            pre_push_check.head_is_pushed = lambda: True
            check('a pushed head commit falls back to the scoped checks', blocker(['app/Models/Pallet.php']) is not None)
        finally:
            pre_push_check.dirty_paths, pre_push_check.hidden_paths, pre_push_check.head_is_pushed = saved

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

        print('install_mysql.sh')
        mysql_script = SCRIPTS / 'install_mysql.sh'
        syntax = subprocess.run(['bash', '-n', str(mysql_script)], capture_output=True, text=True)
        check('MySQL installer parses', syntax.returncode == 0, syntax.stderr)
        mysql_bin = root / 'mysql-bin'
        mysql_bin.mkdir()
        mysql_log = root / 'mysql.log'
        (mysql_bin / 'mysql').write_text(
            '#!/bin/bash\nprintf "%s\\n" "$*" >> "$MYSQL_TEST_LOG"\n'
            'if [[ "$*" == *"CREATE DATABASE"* ]]; then exit "${MYSQL_TEST_CREATE_STATUS:-0}"; fi\n'
            'exit "${MYSQL_TEST_STATUS:-0}"\n'
        )
        (mysql_bin / 'mysql').chmod(0o755)
        for command in ('apt-get', 'service', 'mysqladmin'):
            stub = mysql_bin / command
            stub.write_text('#!/bin/bash\nprintf "unexpected %s\\n" "${0##*/}" >> "$MYSQL_TEST_LOG"\nexit 99\n')
            stub.chmod(0o755)
        mysql_env = {**os.environ, 'PATH': str(mysql_bin), 'MYSQL_TEST_LOG': str(mysql_log)}
        prepared = subprocess.run(['/bin/bash', str(mysql_script)], env=mysql_env, capture_output=True, text=True)
        queries = mysql_log.read_text().splitlines()
        check(
            'an answering prepared server is reused without package or service management',
            prepared.returncode == 0 and queries == [
                '-uroot -ppassword -h127.0.0.1 -e SELECT 1',
                '-uroot -ppassword -h127.0.0.1 -e CREATE DATABASE IF NOT EXISTS warehouse_test;',
            ],
            str(queries) + prepared.stderr,
        )
        mysql_log.write_text('')
        creation_failed = subprocess.run(['/bin/bash', str(mysql_script)], env={**mysql_env, 'MYSQL_TEST_CREATE_STATUS': '1'}, capture_output=True, text=True)
        check(
            'a prepared server that cannot create the test database is not reported as ready',
            creation_failed.returncode != 0 and 'CREATE DATABASE IF NOT EXISTS warehouse_test;' in mysql_log.read_text(),
            creation_failed.stderr,
        )
        for status in ('0', '1'):
            mysql_log.write_text('')
            checked = subprocess.run(['/bin/bash', str(mysql_script), '--check'], env={**mysql_env, 'MYSQL_TEST_STATUS': status}, capture_output=True, text=True)
            check(
                f'MySQL --check returns query status {status} without changing anything',
                checked.returncode == int(status)
                and mysql_log.read_text().splitlines() == ['-uroot -ppassword -h127.0.0.1 -e SELECT 1'],
                checked.stderr,
            )

        print('install_dev_tools.py')
        import install_dev_tools

        sha_a, sha_b = 'a' * 40, 'b' * 40

        def zipball(repository: str, sha: str) -> str:
            return f'https://api.github.com/repos/{repository}/zipball/{sha}'

        lock = {
            'packages': [
                {
                    'name': 'laravel/framework',
                    'source': {'type': 'git', 'url': 'https://github.com/laravel/framework.git', 'reference': sha_a},
                    'dist': {'type': 'zip', 'url': zipball('laravel/framework', sha_a), 'reference': sha_a, 'shasum': ''},
                },
            ],
            'packages-dev': [
                {'name': 'phpstan/phpstan', 'dist': {'type': 'zip', 'url': zipball('phpstan/phpstan', sha_b), 'reference': sha_b, 'shasum': ''}},
                {'name': 'example/elsewhere', 'dist': {'type': 'zip', 'url': 'https://example.com/elsewhere.zip', 'reference': sha_b}},
            ],
        }
        check(
            'a GitHub zipball dist is read as owner, repo and sha',
            install_dev_tools.github_commit(lock['packages'][0]) == ('laravel', 'framework', sha_a),
        )
        check('a dist hosted elsewhere is not a GitHub commit', install_dev_tools.github_commit(lock['packages-dev'][1]) is None)
        check('the committed shape of the lock is not patched', not install_dev_tools.is_patched(lock))

        patched = install_dev_tools.patch_lock(
            json.loads(json.dumps(lock)),
            {'laravel/framework': '/tmp/framework.zip', 'phpstan/phpstan': '/tmp/phpstan.zip'},
        )
        framework, phpstan, elsewhere = patched['packages'][0], patched['packages-dev'][0], patched['packages-dev'][1]
        check(
            'an archived package dist becomes its local zip at the locked reference',
            framework['dist'] == {'type': 'zip', 'url': '/tmp/framework.zip', 'reference': sha_a, 'shasum': ''},
            str(framework),
        )
        check('dev packages are patched too', phpstan['dist']['url'] == '/tmp/phpstan.zip', str(phpstan))
        check(
            'the git source is kept, so installed references match the real lock',
            framework['source'] == lock['packages'][0]['source'],
            str(framework),
        )
        check('a package without an archive keeps its dist', elsewhere == lock['packages-dev'][1], str(elsewhere))
        check('the patched lock is recognised as patched', install_dev_tools.is_patched(patched))

        # A real fetch, offline: a local repository stands in for github.com.
        # The lock pins the first commit, so the archive must hold that one, not
        # the branch tip, with export-ignore applied as a GitHub zipball does.
        upstream = root / 'upstream' / 'acme' / 'tool.git'
        upstream.mkdir(parents=True)
        commit_env = {**os.environ, 'GIT_AUTHOR_NAME': 't', 'GIT_AUTHOR_EMAIL': 't@t', 'GIT_COMMITTER_NAME': 't', 'GIT_COMMITTER_EMAIL': 't@t'}

        def upstream_git(*arguments: str) -> str:
            return subprocess.run(['git', *arguments], cwd=upstream, env=commit_env, check=True, capture_output=True, text=True).stdout

        upstream_git('init', '-q')
        (upstream / 'composer.json').write_text('{"name": "acme/tool"}\n')
        (upstream / 'VERSION').write_text('locked\n')
        (upstream / 'tests').mkdir()
        (upstream / 'tests' / 'ToolTest.php').write_text('<?php\n')
        (upstream / '.gitattributes').write_text('/tests export-ignore\n')
        upstream_git('add', '-A')
        upstream_git('commit', '-q', '-m', 'locked')
        locked_sha = upstream_git('rev-parse', 'HEAD').strip()
        (upstream / 'VERSION').write_text('newer\n')
        upstream_git('commit', '-q', '-am', 'newer')

        fetch_lock = {
            'packages': [
                {'name': 'acme/tool', 'dist': {'type': 'zip', 'url': zipball('acme/tool', locked_sha), 'reference': locked_sha}},
                {'name': 'acme/missing', 'dist': {'type': 'zip', 'url': zipball('acme/missing', sha_a), 'reference': sha_a}},
            ],
        }
        archive_dir = root / 'archives'
        base_url = f'file://{root / "upstream"}'
        archives = install_dev_tools.fetch_archives(fetch_lock, archive_dir, {}, base_url=base_url)
        check('a fetchable commit is archived', set(archives) == {'acme/tool'}, str(archives))
        check('a commit that cannot be fetched is left to composer', 'acme/missing' not in archives, str(archives))
        check('no partial file is left behind', not list(archive_dir.rglob('*.partial')), str(list(archive_dir.rglob('*'))))

        if 'acme/tool' in archives:
            listing = subprocess.run(['unzip', '-Z1', archives['acme/tool']], capture_output=True, text=True).stdout.split()
            version = subprocess.run(['unzip', '-p', archives['acme/tool'], 'package/VERSION'], capture_output=True, text=True).stdout
            check('the archive has one top-level directory, as a GitHub zipball does', all(name.startswith('package/') for name in listing), str(listing))
            check('the archive holds the locked commit, not the branch tip', version == 'locked\n', version)
            check('export-ignore paths are left out, as a GitHub zipball does', not any('tests' in name for name in listing), str(listing))

        check(
            'a package vendor/ holds at the locked reference is not fetched again',
            install_dev_tools.fetch_archives(fetch_lock, root / 'archives-warm', {'acme/tool': locked_sha, 'acme/missing': sha_a}, base_url=base_url) == {},
        )

        installed_json = root / 'installed.json'
        installed_json.write_text(json.dumps({'packages': [{'name': 'acme/tool', 'dist': {'reference': locked_sha}}]}))
        check('installed references are read from installed.json', install_dev_tools.installed_references(installed_json) == {'acme/tool': locked_sha})
        check('no installed.json means nothing is installed', install_dev_tools.installed_references(root / 'absent.json') == {})

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
        archived = {'phpstan/phpstan': str(root / 'phpstan.zip')}

        def fresh_lock() -> None:
            lock_file.write_bytes(pristine_lock)
            backup_file.unlink(missing_ok=True)

        seen: dict[str, bool] = {}

        def peek_then_return() -> int:
            seen['patched'] = install_dev_tools.is_patched(json.loads(lock_file.read_bytes()))

            return 7

        fresh_lock()
        code = install_dev_tools.install_with_patched_lock(lock_file, backup_file, archived, peek_then_return)
        check('the install runs against the patched lock', seen.get('patched') is True)
        check('the install exit code is returned', code == 7)
        check('the lock is restored byte for byte afterwards', lock_file.read_bytes() == pristine_lock)
        check('the backup is removed after a clean run', not backup_file.exists())

        def explode() -> int:
            raise RuntimeError('composer blew up')

        fresh_lock()
        try:
            install_dev_tools.install_with_patched_lock(lock_file, backup_file, archived, explode)
        except RuntimeError:
            pass
        check('the lock is restored when the install raises', lock_file.read_bytes() == pristine_lock)
        check('the backup is removed when the install raises', not backup_file.exists())

        def terminated() -> int:
            os.kill(os.getpid(), signal.SIGTERM)

            return 0

        fresh_lock()
        try:
            install_dev_tools.install_with_patched_lock(lock_file, backup_file, archived, terminated)
        except SystemExit:
            pass
        check('the lock is restored when the run is sent SIGTERM', lock_file.read_bytes() == pristine_lock)

        # A run killed with SIGKILL leaves the patched lock and the backup.
        lock_file.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), archived), indent=4))
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

        repo_lock.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), archived), indent=4))
        install_dev_tools.recover_original(repo_lock, root / 'no-such-backup')
        check('a patched lock with no backup is restored from HEAD', repo_lock.read_bytes() == pristine_lock)

        install_dev_tools.recover_original(repo_lock, root / 'no-such-backup')
        check('a clean lock is left alone', repo_lock.read_bytes() == pristine_lock)

        # While the lock is patched, git must see a clean tree: the install runs
        # in the background and a "commit uncommitted changes"
        # Stop hook would otherwise commit the patched lock.
        def git_output(*arguments: str) -> str:
            return subprocess.run(['git', *arguments], cwd=repo, capture_output=True, text=True).stdout

        during: dict[str, str] = {}

        def inspect_tree_mid_install() -> int:
            during['patched'] = str(install_dev_tools.is_patched(json.loads(repo_lock.read_bytes())))
            during['status'] = git_output('status', '--porcelain')
            during['diff'] = git_output('diff', '--stat')

            return 0

        install_dev_tools.install_with_patched_lock(repo_lock, root / 'repo-backup', archived, inspect_tree_mid_install)
        check('the lock really is patched mid-install', during.get('patched') == 'True', str(during))
        check('git reports a clean tree while the lock is patched', during.get('status') == '' and during.get('diff') == '', str(during))
        check('the lock is back to HEAD afterwards', git_output('status', '--porcelain') == '' and repo_lock.read_bytes() == pristine_lock)
        check('the skip-worktree flag is cleared afterwards', git_output('ls-files', '-v', 'composer.lock').startswith('H'), git_output('ls-files', '-v', 'composer.lock'))

        # A run killed with SIGKILL leaves the flag set and the lock patched.
        subprocess.run(['git', 'update-index', '--skip-worktree', 'composer.lock'], cwd=repo, check=True)
        repo_lock.write_text(json.dumps(install_dev_tools.patch_lock(json.loads(pristine_lock), archived), indent=4))
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
        check('a setup that has not started yet gets the wait instructions', 'wait_for_setup.py' in started and 'two minutes' in started, started)
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
        (stub_bin / 'npm').write_text(
            '#!/bin/sh\n'
            '[ -n "$STUB_NPM_LOG" ] && echo "$*" >> "$STUB_NPM_LOG"\n'
            '[ -n "$STUB_NPM_SLEEP" ] && sleep "$STUB_NPM_SLEEP"\n'
            '[ "$1" = ci ] && mkdir -p node_modules\n'
            'exit 0\n'
        )
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
        check('the setup summary reports browser availability without failing session startup', 'Playwright (Chromium and FFmpeg): unavailable' in (done_dir / 'summary.txt').read_text())
        check('a completed run records its pid', (done_dir / 'pid').read_text().strip().isdigit())
        timings = (done_dir / 'timings.txt').read_text()
        check('a completed run records how long each phase took', 'composer' in timings and 'total' in timings, timings)
        check('browser setup runs after frontend dependencies are ready', 'playwright' in timings, timings)

        # npm ci is skipped while node_modules matches package-lock.json, and
        # runs again once the lock changes.
        npm_log = root / 'npm-calls.log'
        (project / 'package-lock.json').write_text('{"lockfileVersion": 3}\n')

        def npm_ci_runs() -> int:
            return sum(line.startswith('ci') for line in npm_log.read_text().splitlines()) if npm_log.exists() else 0

        for _ in range(2):
            subprocess.run(['bash', str(hook)], capture_output=True, text=True, env=hook_environment(root / 'hook-npm', STUB_NPM_LOG=str(npm_log)))
        check('npm ci is skipped when node_modules already matches the lock', npm_ci_runs() == 1, npm_log.read_text())
        check('a skipped npm ci still reports the frontend ready', 'frontend (vitest, vue-tsc, prettier, eslint): ready' in (root / 'hook-npm' / 'summary.txt').read_text())

        (project / 'package-lock.json').write_text('{"lockfileVersion": 3, "changed": true}\n')
        subprocess.run(['bash', str(hook)], capture_output=True, text=True, env=hook_environment(root / 'hook-npm', STUB_NPM_LOG=str(npm_log)))
        check('npm ci runs again after package-lock.json changes', npm_ci_runs() == 2, npm_log.read_text())

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

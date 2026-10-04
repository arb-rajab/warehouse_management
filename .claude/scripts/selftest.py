"""Self-test for the scripts and hooks in this directory.

Nothing else verifies them, and a checker that quietly stops working is worse
than none, because it is trusted. Run this after changing any script here or
the install guard hook:

    python3 .claude/scripts/selftest.py

Exits non-zero if any case failed.
"""

import json
import os
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

    print()
    if failures:
        print(f'{len(failures)} case(s) failed: {", ".join(failures)}')
        return 1

    print('all cases passed')

    return 0


if __name__ == '__main__':
    sys.exit(main())

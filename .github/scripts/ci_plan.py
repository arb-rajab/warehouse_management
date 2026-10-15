#!/usr/bin/env python3
"""Decide which tests.yml job groups a set of changed files needs.

One classifier for two callers: the `plan` job in .github/workflows/tests.yml
(fed a pull request's changed files) and .claude/scripts/pre_push_check.py
(fed the branch's local changes), so the sandbox and CI agree on what a change
touches.

Groups:
  php            Pint, PHPStan, Pest, Pest (MySQL migrations)
  js             Wayfinder, ESLint, Prettier, vue-tsc, Vitest
  e2e            Playwright
  audit          composer audit / npm audit
  deploy-script  the deploy-remote.sh test

Rules, deliberately coarse (a missed skip costs minutes; a wrong skip lets a
regression through):
  - Agent and documentation files (.ai/, .claude/, root-level *.md) need
    nothing: no test, build or check reads them.
  - Anything under .github/ needs everything, so a workflow, action or CI
    script change is always exercised by the run that introduces it.
  - Every other file needs `php`. Pest reads more than PHP: Inertia's
    `ensure_pages_exist` resolves resources/js/pages, and
    SettingsReconciliationTest scans every file under resources/js.
  - PHP test and tooling config (tests/Feature/, tests/Pest.php,
    tests/TestCase.php, phpunit.xml, phpstan.neon, pint.json) cannot reach the
    browser or the generated Wayfinder types, so it needs only `php`.
    tests/Fixtures/ is not in that list: playwright.config.ts seeds from it.
  - Vitest files (resources/js/**/*.test.ts) are never bundled, so they need
    `php` and `js` but not `e2e`.
  - Everything else needs `php`, `js` and `e2e`; dependency manifests also need
    `audit`.

Usage (CI): changed paths on stdin, one per line; prints `group=true|false`
lines for $GITHUB_OUTPUT. An empty list prints all-true: an empty answer from
the files API is more likely a fault than a change touching nothing.
    python3 .github/scripts/ci_plan.py < changed-files.txt
    python3 .github/scripts/ci_plan.py --all    # every group
    python3 .github/scripts/ci_plan.py --none   # no group
"""

import sys
from collections.abc import Iterable

GROUPS = ('php', 'js', 'e2e', 'audit', 'deploy-script')

NO_CI_PREFIXES = ('.ai/', '.claude/')

PHP_ONLY_PREFIXES = ('tests/Feature/',)

PHP_ONLY_FILES = frozenset({
    'tests/Pest.php',
    'tests/TestCase.php',
    'phpunit.xml',
    'phpstan.neon',
    'pint.json',
})

DEPENDENCY_FILES = frozenset({
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
})


def needs_no_ci(path: str) -> bool:
    """Whether nothing CI runs can read this file."""
    return path.startswith(NO_CI_PREFIXES) or ('/' not in path and path.endswith('.md'))


def is_php_only(path: str) -> bool:
    """Whether only the PHP jobs can observe a change to this file."""
    return path.startswith(PHP_ONLY_PREFIXES) or path in PHP_ONLY_FILES


def is_vitest_file(path: str) -> bool:
    """Whether this is a Vitest file, which the Vite build never bundles."""
    return path.startswith('resources/js/') and path.endswith('.test.ts')


def plan(paths: Iterable[str]) -> dict[str, bool]:
    """Return which groups the changed paths need."""
    needed = dict.fromkeys(GROUPS, False)

    for path in paths:
        if needs_no_ci(path):
            continue

        if path.startswith('.github/'):
            return dict.fromkeys(GROUPS, True)

        needed['php'] = True

        if path in DEPENDENCY_FILES:
            needed['audit'] = True

        if is_php_only(path):
            continue

        needed['js'] = True

        if not is_vitest_file(path):
            needed['e2e'] = True

    return needed


def main(argv: list[str]) -> int:
    if '--all' in argv:
        needed = dict.fromkeys(GROUPS, True)
    elif '--none' in argv:
        needed = dict.fromkeys(GROUPS, False)
    else:
        paths = [line.strip() for line in sys.stdin if line.strip()]
        needed = plan(paths) if paths else dict.fromkeys(GROUPS, True)

    for group, value in needed.items():
        print(f'{group}={str(value).lower()}')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

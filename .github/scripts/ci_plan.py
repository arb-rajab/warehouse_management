#!/usr/bin/env python3
"""Decide which tests.yml job groups a set of changed files needs.

One classifier for two callers: the `plan` job in .github/workflows/tests.yml
(fed a pull request's changed files) and .claude/scripts/pre_push_check.py
(fed the branch's local changes), so the sandbox and CI agree on what a change
touches.

Groups:
  php            Pint, PHPStan, Pest
  mysql          Pest (MySQL migrations)
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
  - The MySQL job runs only the migration/schema tests, so it is needed only
    when something that can change them does: database/, tests/Feature/Database/,
    config/database.php and composer.json/composer.lock.
  - Everything else needs `php`, `js` and `e2e`; dependency manifests also need
    `audit`.

Attestation: .claude/scripts/pre_push_check.py --attest runs the planned groups
locally and, when they all pass, records a trailer on the head commit:

    CI-Verified: tree=<head tree sha> groups=js,php,e2e

A trailer vouches only for the groups it lists and only for the tree it names,
so any later content change makes it stale. `--attested-message-file` and
`--tree` let the `plan` job subtract those groups for a pull request whose
merged tree equals its head tree. It is ignored when the change touches
.github/, so a workflow change is still exercised by the run that introduces it.
With --require-attestation, ordinary code changes fail instead of falling back
to paid CI when any required group is missing or the merged tree differs.

Usage (CI): changed paths on stdin, one per line; prints `group=true|false`
lines for $GITHUB_OUTPUT. An empty list prints all-true: an empty answer from
the files API is more likely a fault than a change touching nothing.
    python3 .github/scripts/ci_plan.py < changed-files.txt
    python3 .github/scripts/ci_plan.py --all    # every group
    python3 .github/scripts/ci_plan.py --none   # no group
    python3 .github/scripts/ci_plan.py --attested-message-file msg.txt --tree <sha> < changed-files.txt
    python3 .github/scripts/ci_plan.py --require-attestation --attested-message-file msg.txt --tree <sha> --merged-tree <sha> < changed-files.txt
"""

import re
import sys
from collections.abc import Iterable
from pathlib import Path

GROUPS = ('php', 'mysql', 'js', 'e2e', 'audit', 'deploy-script')

NO_CI_PREFIXES = ('.ai/', '.claude/')

PHP_ONLY_PREFIXES = ('tests/Feature/',)

PHP_ONLY_FILES = frozenset({
    'tests/Pest.php',
    'tests/TestCase.php',
    'phpunit.xml',
    'phpstan.neon',
    'pint.json',
})

MYSQL_PREFIXES = ('database/', 'tests/Feature/Database/')

MYSQL_FILES = frozenset({'config/database.php', 'composer.json', 'composer.lock'})

DEPENDENCY_FILES = frozenset({
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
})


ATTESTATION = re.compile(r'^CI-Verified: tree=([0-9a-f]{40,64}) groups=([a-z0-9,-]*)$')


def format_attestation(tree: str, groups: Iterable[str]) -> str:
    """The commit-message trailer that vouches for `groups` on `tree`."""
    return f"CI-Verified: tree={tree} groups={','.join(group for group in GROUPS if group in set(groups))}"


def attested_groups(message: str, tree: str) -> frozenset[str]:
    """The groups a commit message vouches for on `tree`; the last matching trailer wins."""
    attested: frozenset[str] = frozenset()

    for line in message.splitlines():
        match = ATTESTATION.match(line.strip())

        if match and match.group(1) == tree:
            attested = frozenset(group for group in match.group(2).split(',') if group in GROUPS)

    return attested


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

        if path.startswith(MYSQL_PREFIXES) or path in MYSQL_FILES:
            needed['mysql'] = True

        if path in DEPENDENCY_FILES:
            needed['audit'] = True

        if is_php_only(path):
            continue

        needed['js'] = True

        if not is_vitest_file(path):
            needed['e2e'] = True

    return needed


def option(argv: list[str], name: str) -> str | None:
    """The value after `name` in argv, or None when the option is absent."""
    return argv[argv.index(name) + 1] if name in argv and argv.index(name) + 1 < len(argv) else None


def main(argv: list[str]) -> int:
    if '--require-attestation' in argv and ('--all' in argv or '--none' in argv):
        print('Cannot override the required attestation with --all or --none.', file=sys.stderr)
        return 1

    if '--all' in argv:
        needed = dict.fromkeys(GROUPS, True)
    elif '--none' in argv:
        needed = dict.fromkeys(GROUPS, False)
    else:
        paths = [line.strip() for line in sys.stdin if line.strip()]
        needed = plan(paths) if paths else dict.fromkeys(GROUPS, True)
        message_file, tree = option(argv, '--attested-message-file'), option(argv, '--tree')

        if '--require-attestation' in argv:
            error = None
            if not paths:
                error = 'The changed-file list is empty; cannot verify attestation coverage.'
            elif any(needed.values()) and not any(path.startswith('.github/') for path in paths):
                merged_tree = option(argv, '--merged-tree')
                if not tree or not merged_tree or merged_tree != tree:
                    error = 'The merged tree differs from the PR head or its hash is unavailable. Update the branch first.'
                else:
                    message = Path(message_file).read_text() if message_file else ''
                    vouched = attested_groups(message, tree)
                    missing = [group for group in GROUPS if needed[group] and group not in vouched]
                    if missing:
                        error = f'Missing or stale CI-Verified attestation for: {", ".join(missing)}.'

            if error:
                print(f'{error}\nCommit all changes, then run '
                      'python3 .claude/scripts/pre_push_check.py --attest '
                      'and push the amended commit. Do not write the trailer by hand.', file=sys.stderr)
                return 1

        # Never for a change under .github/: it must run everything it touches.
        if paths and message_file and tree and not any(path.startswith('.github/') for path in paths):
            vouched = attested_groups(Path(message_file).read_text(), tree)
            skipped = sorted(group for group in vouched if needed[group])

            for group in skipped:
                needed[group] = False

            if skipped:
                print(f"Skipping {', '.join(skipped)}: the head commit attests a local run on tree {tree}.", file=sys.stderr)

    for group, value in needed.items():
        print(f'{group}={str(value).lower()}')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

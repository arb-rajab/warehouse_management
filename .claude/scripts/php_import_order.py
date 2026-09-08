"""Check PHP `use` statement ordering the way Pint's `ordered_imports` does.

Pint's laravel preset sorts imports alphabetically, case-INSENSITIVELY. Sorting
with a plain `sort` is case-sensitive and reports false positives on pairs like
`Date` / `DB`, which is exactly the mistake that made this check look unreliable
the first time it was written.

Usage:
    python3 .claude/scripts/php_import_order.py app/**/*.php
    python3 .claude/scripts/php_import_order.py $(git diff --name-only --diff-filter=d | grep '\.php$')

Exits non-zero when any file is out of order. Validated against every PHP file
in app/ and tests/ at 0 false positives while Pint was passing on develop.

Limitation: reads only top-level `use X;` lines, so grouped imports
(`use A\{B, C};`) and trait `use` statements inside a class body are ignored —
neither appears in this codebase.
"""

import sys


def violations(path: str) -> list[tuple[str, str]]:
    """Return (actual, expected) pairs for each mis-ordered import in a file."""
    with open(path, encoding='utf-8') as handle:
        names = [
            line[4:].rstrip().rstrip(';')
            for line in handle
            if line.startswith('use ')
        ]

    expected = sorted(names, key=str.lower)

    return [(a, b) for a, b in zip(names, expected) if a != b]


def main(paths: list[str]) -> int:
    bad = 0

    for path in paths:
        found = violations(path)
        if not found:
            continue

        bad += 1
        print(f'  {path}')
        for actual, wanted in found:
            print(f'      has {actual!r} where {wanted!r} expected')

    print(f'{bad} file(s) with import-order violations')

    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

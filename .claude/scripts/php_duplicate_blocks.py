"""Find identical runs of PHP lines repeated across two or more files.

This is the tool that surfaced the cross-file duplication behind the Concerns
extracted in #152 — the kind of repetition that is invisible when reading one
file at a time. Reach for it when doing a cross-cutting review against the
duplication threshold in CLAUDE.md, not on every edit.

Usage:
    python3 .claude/scripts/php_duplicate_blocks.py app
    python3 .claude/scripts/php_duplicate_blocks.py app/Http/Requests 8

Whitespace is normalised before comparison. Runs that are mostly braces,
comments or `use` lines are skipped, since those match everywhere and say
nothing. Only blocks spanning 2+ distinct files are reported — a block repeated
within one file is usually a legitimate list, not shared logic.

The output is a starting point, not a verdict: CLAUDE.md's threshold is 2+ real
call sites for the SPECIFIC symbol, and structurally similar code is not proof
of shared logic. Read each hit before extracting anything.
"""

import collections
import os
import sys

NOISE_PREFIXES = ('*', '//', '/*', 'use ', '}', '{')


def is_noise(line: str) -> bool:
    """True for lines that match across unrelated files without meaning anything."""
    return not line or line.startswith(NOISE_PREFIXES)


def duplicate_blocks(root: str, window: int) -> dict[str, list[str]]:
    """Map each repeated block of `window` lines to the locations it appears at."""
    blocks: dict[str, list[str]] = collections.defaultdict(list)

    for directory, _, filenames in os.walk(root):
        for filename in filenames:
            if not filename.endswith('.php'):
                continue

            path = os.path.join(directory, filename)
            with open(path, encoding='utf-8') as handle:
                normalised = [' '.join(line.split()) for line in handle]

            for start in range(len(normalised) - window + 1):
                run = normalised[start:start + window]

                if any(is_noise(line) for line in run):
                    continue

                blocks['\n'.join(run)].append(f'{path}:{start + 1}')

    return blocks


def main(argv: list[str]) -> int:
    root = argv[0] if argv else 'app'
    window = int(argv[1]) if len(argv) > 1 else 5
    found = 0

    blocks = duplicate_blocks(root, window)

    for block, locations in sorted(blocks.items(), key=lambda item: -len(item[1])):
        files = {location.rsplit(':', 1)[0] for location in locations}

        if len(files) < 2:
            continue

        found += 1
        print(f'--- {len(locations)} occurrences across {len(files)} files')
        for location in locations:
            print(f'    {location}')
        for line in block.split('\n'):
            print(f'      | {line[:120]}')
        print()

    print(f'{found} block(s) of {window}+ lines repeated across files under {root}/')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

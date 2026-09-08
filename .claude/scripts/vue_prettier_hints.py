"""Approximate two Prettier decisions for a Vue file, for when Prettier can't run.

Advisory only. Prettier is the authority and CI is where the real answer comes
from; this exists so an obvious `format:check` failure gets caught before a push
rather than after a CI round. Both checks over-report.

  1. Lines wider than printWidth (80). Many are unavoidable — a single long
     string or class attribute has nowhere to break — so each is labelled
     rather than failed.
  2. Multi-line element openings whose collapsed form would fit in 80 columns.
     Prettier always collapses those, so these are the closest thing here to a
     real finding.

Usage:
    python3 .claude/scripts/vue_prettier_hints.py resources/js/pages/Admin/Rows/Show.vue

Always exits 0.
"""

import sys

PRINT_WIDTH = 80
MAX_OPENING_LINES = 12
UNBREAKABLE_PREFIXES = ('class="', ':title=', 'import ', '* ', '/**', '"', "'")


def over_width_lines(lines: list[str]) -> list[tuple[int, int, bool, str]]:
    """Return (line number, width, looks-unbreakable, text) for each wide line."""
    return [
        (
            number,
            len(line),
            line.strip().startswith(UNBREAKABLE_PREFIXES),
            line.strip(),
        )
        for number, line in enumerate(lines, 1)
        if len(line) > PRINT_WIDTH
    ]


def collapsible_openings(lines: list[str]) -> list[tuple[int, str]]:
    """Return (line number, collapsed form) for openings Prettier would join."""
    found: list[tuple[int, str]] = []
    index = 0

    while index < len(lines):
        stripped = lines[index].strip()
        opens_element = (
            stripped.startswith('<')
            and not stripped.startswith('</')
            and not stripped.endswith('>')
        )

        if opens_element:
            indent = len(lines[index]) - len(lines[index].lstrip())
            parts = [stripped]
            end = index + 1

            while (
                end < len(lines)
                and lines[end].strip() not in ('>', '/>')
                and end - index <= MAX_OPENING_LINES
            ):
                parts.append(lines[end].strip())
                end += 1

            if end < len(lines) and lines[end].strip() in ('>', '/>'):
                suffix = '>' if lines[end].strip() == '>' else ' />'
                collapsed = ' ' * indent + ' '.join(parts) + suffix

                if len(collapsed) <= PRINT_WIDTH:
                    found.append((index + 1, collapsed.strip()))

                index = end

        index += 1

    return found


def main(path: str) -> int:
    with open(path, encoding='utf-8') as handle:
        lines = handle.read().split('\n')

    print(f'--- lines over {PRINT_WIDTH} columns')
    for number, width, unbreakable, text in over_width_lines(lines):
        label = 'probably unavoidable' if unbreakable else 'CHECK'
        print(f'  {number}: {width} cols ({label}) {text[:70]}')

    print(f'--- multi-line openings that fit in {PRINT_WIDTH} collapsed')
    for number, collapsed in collapsible_openings(lines):
        print(f'  {number}: {len(collapsed)} cols ==> {collapsed[:70]}')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1]))

"""Smoke-check that a .ts/.js/.vue file is bracket-balanced.

Stands in for `eslint` / `vue-tsc` when node_modules is absent (see the sandbox
section of CLAUDE.md). It will not catch a type error or a lint violation — it
catches the one class of mistake that is both easy to make when editing a file
with `sed`/heredocs and expensive to find in CI: an unclosed brace.

Usage:
    python3 .claude/scripts/js_balance.py resources/js/pages/Admin/Rows/Show.vue
    for f in $(git diff --name-only --diff-filter=d | grep -E '\.(ts|js|vue)$'); do
        python3 .claude/scripts/js_balance.py "$f" || break
    done

Exits non-zero on the first problem found.

Known limitations, all of which produce false POSITIVES rather than silence:
  - A regex literal containing an unmatched bracket (`/[^{]/`) is read as
    division followed by code.
  - A backtick nested inside a `${...}` interpolation ends the template literal
    early.
  - An apostrophe in Vue template prose (`<p>Don't</p>`) opens a string that
    runs to the next quote. This codebase keeps user-facing copy in lang/, so
    it rarely bites; if it does, the file is fine and this check is wrong.
"""

import sys

OPENERS = '([{'
CLOSERS = ')]}'
MATCHING = {')': '(', ']': '[', '}': '{'}
QUOTES = '"\'`'


def strip_strings_and_comments(src: str) -> str:
    """Drop string literals and comments so only structural brackets remain.

    Comments are recognised BEFORE quotes: the other order makes an apostrophe
    inside a `// don't do this` comment open a string literal that swallows the
    rest of the file.
    """
    out: list[str] = []
    i, n = 0, len(src)

    while i < n:
        if src.startswith('//', i):
            newline = src.find('\n', i)
            i = n if newline < 0 else newline
            continue

        if src.startswith('/*', i):
            end = src.find('*/', i)
            i = n if end < 0 else end + 2
            continue

        char = src[i]

        if char in QUOTES:
            i += 1
            while i < n and src[i] != char:
                i += 2 if src[i] == '\\' else 1
            i += 1
            continue

        out.append(char)
        i += 1

    return ''.join(out)


def first_imbalance(src: str) -> str | None:
    """Return a description of the first unbalanced bracket, or None if clean."""
    stack: list[str] = []

    for char in strip_strings_and_comments(src):
        if char in OPENERS:
            stack.append(char)
        elif char in CLOSERS:
            if not stack or stack[-1] != MATCHING[char]:
                return f'unexpected {char!r}'
            stack.pop()

    return f'unclosed {stack[-1]!r}' if stack else None


def main(path: str) -> int:
    with open(path, encoding='utf-8') as handle:
        src = handle.read()

    if path.endswith('.vue') and '<template' not in src:
        print(f'{path}: no <template> block')
        return 1

    problem = first_imbalance(src)

    if problem is not None:
        print(f'{path}: {problem}')
        return 1

    print(f'{path}: ok')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1]))

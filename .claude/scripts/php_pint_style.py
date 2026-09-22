"""Check PHP files against a handful of Pint fixers beyond import ordering.

`php_import_order.py` already covers `ordered_imports`; this script covers
other `laravel` preset fixers that are cheap and reliable to approximate with
line-based checks, without needing Pint's actual tokenizer:

  - `array_syntax` (long `array(...)` instead of short `[...]`)
  - `no_trailing_whitespace` (trailing spaces/tabs at end of a line)
  - `single_blank_line_at_eof` (missing final newline, or 2+ blank lines
    trailing the file)
  - `no_closing_tag` (a stray `?>` at the end of a pure-PHP file)
  - `trailing_comma_in_multiline` for array literals only — a multi-line
    `[...]` whose last element line has no trailing comma before the closing
    `]`

Usage:
    python3 .claude/scripts/php_pint_style.py app/Models/Product.php
    python3 .claude/scripts/php_pint_style.py $(git diff --name-only --diff-filter=d | grep '\.php$')

Exits non-zero when any file has a violation.

Known limitations (all false NEGATIVES — this under-reports rather than
crying wolf):
  - `array_syntax` is a plain regex search for `array(` not preceded by a
    word character, `$`, `->` or `::` (to skip calls like `$request->array()`
    or `Foo::array()`), and skips lines that look like a `//`/`*`/`/*` comment.
    It will still miss `array (` split across a line break, and treats
    `array(` inside a string literal on an otherwise-code line as code.
  - The trailing-comma check only looks at `]` (array literals). Pint's
    `trailing_comma_in_multiline` also applies to multi-line function
    arguments/parameters, `match` arms, and grouped `use` imports, none of
    which this script checks, because `)` closes far too many unrelated
    constructs (control structures, closures) to line-check reliably. A
    trailing `// comment` on the last element line is stripped (respecting
    quotes) before checking for the comma, so an inline comment after the
    last element does not cause a false positive.
  - A `]` closing a multi-line array whose last line is a comment, or that
    itself opens another bracket, is skipped rather than guessed at.
"""

import re
import sys

ARRAY_SYNTAX = re.compile(r'(?<![\w$>:])array\s*\(')


def array_syntax_violations(lines: list[str]) -> list[int]:
    """Return line numbers using long `array(...)` syntax."""
    return [
        n
        for n, line in enumerate(lines, 1)
        if not line.strip().startswith(('//', '*', '/*'))
        and ARRAY_SYNTAX.search(line)
    ]


def trailing_whitespace_violations(lines: list[str]) -> list[int]:
    """Return line numbers with trailing spaces or tabs."""
    return [n for n, line in enumerate(lines, 1) if line != line.rstrip(' \t')]


def eof_violation(src: str) -> str | None:
    """Return a description of the end-of-file blank-line problem, if any."""
    if not src.endswith('\n'):
        return 'missing trailing newline'
    stripped = src.rstrip('\n')
    trailing_newlines = len(src) - len(stripped)
    if trailing_newlines > 1:
        return f'{trailing_newlines} trailing blank lines (Pint wants exactly 1)'
    return None


def closing_tag_violation(src: str) -> bool:
    """True when the file ends with a stray `?>` closing tag."""
    return src.rstrip().endswith('?>')


def strip_trailing_line_comment(line: str) -> str:
    """Drop a trailing `// ...` comment, respecting string literals on the line."""
    in_string: str | None = None
    i, n = 0, len(line)

    while i < n:
        char = line[i]

        if in_string:
            if char == '\\':
                i += 2
                continue
            if char == in_string:
                in_string = None
            i += 1
            continue

        if char in ('"', "'"):
            in_string = char
            i += 1
            continue

        if line[i:i + 2] == '//':
            return line[:i]

        i += 1

    return line


def array_trailing_comma_violations(lines: list[str]) -> list[int]:
    """Return line numbers of `]` closing a multi-line array with no trailing comma."""
    found = []

    for index, line in enumerate(lines):
        stripped = line.strip()
        if stripped not in (']', '],', '];'):
            continue
        if index == 0:
            continue

        previous = strip_trailing_line_comment(lines[index - 1]).rstrip()
        if not previous or previous.endswith((',', '[', '(', '*/')):
            continue
        if previous.strip().startswith(('//', '*', '/*')):
            continue

        found.append(index + 1)

    return found


def main(paths: list[str]) -> int:
    bad_files = 0

    for path in paths:
        with open(path, encoding='utf-8') as handle:
            src = handle.read()
        lines = src.split('\n')

        problems: list[str] = []

        for number in array_syntax_violations(lines):
            problems.append(f'  {number}: uses array(...) instead of [...]')

        for number in trailing_whitespace_violations(lines):
            problems.append(f'  {number}: trailing whitespace')

        eof_problem = eof_violation(src)
        if eof_problem:
            problems.append(f'  EOF: {eof_problem}')

        if closing_tag_violation(src):
            problems.append('  EOF: stray closing ?> tag')

        for number in array_trailing_comma_violations(lines):
            problems.append(f'  {number}: multi-line array missing trailing comma')

        if problems:
            bad_files += 1
            print(f'{path}')
            for problem in problems:
                print(problem)

    print(f'{bad_files} file(s) with Pint style violations')

    return 1 if bad_files else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

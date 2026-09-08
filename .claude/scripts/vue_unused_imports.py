"""Report imports in a `<script setup>` block that nothing in the file uses.

Stands in for eslint's `no-unused-vars` when node_modules is absent. Checks the
rest of the script body AND the template, since a component or helper is very
often referenced only from the template.

Usage:
    python3 .claude/scripts/vue_unused_imports.py resources/js/pages/Admin/Rows/Show.vue

Always exits 0 — this is advisory. Matching is by word boundary, so a name that
appears in a comment or a string counts as "used" and the check under-reports
rather than sending you to delete a live import.

Limitation: recognises the Prettier-formatted shape this codebase uses —
single-quoted specifier, statement ending in `;` at end of line. A double-quoted
or unterminated import is skipped, not misreported.
"""

import re
import sys

IMPORT_STATEMENT = re.compile(r"^import\b[\s\S]*?from\s+'[^']+';$", re.M)
IMPORT_CLAUSE = re.compile(r'import\s+(?:type\s+)?([\s\S]*?)\s+from')
SCRIPT_SETUP = re.compile(r'<script setup[^>]*>(.*?)</script>', re.S)


def imported_names(statement: str) -> list[str]:
    """Extract the locally-bound names a single import statement introduces."""
    clause = IMPORT_CLAUSE.match(statement).group(1).strip()

    if not clause.startswith('{'):
        return [clause]

    return [
        entry.strip().split(' as ')[-1].strip()
        for entry in clause.strip('{}').split(',')
        if entry.strip()
    ]


def unused_imports(src: str) -> list[str]:
    """Return names imported by the <script setup> block that go unreferenced."""
    block = SCRIPT_SETUP.search(src)

    if block is None:
        return []

    script = block.group(1)
    template = src.replace(script, '', 1)
    statements = IMPORT_STATEMENT.findall(script)

    body = script
    for statement in statements:
        body = body.replace(statement, '')

    names = [name for statement in statements for name in imported_names(statement)]

    return [
        name for name in names
        if not re.search(rf'\b{re.escape(name)}\b', body)
        and not re.search(rf'\b{re.escape(name)}\b', template)
    ]


def main(path: str) -> int:
    with open(path, encoding='utf-8') as handle:
        unused = unused_imports(handle.read())

    print(f'{path}: {", ".join(unused) if unused else "no unused imports"}')

    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1]))

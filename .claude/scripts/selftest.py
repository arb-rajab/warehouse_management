"""Self-test for the checkers in this directory.

These scripts stand in for Pint/eslint/Prettier when vendor/ and node_modules
are absent, which means nothing else verifies them — a checker that quietly
stops working is worse than no checker, because it is trusted. Run this after
changing any script here:

    python3 .claude/scripts/selftest.py

Exits non-zero on the first failed case.
"""

import subprocess
import sys
import tempfile
from pathlib import Path

SCRIPTS = Path(__file__).parent

ORDERED_PHP = """<?php

use Illuminate\\Support\\Facades\\Date;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Password;
"""

UNORDERED_PHP = """<?php

use Illuminate\\Http\\Request;
use Illuminate\\Database\\Eloquent\\Model;
"""

BALANCED_TS = """export function total(rows: number[]): number {
    // don't trust the caller's ordering
    return rows.reduce((carry, row) => carry + row, 0);
}
"""

UNBALANCED_TS = """export function total(rows: number[]): number {
    return rows.reduce((carry, row) => carry + row, 0);
"""

VUE_WITH_APOSTROPHE_COMMENT = """<script setup lang="ts">
import { computed } from 'vue';
import Unused from '@/components/Unused.vue';

// don't resolve this eagerly
const label = computed(() => 'ok');
</script>

<template>
    <p>{{ label }}</p>
</template>
"""

VUE_COLLAPSIBLE = """<template>
    <Badge
        :active="true"
    />
</template>
"""

CLEAN_PINT_PHP = """<?php

class Example
{
    public function scopes(): array
    {
        return [
            'active',
            'published', // trailing comment, not a violation
        ];
    }

    public function ids(Request $request): array
    {
        return array_map('intval', $request->array('ids'));
    }
}
"""

ARRAY_SYNTAX_PHP = "<?php\n\nfunction scopes(): array\n{\n    return array('active');\n}\n"

TRAILING_WHITESPACE_PHP = "<?php\n\n$value = 1;   \n"

MISSING_TRAILING_COMMA_PHP = (
    "<?php\n\nreturn [\n    'active',\n    'published'\n];\n"
)

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
    """Run a checker and return its exit code and combined output."""
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

        (root / 'Ordered.php').write_text(ORDERED_PHP)
        (root / 'Unordered.php').write_text(UNORDERED_PHP)
        (root / 'balanced.ts').write_text(BALANCED_TS)
        (root / 'unbalanced.ts').write_text(UNBALANCED_TS)
        (root / 'Apostrophe.vue').write_text(VUE_WITH_APOSTROPHE_COMMENT)
        (root / 'Collapsible.vue').write_text(VUE_COLLAPSIBLE)

        (root / 'CleanPint.php').write_text(CLEAN_PINT_PHP)
        (root / 'ArraySyntax.php').write_text(ARRAY_SYNTAX_PHP)
        (root / 'TrailingWhitespace.php').write_text(TRAILING_WHITESPACE_PHP)
        (root / 'MissingComma.php').write_text(MISSING_TRAILING_COMMA_PHP)
        (root / 'NoTrailingNewline.php').write_text('<?php\n\n$value = 1;')
        (root / 'ClosingTag.php').write_text('<?php\n\n$value = 1;\n?>\n')

        duplicated = root / 'duplicated'
        duplicated.mkdir()
        (duplicated / 'One.php').write_text(DUPLICATED_PHP)
        (duplicated / 'Two.php').write_text(DUPLICATED_PHP)

        # The same block twice inside ONE file: a legitimate repeated list far
        # more often than shared logic, so it must not be reported.
        alone = root / 'alone'
        alone.mkdir()
        (alone / 'Solo.php').write_text(DUPLICATED_PHP + DUPLICATED_PHP)

        print('php_import_order.py')
        code, out = run('php_import_order.py', str(root / 'Ordered.php'))
        check('Date before DB is ordered (case-insensitive)', code == 0, out)

        code, out = run('php_import_order.py', str(root / 'Unordered.php'))
        check('Http before Database is a violation', code == 1, out)

        print('js_balance.py')
        code, out = run('js_balance.py', str(root / 'balanced.ts'))
        check('balanced file passes', code == 0, out)

        code, out = run('js_balance.py', str(root / 'unbalanced.ts'))
        check('unclosed brace is reported', code == 1 and 'unclosed' in out, out)

        code, out = run('js_balance.py', str(root / 'Apostrophe.vue'))
        check("apostrophe in a // comment doesn't open a string", code == 0, out)

        print('php_pint_style.py')
        code, out = run('php_pint_style.py', str(root / 'CleanPint.php'))
        check('clean file (incl. ->array() call and inline comment) passes', code == 0, out)

        code, out = run('php_pint_style.py', str(root / 'ArraySyntax.php'))
        check('long array(...) syntax is reported', code == 1 and 'array(...)' in out, out)

        code, out = run('php_pint_style.py', str(root / 'TrailingWhitespace.php'))
        check('trailing whitespace is reported', code == 1 and 'trailing whitespace' in out, out)

        code, out = run('php_pint_style.py', str(root / 'MissingComma.php'))
        check('missing trailing comma in array is reported', code == 1 and 'trailing comma' in out, out)

        code, out = run('php_pint_style.py', str(root / 'NoTrailingNewline.php'))
        check('missing trailing newline is reported', code == 1 and 'trailing newline' in out, out)

        code, out = run('php_pint_style.py', str(root / 'ClosingTag.php'))
        check('stray ?> closing tag is reported', code == 1 and 'closing ?>' in out, out)

        print('vue_unused_imports.py')
        code, out = run('vue_unused_imports.py', str(root / 'Apostrophe.vue'))
        check('unused import reported', 'Unused' in out, out)
        check('template-only usage counts as used', 'computed' not in out, out)

        print('vue_prettier_hints.py')
        code, out = run('vue_prettier_hints.py', str(root / 'Collapsible.vue'))
        check('collapsible opening reported', '<Badge' in out, out)

        print('php_duplicate_blocks.py')
        code, out = run('php_duplicate_blocks.py', str(duplicated))
        check('block shared by two files reported', 'One.php' in out and 'Two.php' in out, out)

        code, out = run('php_duplicate_blocks.py', str(alone))
        check('single-file repetition not reported', '0 block(s)' in out, out)

    print()
    if failures:
        print(f'{len(failures)} case(s) failed: {", ".join(failures)}')
        return 1

    print('all cases passed')

    return 0


if __name__ == '__main__':
    sys.exit(main())

#!/usr/bin/env python3
"""Tests for ci_plan.py's change classification.

Run directly: python3 .github/scripts/ci_plan_test.py
"""

import importlib.util
import io
import sys
import unittest
from contextlib import redirect_stdout
from pathlib import Path

_MODULE_PATH = Path(__file__).parent / "ci_plan.py"
_spec = importlib.util.spec_from_file_location("ci_plan", _MODULE_PATH)
ci_plan = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(ci_plan)

NOTHING = dict.fromkeys(ci_plan.GROUPS, False)
EVERYTHING = dict.fromkeys(ci_plan.GROUPS, True)


def _needed(*paths: str) -> set[str]:
    return {group for group, value in ci_plan.plan(paths).items() if value}


def _cli(argv: list[str], stdin: str) -> str:
    old_stdin = sys.stdin
    sys.stdin = io.StringIO(stdin)
    out = io.StringIO()
    try:
        with redirect_stdout(out):
            ci_plan.main(argv)
    finally:
        sys.stdin = old_stdin
    return out.getvalue()


class PlanTest(unittest.TestCase):
    def test_agent_and_root_docs_need_nothing(self):
        self.assertEqual(
            ci_plan.plan(['.ai/rules/index.md', '.claude/scripts/selftest.py', 'README.md', 'AGENTS.md']),
            NOTHING,
        )

    def test_markdown_below_the_root_is_not_treated_as_docs(self):
        self.assertEqual(_needed('resources/views/help/topic.md'), {'php', 'js', 'e2e'})

    def test_any_github_change_needs_everything(self):
        self.assertEqual(ci_plan.plan(['.ai/rules/index.md', '.github/workflows/tests.yml']), EVERYTHING)
        self.assertEqual(ci_plan.plan(['.github/scripts/deploy-remote.sh']), EVERYTHING)

    def test_php_tests_and_tool_config_need_only_php(self):
        self.assertEqual(
            _needed('tests/Feature/Admin/RowControllerTest.php', 'tests/Pest.php', 'phpstan.neon', 'pint.json'),
            {'php'},
        )

    def test_fixtures_reach_the_browser(self):
        self.assertEqual(_needed('tests/Fixtures/StoreCampaignBrowserSeeder.php'), {'php', 'js', 'e2e'})

    def test_vitest_files_skip_only_e2e(self):
        # Pest scans every file under resources/js, so even a test file needs it.
        self.assertEqual(_needed('resources/js/lib/cellHighlight.test.ts'), {'php', 'js'})

    def test_frontend_source_needs_php_js_and_e2e(self):
        self.assertEqual(_needed('resources/js/pages/Admin/Rows/Index.vue'), {'php', 'js', 'e2e'})

    def test_backend_source_needs_php_js_and_e2e(self):
        # Routes and controllers feed the generated Wayfinder types.
        self.assertEqual(_needed('routes/web.php'), {'php', 'js', 'e2e'})
        self.assertEqual(_needed('app/Models/Pallet.php'), {'php', 'js', 'e2e'})

    def test_dependency_manifests_need_the_audit(self):
        self.assertEqual(_needed('package-lock.json'), {'php', 'js', 'e2e', 'audit'})
        self.assertEqual(_needed('composer.lock'), {'php', 'js', 'e2e', 'audit'})

    def test_groups_accumulate_across_files(self):
        self.assertEqual(_needed('tests/Feature/FooTest.php', 'resources/js/app.ts'), {'php', 'js', 'e2e'})

    def test_no_files_need_nothing(self):
        self.assertEqual(ci_plan.plan([]), NOTHING)


class CliTest(unittest.TestCase):
    def test_prints_github_output_lines(self):
        self.assertEqual(
            _cli([], 'tests/Feature/FooTest.php\n'),
            'php=true\njs=false\ne2e=false\naudit=false\ndeploy-script=false\n',
        )

    def test_empty_input_fails_open(self):
        self.assertEqual(_cli([], '\n'), ''.join(f'{group}=true\n' for group in ci_plan.GROUPS))

    def test_all_and_none_flags(self):
        self.assertEqual(_cli(['--all'], ''), ''.join(f'{group}=true\n' for group in ci_plan.GROUPS))
        self.assertEqual(_cli(['--none'], 'app/Models/Pallet.php\n'), ''.join(f'{group}=false\n' for group in ci_plan.GROUPS))


if __name__ == "__main__":
    unittest.main()

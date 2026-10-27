#!/usr/bin/env python3
"""Tests for ci_plan.py's change classification.

Run directly: python3 .github/scripts/ci_plan_test.py
"""

import importlib.util
import io
import os
import subprocess
import sys
import tempfile
import textwrap
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
        self.assertEqual(_needed('composer.lock'), {'php', 'mysql', 'js', 'e2e', 'audit'})

    def test_mysql_runs_only_for_schema_related_changes(self):
        self.assertEqual(_needed('database/migrations/2026_10_15_000000_add_x.php'), {'php', 'mysql', 'js', 'e2e'})
        self.assertEqual(_needed('tests/Feature/Database/SchemaIdentifierLengthTest.php'), {'php', 'mysql'})
        self.assertEqual(_needed('config/database.php'), {'php', 'mysql', 'js', 'e2e'})
        self.assertNotIn('mysql', _needed('app/Models/Pallet.php', 'resources/js/app.ts', 'tests/Feature/FooTest.php'))

    def test_groups_accumulate_across_files(self):
        self.assertEqual(_needed('tests/Feature/FooTest.php', 'resources/js/app.ts'), {'php', 'js', 'e2e'})

    def test_no_files_need_nothing(self):
        self.assertEqual(ci_plan.plan([]), NOTHING)


TREE = 'a' * 40


class AttestationTest(unittest.TestCase):
    def test_round_trip(self):
        line = ci_plan.format_attestation(TREE, ['e2e', 'php', 'js'])
        self.assertEqual(line, f'CI-Verified: tree={TREE} groups=php,js,e2e')
        self.assertEqual(ci_plan.attested_groups(f'Subject\n\nBody\n\n{line}\n', TREE), {'php', 'js', 'e2e'})

    def test_a_different_tree_vouches_for_nothing(self):
        line = ci_plan.format_attestation('b' * 40, ['php'])
        self.assertEqual(ci_plan.attested_groups(line, TREE), frozenset())

    def test_malformed_and_unknown_entries_are_ignored(self):
        self.assertEqual(ci_plan.attested_groups('CI-Verified: tree=zz groups=php', TREE), frozenset())
        self.assertEqual(ci_plan.attested_groups(f'CI-Verified: tree={TREE} groups=php,bogus', TREE), {'php'})

    def test_the_last_matching_trailer_wins(self):
        first = ci_plan.format_attestation(TREE, ['php', 'js'])
        last = ci_plan.format_attestation(TREE, ['php'])
        self.assertEqual(ci_plan.attested_groups(f'{first}\n{last}', TREE), {'php'})


def _planned_with(message: str, tree: str, *paths: str) -> str:
    import tempfile

    with tempfile.NamedTemporaryFile('w', suffix='.txt') as handle:
        handle.write(message)
        handle.flush()
        return _cli(['--attested-message-file', handle.name, '--tree', tree], '\n'.join(paths) + '\n')


def _true_groups(output: str) -> set[str]:
    return {line.split('=')[0] for line in output.splitlines() if line.endswith('=true')}


class AttestedPlanTest(unittest.TestCase):
    def test_attested_groups_are_skipped_and_others_still_run(self):
        message = ci_plan.format_attestation(TREE, ['php', 'js'])
        output = _planned_with(message, TREE, 'app/Models/Pallet.php')
        self.assertEqual(_true_groups(output), {'e2e'})

    def test_a_full_attestation_skips_everything(self):
        message = ci_plan.format_attestation(TREE, ['php', 'js', 'e2e'])
        self.assertEqual(_true_groups(_planned_with(message, TREE, 'app/Models/Pallet.php')), set())

    def test_a_stale_attestation_changes_nothing(self):
        message = ci_plan.format_attestation('b' * 40, ['php', 'js', 'e2e'])
        self.assertEqual(_true_groups(_planned_with(message, TREE, 'app/Models/Pallet.php')), {'php', 'js', 'e2e'})

    def test_a_workflow_change_ignores_the_attestation(self):
        message = ci_plan.format_attestation(TREE, list(ci_plan.GROUPS))
        output = _planned_with(message, TREE, '.github/workflows/tests.yml', 'app/Models/Pallet.php')
        self.assertEqual(_true_groups(output), set(ci_plan.GROUPS))


class CliTest(unittest.TestCase):
    def test_prints_github_output_lines(self):
        self.assertEqual(
            _cli([], 'tests/Feature/FooTest.php\n'),
            'php=true\nmysql=false\njs=false\ne2e=false\naudit=false\ndeploy-script=false\n',
        )

    def test_empty_input_fails_open(self):
        self.assertEqual(_cli([], '\n'), ''.join(f'{group}=true\n' for group in ci_plan.GROUPS))

    def test_all_and_none_flags(self):
        self.assertEqual(_cli(['--all'], ''), ''.join(f'{group}=true\n' for group in ci_plan.GROUPS))
        self.assertEqual(_cli(['--none'], 'app/Models/Pallet.php\n'), ''.join(f'{group}=false\n' for group in ci_plan.GROUPS))


class RequiredAttestationTest(unittest.TestCase):
    def run_gate(self, paths, message='', tree=TREE, merged_tree=TREE, extra=()):
        with tempfile.NamedTemporaryFile('w') as handle:
            handle.write(message)
            handle.flush()
            return subprocess.run(
                [sys.executable, str(_MODULE_PATH), '--require-attestation',
                 '--attested-message-file', handle.name, '--tree', tree,
                 '--merged-tree', merged_tree, *extra],
                input='\n'.join(paths), capture_output=True, text=True,
            )

    def test_missing_stale_malformed_and_partial_attestations_fail_without_outputs(self):
        for message in ('', 'CI-Verified: invalid',
                        ci_plan.format_attestation('b' * 40, ['php', 'js', 'e2e']),
                        ci_plan.format_attestation(TREE, ['php', 'js'])):
            with self.subTest(message=message):
                result = self.run_gate(['app/Models/Pallet.php'], message)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(result.stdout, '')
                self.assertIn('--attest', result.stderr)

    def test_coverage_follows_the_changed_paths(self):
        for paths, groups in ((['tests/Feature/FooTest.php'], ['php']),
                              (['composer.lock'], ['php', 'mysql', 'js', 'e2e', 'audit'])):
            with self.subTest(paths=paths):
                result = self.run_gate(paths, ci_plan.format_attestation(TREE, groups))
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(_true_groups(result.stdout), set())

    def test_new_merged_content_and_missing_hashes_fail(self):
        for tree, merged in ((TREE, 'b' * 40), ('', TREE), (TREE, '')):
            with self.subTest(tree=tree, merged=merged):
                result = self.run_gate(['routes/web.php'], ci_plan.format_attestation(TREE, ci_plan.GROUPS), tree, merged)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(result.stdout, '')

    def test_docs_and_workflow_exemptions_do_not_require_hashes(self):
        for paths, groups in ((['AGENTS.md', '.ai/rules/ci.md'], set()),
                              (['.github/workflows/tests.yml', 'routes/web.php'], set(ci_plan.GROUPS))):
            with self.subTest(paths=paths):
                result = self.run_gate(paths, tree='', merged_tree='')
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(_true_groups(result.stdout), groups)

    def test_empty_file_list_and_override_flags_fail(self):
        for paths, extra in (([], ()), (['routes/web.php'], ('--all',)), (['routes/web.php'], ('--none',))):
            with self.subTest(extra=extra):
                result = self.run_gate(paths, extra=extra)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(result.stdout, '')


class WorkflowGateTest(unittest.TestCase):
    """Execute the real workflow's planning shell against a fake GitHub API."""

    def run_plan(self, paths=('routes/web.php',), message='', merged_tree=TREE,
                 event='pull_request', failure=''):
        root = _MODULE_PATH.parents[2]
        workflow = (root / '.github/workflows/tests.yml').read_text()
        step = workflow.split('        id: plan\n', 1)[1]
        shell = step.split('        run: |\n', 1)[1].split('\n  format-js:', 1)[0]
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary)
            gh = directory / 'gh'
            gh.write_text(f'#!{sys.executable}\n' + textwrap.dedent('''\
                import json, os, sys
                args = ' '.join(sys.argv[1:])
                if os.environ['FAKE_FAILURE'] and os.environ['FAKE_FAILURE'] in args:
                    sys.exit(1)
                if '/files?' in args:
                    print(os.environ['FAKE_FILES'])
                elif '/commits/merge' in args:
                    print(os.environ['FAKE_MERGED_TREE'])
                elif '/commits/head' in args:
                    print(json.dumps({'commit': {'tree': {'sha': os.environ['FAKE_TREE']},
                                                 'message': os.environ['FAKE_MESSAGE']}}))
                else:
                    sys.exit(2)
                '''))
            gh.chmod(0o755)
            output = directory / 'outputs'
            env = {**os.environ, 'PATH': f'{directory}{os.pathsep}{os.environ["PATH"]}',
                   'REPO': 'owner/repo', 'EVENT_NAME': event, 'PR_NUMBER': '1',
                   'HEAD_SHA': 'head', 'MERGE_SHA': 'merge', 'RUNNER_TEMP': temporary,
                   'GITHUB_OUTPUT': str(output), 'FAKE_FILES': '\n'.join(paths),
                   'FAKE_TREE': TREE, 'FAKE_MERGED_TREE': merged_tree,
                   'FAKE_MESSAGE': message, 'FAKE_FAILURE': failure}
            result = subprocess.run(['bash', '-c', textwrap.dedent(shell)], cwd=root,
                                    env=env, capture_output=True, text=True)
            return result, output.read_text() if output.exists() else ''

    def test_missing_attestation_blocks_the_workflow_without_job_outputs(self):
        result, outputs = self.run_plan()
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(outputs, '')
        self.assertIn('--attest', result.stderr)

    def test_complete_attestation_skips_expensive_jobs(self):
        result, outputs = self.run_plan(message=ci_plan.format_attestation(TREE, ['php', 'js', 'e2e']))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(_true_groups(outputs), set())

    def test_api_failures_and_incomplete_file_lists_fail_closed(self):
        for options in ({'failure': '/commits/merge'}, {'failure': '/commits/head'},
                        {'failure': '/files?'}, {'paths': ()},
                        {'paths': tuple(f'file-{i}.php' for i in range(3000))}):
            with self.subTest(options=list(options)):
                result, outputs = self.run_plan(**options)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(outputs, '')

    def test_exemptions_and_non_pr_runs(self):
        for options, groups in (({'paths': ('README.md',)}, set()),
                                ({'paths': ('.github/workflows/tests.yml',)}, set(ci_plan.GROUPS)),
                                ({'event': 'workflow_dispatch'}, set(ci_plan.GROUPS)),
                                ({'event': 'workflow_call'}, set(ci_plan.GROUPS))):
            with self.subTest(options=options):
                result, outputs = self.run_plan(**options)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(_true_groups(outputs), groups)

    def test_changed_merged_tree_blocks_even_a_complete_attestation(self):
        result, outputs = self.run_plan(message=ci_plan.format_attestation(TREE, ci_plan.GROUPS), merged_tree='b' * 40)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(outputs, '')


if __name__ == "__main__":
    unittest.main()

#!/usr/bin/env python3
"""Tests for semgrep_report.py's gating logic.

Run directly: python3 .github/scripts/semgrep_report_test.py
"""

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

_MODULE_PATH = Path(__file__).parent / "semgrep_report.py"
_spec = importlib.util.spec_from_file_location("semgrep_report", _MODULE_PATH)
semgrep_report = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(semgrep_report)


def _run(report: dict) -> tuple[int, str]:
    with tempfile.TemporaryDirectory() as tmp:
        results_path = Path(tmp) / "results.json"
        summary_path = Path(tmp) / "summary.md"
        results_path.write_text(json.dumps(report), encoding="utf-8")
        summary_path.write_text("", encoding="utf-8")

        old_argv = sys.argv
        sys.argv = ["semgrep_report.py", str(results_path), str(summary_path)]
        try:
            exit_code = semgrep_report.main()
        finally:
            sys.argv = old_argv

        return exit_code, summary_path.read_text(encoding="utf-8")


def _finding(severity: str) -> dict:
    return {
        "check_id": "some-rule",
        "path": "app/Models/Example.php",
        "start": {"line": 1},
        "extra": {"severity": severity, "message": "example"},
    }


def _scan_error() -> dict:
    return {"level": "error", "message": "config fetch failed"}


class SemgrepReportTest(unittest.TestCase):
    def test_no_findings_no_errors_passes(self):
        exit_code, summary = _run({"results": [], "errors": []})
        self.assertEqual(exit_code, 0)
        self.assertIn("No findings.", summary)

    def test_warning_findings_only_pass(self):
        exit_code, _ = _run({"results": [_finding("WARNING")], "errors": []})
        self.assertEqual(exit_code, 0)

    def test_error_severity_finding_fails(self):
        exit_code, _ = _run({"results": [_finding("ERROR")], "errors": []})
        self.assertEqual(exit_code, 1)

    def test_scan_errors_with_no_findings_fails(self):
        """The gap this script was fixed for: a broken scan (e.g. a ruleset
        that failed to load) must not silently report green just because it
        also found zero findings."""
        exit_code, summary = _run({"results": [], "errors": [_scan_error()]})
        self.assertEqual(exit_code, 1)
        self.assertIn("scan error", summary)

    def test_scan_errors_alongside_real_findings_do_not_fail_by_themselves(self):
        """A scan error next to genuine findings (e.g. one file that failed
        to parse while everything else ran) is reported but not gated on —
        only an ERROR-severity finding or a totally empty result is."""
        exit_code, _ = _run(
            {"results": [_finding("WARNING")], "errors": [_scan_error()]}
        )
        self.assertEqual(exit_code, 0)


if __name__ == "__main__":
    unittest.main()

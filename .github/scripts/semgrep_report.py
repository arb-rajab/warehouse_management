#!/usr/bin/env python3
"""Summarize a `semgrep scan --json` report and gate the workflow on it.

Usage: semgrep_report.py <results.json> <step-summary-file>

Reads the Semgrep JSON report, writes a Markdown table of every finding to
the given step-summary file (grouped by severity), and exits 1 if any
finding has severity ERROR — the same gate `semgrep scan --severity ERROR
--error` enforced when it ran as a second, separate invocation. Findings at
WARNING/INFO severity are reported but do not fail the job.

Also exits 1 if Semgrep reported scan errors (e.g. a ruleset config that
failed to load or fetch) AND found nothing at all — that combination means
the scan most likely didn't really run, so an empty "No findings." result
would otherwise report green while silently covering nothing. Scan errors
alongside a non-empty result set are still just reported, not gated on:
a single file that failed to parse is common and shouldn't fail runs that
otherwise produced real findings.
"""

import json
import sys
from collections import Counter


def main() -> int:
    if len(sys.argv) != 3:
        print("usage: semgrep_report.py <results.json> <step-summary-file>", file=sys.stderr)
        return 2

    results_path, summary_path = sys.argv[1], sys.argv[2]

    with open(results_path, encoding="utf-8") as f:
        report = json.load(f)

    findings = report.get("results", [])
    errors = report.get("errors", [])
    severity_counts = Counter(f.get("extra", {}).get("severity", "UNKNOWN") for f in findings)

    lines = ["# Semgrep results", ""]

    if not findings:
        lines.append("No findings.")
    else:
        lines.append(
            "| Severity | Count |\n| --- | --- |\n"
            + "\n".join(f"| {sev} | {count} |" for sev, count in severity_counts.most_common())
        )
        lines.append("")
        lines.append("| Severity | Rule | File | Line | Message |")
        lines.append("| --- | --- | --- | --- | --- |")
        for finding in findings:
            severity = finding.get("extra", {}).get("severity", "UNKNOWN")
            rule = finding.get("check_id", "")
            path = finding.get("path", "")
            line = finding.get("start", {}).get("line", "")
            message = finding.get("extra", {}).get("message", "").replace("\n", " ").replace("|", "\\|")
            lines.append(f"| {severity} | {rule} | {path} | {line} | {message} |")

    if errors:
        lines.append("")
        lines.append(f"Semgrep reported {len(errors)} scan error(s); see the uploaded artifact for details.")

    with open(summary_path, "a", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")

    error_findings = [f for f in findings if f.get("extra", {}).get("severity") == "ERROR"]
    if error_findings:
        print(f"{len(error_findings)} finding(s) at ERROR severity — failing the job.", file=sys.stderr)
        return 1

    if errors and not findings:
        print(
            f"Semgrep reported {len(errors)} scan error(s) and found nothing — "
            "the scan likely didn't run, failing the job.",
            file=sys.stderr,
        )
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())

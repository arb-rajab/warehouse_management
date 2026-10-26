# Required-fields audit reports

The newest committed audit is **2026-10-26**: [Markdown report](required-fields-audit-20261026.md)
and [JSON data](required-fields-audit-20261026.json). It records 217 findings across
158 Vue and 81 Blade forms. A finding is a scanner observation, not proof of a
validation defect; read the [standing rule and scanner limits](rules/form-required-fields.md#the-audit-php-artisan-formsaudit-required)
and the dated implementation evidence before acting.

## Earlier snapshots

Each pair describes the code and scanner at its recorded date. Line numbers and
counts are historical; do not compare counts as though scanner coverage never changed.

| Date | Report | Data |
| --- | --- | --- |
| 2026-10-24 | [Markdown](archive/required-fields/required-fields-audit-20261024.md) | [JSON](archive/required-fields/required-fields-audit-20261024.json) |
| 2026-10-23 | [Markdown](archive/required-fields/required-fields-audit-20261023.md) | [JSON](archive/required-fields/required-fields-audit-20261023.json) |
| 2026-10-22 | [Markdown](archive/required-fields/required-fields-audit-20261022.md) | [JSON](archive/required-fields/required-fields-audit-20261022.json) |
| 2026-10-21 | [Markdown](archive/required-fields/required-fields-audit-20261021.md) | [JSON](archive/required-fields/required-fields-audit-20261021.json) |

## Generation and consumers

`php artisan forms:audit-required` writes `.ai/required-fields-audit-<Ymd>.md`
and `.json`; `--date=` and `--output=` override the date and directory. It refuses
production and uses an in-memory SQLite database. Keep the default output path:
new reports remain discoverable here without changing the command or its tests.
When a newer pair is committed, update this index and archive the superseded pair together.
Do not regenerate old evidence from newer code.

The archived JSON is byte-for-byte unchanged. The archived Markdown differs only
in its relative link to the standing form rule, rebased for the new directory.
Dates, generator names, findings and source line references are preserved.

`RequiredFieldsAuditTest` checks command output in a temporary testing directory.
`RequiredFieldsRatchetTest` runs the scanner and reads
`tests/Feature/Support/required-fields-baseline.json`; it does not load these reports.
Only the baseline's explanatory provenance paths changed in this reorganization;
its fingerprints, reasons and allowed findings retain their meaning. A new or
fixed finding still requires the existing ratchet checks.

# Documentation map

Start with [AGENTS.md](../AGENTS.md) for shared instructions and the
[rule index](rules/index.md) for feature guidance and path mappings.
The application [README](../README.md) covers warehouse setup, APIs and deployment.

## Current guidance

| Area | Entry point |
| --- | --- |
| Warehouse feature constraints | [Rule index](rules/index.md) |
| Integrated store architecture, constraints, decisions and operations | [Store back-office guide](rules/store-backoffice-migration.md) |
| Product ownership, sync and table-name constraints | [Database ownership](rules/shared-database.md) |
| Required-field convention, scanner and ratchet | [Form rules](rules/form-required-fields.md) |
| Newest committed required-fields audit and earlier report pairs | [Audit index](required-fields-audits.md) |
| Launch operations and external confirmations | [Go-live checklist](go-live-checklist-20261021.md) |

## Historical evidence

- The store guide indexes dated migration implementation archives. These preserve
  feature contracts, decisions and unresolved issues alongside their original
  verification limits; use the active guide to determine current guidance.
- The [reconciliation ledger](store-backoffice-reconciliation-20261011.md) is the
  canonical historical account of integration sources, runtime attempts and
  verification through 2026-10-20. Its dated status and approval claims do not
  authorize new actions.
- The [legacy integration audit](rules/legacy-albaraka-holland.md) preserves the
  original source security assessment and phased migration plan. Source-state
  findings and old package versions are historical; its security constraints
  remain relevant. Do not restore excluded installers or unsafe legacy files.
- [Role-grant review](role-grants-audit-20261020.md) retains its [JSON](role-grants-audit-20261020.json)
  and [SQL](role-grants-audit-20261020.sql) evidence. Reviewing definitions never
  grants permissions or authorizes production changes.

## Documentation consumers

Agents use AGENTS.md, its shared guidance, the rule index and rule `paths`
frontmatter. Keep path mappings and feature links aligned when moving rules;
archives have no active path mappings. Source/test comments also cite store
section numbers and are navigational references, not runtime dependencies.

The required-fields command writes date-named Markdown/JSON pairs in `.ai/` by
default; the [audit index](required-fields-audits.md#generation-and-consumers)
describes its command tests and separate ratchet baseline. Reports preserve
source paths and line numbers as dated provenance, even when those locations
later change. The CI planner and pre-push runner classify `.ai/` and root Markdown
as documentation; no documentation build or link checker is configured. Validate
local links/anchors, mappings and consumer references when reorganizing, then run
`git diff --check`.

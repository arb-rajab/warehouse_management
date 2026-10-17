# Repository instructions

This is the shared entry point for Codex, Claude, and other coding agents.
Maintain shared instructions here and in the referenced `.ai/` files; keep
`CLAUDE.md` as a compatibility entry point rather than a second copy.

## Read before making changes

1. Read [.ai/agent-framework.md](.ai/agent-framework.md) for Laravel, PHP,
   Inertia, Vue, Wayfinder, formatting, and testing guidance.
2. Read [.ai/agent-conventions.md](.ai/agent-conventions.md) for engineering
   conventions, authorization, security, verification, and review workflows.
3. Read [.ai/rules/index.md](.ai/rules/index.md), then the rules matching the
   paths being changed. Use targeted `rg` searches in `.ai/rules` to find
   related constraints the path mapping may miss.
4. Check sibling files and the actual dependency versions before choosing APIs.

Read the shared files fully once per session before editing; use multiple
bounded reads if tool output is truncated. For narrow follow-up work, reuse
already-read guidance and read only newly applicable area rules. The portability
rules below take precedence over environment-specific wording in those files.

## Project context and setup

- Backend requirements and scripts are in `composer.json`; frontend requirements
  and scripts are in `package.json`. Lockfiles pin resolved versions.
- The stack includes Laravel 13, PHP 8.5, Inertia 3, Vue 3, TypeScript, Tailwind,
  Pest, Vitest, and Playwright. Confirm installed versions before relying on APIs.
- Read [README.md](README.md) for warehouse workflows and local setup. Also consult
  [.ai/rules/legacy-albaraka-holland.md](.ai/rules/legacy-albaraka-holland.md) for
  the integrated store and [.ai/rules/store-backoffice-migration.md](.ai/rules/store-backoffice-migration.md)
  for its existing migration constraints; the README alone is not a complete
  inventory of the current application.
- `composer run setup` installs dependencies, creates local environment files,
  migrates the database, and builds assets. Inspect its effects and confirm the
  database target before running it. `composer run dev` starts local services.
- Preserve existing application structure, authorization boundaries, API
  contracts, localization, and protected Blade templates during migration work.

## Tool and runtime portability

- `.claude/settings.json` and `.claude/hooks/` are Claude-specific. Codex does
  not automatically run them, inherit their permissions, or receive their
  notifications. Do not assume dependencies or assets have been provisioned.
- First check available tools, dependencies, and setup state. Only use
  `.claude/scripts/wait_for_setup.py` when an existing Claude setup is relevant;
  an absent setup state does not establish that dependencies are ready.
- The Claude web sandbox's recorded network failures are historical observations,
  not evidence about another environment. Follow the current environment's
  network and credential policy. Do not force Claude remote-mode environment
  variables or launch its SessionStart hook to simulate Codex setup.
- Keep the existing `.claude/scripts/` utilities available to both agents. Read
  their usage and limitations before invoking them. Avoid concurrent installers,
  never bypass platform requirements, and preserve lockfiles during setup.
- Prefer Laravel Boost tools when they are available. If `search-docs` or other
  Boost tools are unavailable, use installed package source, repository files,
  existing commands, and official documentation matching the installed version.
  Do not invent tool results or block ordinary file edits on a missing tool.
- Keep durable rules in `.ai/rules`. Use `record-rule` when available; otherwise,
  for an authorized rule update, edit the appropriate rule file and its index.
- Domain skills currently live in `.claude/skills/<name>/SKILL.md`. Read the
  relevant skill directly when the runtime does not automatically discover it;
  follow its relative references without duplicating the skill content.
- Claude-specific tool names, `/remember`, notification APIs, and agent roles in
  shared guidance describe workflow intent. Use available equivalents only when
  supported. Delegate only when authorized by the current session's instructions.
- After a Laravel Boost guideline regeneration, preserve this entry point and
  the `CLAUDE.md` import. Generated framework guidance belongs in
  `.ai/agent-framework.md`; project conventions remain separate.

## Verification

For code changes, run relevant existing checks and meaningful affected tests.
For documentation-only changes, verify links, instruction consistency, and
`git diff --check`; application tests are unnecessary.

| Check | Command |
| --- | --- |
| Backend tests | `php artisan test --compact --filter=<test>` |
| Frontend tests | `npx vitest run <test-file>` |
| Frontend types | `npm run types:check` |
| Backend types | `composer run types:check` |
| PHP formatting | `vendor/bin/pint --dirty --format agent` |
| Frontend lint | `npx eslint <changed-files>` |
| Frontend formatting | `npx prettier --check <changed-files>` |
| Production assets | `npm run build` |
| End-to-end tests | `npm run test:e2e` |
| Before pushing code | `python3 .claude/scripts/pre_push_check.py` (scoped to the branch's changes; `--full` for everything) |
| Before a PR's first push | `python3 .claude/scripts/pre_push_check.py --attest` (full run of every group the change needs; lets CI skip what passed) |

Backend feature tests need a configured local `APP_KEY` and a Vite manifest;
generated Wayfinder files may also be required. Never print environment secrets.
The pre-push script can skip unavailable PHP tools: report skipped checks, rather
than describing a partial run as full verification. Do not commit or push unless
authorized by the user or the applicable task instructions.

### Attested pre-push run (`--attest`)

`pre_push_check.py --attest` runs, unscoped, every CI group the branch's changes
need, including the ones CI alone ran before (Playwright, the MySQL migration
tests, the composer/npm audits, the deploy-script test). When all of them pass it
amends the **unpushed** head commit with a trailer:

```
CI-Verified: tree=<head tree sha> groups=<groups that passed>
```

CI's `plan` job then skips exactly those groups for that tree, which cuts a
typical pull request from about 24 billed minutes to about 1. Rules:

- Commit everything first and run it on a clean work tree, then push the commit
  it amends. Do not push before it finishes: the amend needs an unpushed commit.
- Any later content change (a new commit, a rebase, a merge of the base) makes the
  trailer stale; run it again before pushing that change. A stale trailer is
  ignored, never trusted.
- It takes about 25 minutes. Run it in the background and wait for completion; it
  stops at the first failing check and records nothing. Fix the cause (do not skip
  or loosen a test), then run it again.
- A change under `.github/` always runs everything in CI; the trailer is ignored.
- It needs the Claude web sandbox's tooling (local MySQL from `session-start.sh`,
  the preinstalled Chromium). A group that cannot run is left out of the trailer
  and CI runs it, so state which groups were attested.
- Never write or edit the trailer by hand: it vouches for a run that must have
  happened. Details and constraints are in [.ai/rules/ci.md](.ai/rules/ci.md).

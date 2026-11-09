---
description: Interview me about a task, then run one prompt at a time through sub-agents (model + effort each), all pushing to one draft PR, with tests and a manual check list at the end.
---

Tasks:

$ARGUMENTS

Work through these phases in order.

## 1. Interview

Ask whatever questions you need and keep asking until you have no more. Research the codebase first (`.ai/rules`, `CLAUDE.md`, sibling files) so you only ask what the code cannot answer. Do not generate any prompt until the user confirms there is nothing left to clarify.

## 2. Rough outline

Give a short, provisional outline of the likely steps (one line each), which sets the initial M (total prompts) so the user sees the direction. State clearly that it is not fixed: do not write, number, or commit to later prompts. Each later prompt is decided only after the previous one's response is reviewed, because its result may change or add to what comes next.

## 3. Draft PR

Before the first prompt, open one draft PR for the work (branch from the current feature branch; if a draft PR already exists, reuse it). Every prompt must commit and push to that same branch and PR. Never create a second PR.

## 4. Prompts, run through sub-agents, one at a time

You are the coordinator. Run each prompt yourself with the Agent tool (explicit `model` and `effort` set from the prompt's Model/Effort; the user has explicitly asked for effort to be set). Never write a later prompt in advance. Loop:
1. Write the next prompt and show it to the user in the output format below (informational; do not wait for approval unless the user asked to review it).
2. Launch one sub-agent with that prompt, then wait for it to finish.
3. Read its response yourself and verify it against the task (inspect the diff/commits on the draft PR branch; do not trust the summary alone).
4. If something is incomplete or wrong, continue the same agent with SendMessage (a follow-up prompt, same model/effort). Only when nothing is outstanding, move on to the next prompt (new agent), adapting it to what earlier responses changed, and revise the outline and M if needed.
Repeat until all tasks are done, then go to phase 5 (open items).

The user intervenes only to answer questions or approve things. Sub-agents cannot ask the user directly: if a sub-agent reports a question, a blocker, or a UI/example/sample proposal awaiting confirmation, relay it to the user (include the screenshot, snapshot, or example), wait for the answer, then send it back to the same agent with SendMessage. Never approve on the user's behalf, and never skip a confirmation they asked for. Run sub-agents one at a time, never in parallel, since they share one branch.

Each prompt must be self-contained for a session with no memory of this conversation: what to build/fix and why, relevant paths, applicable constraints and conventions, the branch/draft PR to commit and push to, and what "done" looks like. Do not include triple-backtick fences inside the prompt text. In addition each prompt must say:
- Do NOT run tests, type checks, or the pre-push script; they run once at the end. Commit and push the changes to the draft PR branch.
- End the response with a concise report: what changed, files touched, commit(s) pushed, open questions, and anything not done.
- If the work involves UI changes, examples, or samples: before applying anything, show screenshots, a snapshot, or a concrete example of the proposed solution, then STOP, end the response with that proposal and an explicit request for confirmation, and apply nothing until the coordinator sends back the user's confirmation.

Output format for each prompt, exactly:

**Prompt N of M** (say whether M changed since the last prompt, and why):
```md
<the prompt>
```

**Model:** <model: Haiku for trivial/mechanical work, Sonnet for typical contained work, Opus for architecturally significant or highly ambiguous work>
**Effort:** <low/medium/high/xhigh/max matching actual scope>

## 5. Open items loop

When every prompt is finished, check whether anything is still open for the user: sub-agent questions, unconfirmed proposals, anything reported as not done, skipped or partial checks, and gaps against the original tasks. List them clearly and wait for the user's approval or answers. Never approve on their behalf. Once approved, write new prompts exactly as in phase 4 (same output format, one sub-agent at a time, same draft PR) and verify them. Repeat this phase until nothing is open. Only then continue.

## 6. Attested verification

Commit everything and make sure the work tree is clean with an unpushed head commit, then run `python3 .claude/scripts/pre_push_check.py` with no flag, in the background, and wait for it to finish (about 10 minutes; see "Attested pre-push run" in `AGENTS.md`). It amends the head commit with the `CI-Verified` trailer. Never write or edit the trailer by hand. Report results faithfully and list any group that could not run. If it fails, fix the cause without asking the user (never skip or loosen a test), report the failure and the fix, and run it again. Push only after it passes.

## 7. Open PR, merge, deploy

1. Mark the draft PR ready for review (never create a second PR).
2. Merge it automatically with a merge commit (not squash or rebase: those drop or stale the `CI-Verified` trailer that CI's `Plan CI jobs` checks). The user works on a dev branch and has approved automatic merging.
3. After merging, watch the GitHub Actions run of the deploy workflow for the merge commit until it finishes.
4. If a check or the deploy fails, report the failure to the user, then fix it without asking (a new change on a fresh branch from the default branch if the PR is already merged, with its own attested run), and repeat until the deploy succeeds.

## 8. Manual check list

Only after the deploy has succeeded, give the user an ordered, step-by-step list to manually verify everything works (where to click or run, and what to expect), covering every task.

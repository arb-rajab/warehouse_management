---
description: Interview me about a task, then hand out one delegation prompt at a time (model + effort each), all pushing to one draft PR, with tests and a manual check list at the end.
---

Tasks:

$ARGUMENTS

Work through these phases in order.

## 1. Interview

Ask whatever questions you need and keep asking until you have no more. Research the codebase first (`.ai/rules`, `CLAUDE.md`, sibling files) so you only ask what the code cannot answer. Do not generate any prompt until the user confirms there is nothing left to clarify.

## 2. Plan

Split the tasks into an ordered list of prompts, each independently completable in a fresh session. Show the list (one line per prompt, with model and effort) so the user can adjust it. Do not write the prompts yet.

## 3. Draft PR

Before the first prompt, open one draft PR for the work (branch from the current feature branch; if a draft PR already exists, reuse it). Every prompt must commit and push to that same branch and PR. Never create a second PR.

## 4. Prompts, one at a time

Generate only the first prompt, then stop and wait. When the user pastes its response:
- If something was not completed, generate a follow-up prompt meant to be pasted into the same session as the previous one.
- Otherwise generate the next prompt (fresh session).
Repeat until all prompts are done.

Each prompt must be self-contained for a session with no memory of this conversation: what to build/fix and why, relevant paths, applicable constraints and conventions, the branch/draft PR to commit and push to, and what "done" looks like. Do not include triple-backtick fences inside the prompt text. In addition each prompt must say:
- Do NOT run tests, type checks, or the pre-push script; they run once at the end. Commit and push the changes to the draft PR branch.
- If the work involves UI changes, examples, or samples: before applying anything, show screenshots, a snapshot, or a concrete example of the proposed solution, then STOP and wait for the user's confirmation before applying it.

Output format for each prompt, exactly:

**Prompt N of M:**
```md
<the prompt>
```

**Model:** <model: Haiku for trivial/mechanical work, Sonnet for typical contained work, Opus for architecturally significant or highly ambiguous work>
**Effort:** <low/medium/high/xhigh/max matching actual scope>

## 5. Final verification

When every prompt is finished, run the relevant tests and checks once for the whole change (see the verification table in `AGENTS.md`), report results faithfully, and mark nothing as passing that was skipped.

## 6. Manual check list

Finish by giving the user an ordered, step-by-step list to manually verify everything works (where to click or run, and what to expect), covering every task.

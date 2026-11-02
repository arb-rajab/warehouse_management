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
Repeat until all tasks are done.

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

## 5. Final verification

When every prompt is finished, run (or delegate to one final sub-agent) the relevant tests and checks once for the whole change (see the verification table in `AGENTS.md`), report results faithfully, and mark nothing as passing that was skipped.

## 6. Manual check list

Finish by giving the user an ordered, step-by-step list to manually verify everything works (where to click or run, and what to expect), covering every task.

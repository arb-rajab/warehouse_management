---
description: Turn a short feature/change explanation into a ready-to-send delegation prompt, with a recommended model and effort level.
---

Given the feature/change explanation below, produce exactly three things and nothing else — no commentary, no restating the explanation back:

$ARGUMENTS

1. **Prompt** — a self-contained, ready-to-paste delegation prompt for a fresh session/subagent with no memory of this conversation. Include what to build/fix and why, the relevant file paths or codebase areas if known, any constraints or conventions that apply (check `.ai/rules` and `CLAUDE.md` if relevant), and what "done" looks like (tests updated/passing, etc). Write it the way a smart colleague walking in cold would need — no filler, no padding. Do not include triple-backtick fences in the prompt text itself.
2. **Model** — the single best-fit model (e.g. Sonnet 5, Opus 5, Haiku 4.5). Haiku for trivial/mechanical work, Sonnet for typical contained feature/bugfix work, Opus for architecturally significant or highly ambiguous work. One line; only add a reason if the choice is non-obvious.
3. **Effort** — one of low/medium/high/xhigh/max, matching actual scope: low for a single mechanical fix, medium for a normal contained feature, high+ for work spanning many files or requiring design judgment.

Output format exactly:

**Prompt:**
```md
<the prompt>
```

**Model:** <model>
**Effort:** <effort>

Infer reasonable defaults from the explanation and whatever codebase context is already available rather than asking clarifying questions — only ask if the explanation is genuinely too vague to produce a usable prompt.


# Project Conventions

Apply these on every file add/edit in this project, not as an occasional audit.

## Pre-change checklist — run on every file add/edit

1. Before adding a new file, check the code you're about to add for duplication
   against the rest of the codebase (not just sibling files). If it repeats
   logic that already exists elsewhere, extract the shared logic instead of
   duplicating it — but only once there are 2+ real call sites (see below).
2. Before editing a file, look at what's being removed. If it was the second-
   to-last caller of a shared helper/trait/composable/component, inline that
   shared code into the one remaining caller and delete the now-unused shared
   definition, in the same change — don't leave a one-caller indirection
   "in case it's needed again."
3. Before editing a file, check the code being added the same way as point 1.
4. After any file is added or edited, add or update the test(s) covering that
   change and run them before finalizing.

## Duplication threshold

- An extraction only clears the bar once 2+ real call sites need it. Don't
  extract speculatively, and don't add flexibility (extra params/options) that
  nothing currently uses.
- Two files/components that look structurally similar isn't proof of
  duplication — check whether each *specific* symbol (a constant, a type, a
  variant map) would actually be imported by 2+ places post-extraction, not
  just whether the surrounding shape looks alike.
- The reverse applies: if a change drops a shared helper to one caller,
  inline it back in the same change.

## Reuse before writing new UI

- Before writing a new button/input/modal/dropdown, or repeating a class
  string, check existing shared components first.
- Only promote a component-local variant/style map to a shared file once a
  second component actually needs it.

## Format/type-check incrementally, not just at the final gate

- Run the formatter (Pint/Prettier) and type-checker (vue-tsc/PHPStan) right
  after editing the file(s) they cover, not only via one final CI-check
  command at the end. Catching an issue on the file you just touched is
  cheaper than finding a pile of unrelated fallout later.
- In a Claude Code web session the SessionStart hook installs the PHP tools
  from git sources and builds the Vite assets in the background — see the next
  section, and wait for it (`python3 .claude/scripts/wait_for_setup.py`) before
  relying on them.
- Same discipline for `vitest`: while iterating on a fix, run only the
  specific `*.test.ts` file(s) the change touches (`npx vitest run
  path/to/File.test.ts`), not the whole suite. Reserve one full `npx vitest
  run` (all files) plus one full `npx vue-tsc --noEmit` (no per-file mode
  exists for it) for right before each commit, as the final confirmation
  that nothing else regressed — not after every intermediate edit. Before
  every push, run `python3 .claude/scripts/pre_push_check.py` once: it picks
  the checks the branch's changes need with the same classifier as CI's `plan`
  job, scoping ESLint, Prettier, Vitest and Pint to the changed files and
  running vue-tsc, PHPStan and Pest whole; a docs-only change runs nothing.
  `--full` runs the unscoped sequence in CI's order. It skips the PHP steps,
  with a note, when the dev tools are not installed. Which check runs where is
  tabled in `.ai/rules/ci.md`.

## Sandbox: dev tools install from git, and Pest needs the Vite build

A plain `composer install` cannot succeed in a Claude Code web session: every
locked package's dist URL (`api.github.com/repos/<vendor>/<package>/zipball/...`,
and codeload/archive URLs alike) returns 403 through the egress proxy for any
repository the session does not have attached. Anonymous git fetches of public
repositories *are* served, so the SessionStart hook
(`.claude/hooks/session-start.sh`) runs
`python3 .claude/scripts/install_dev_tools.py`: it shallow-fetches every locked
commit in parallel, archives each into the zip GitHub would have served, installs
everything (including Pint, Pest, PHPStan, Larastan and faker) from those local
dists, and restores `composer.lock` byte for byte afterwards. Measured cold: about
35 seconds and 1.3 GB, against about 11 minutes and 7 GB for the
`--prefer-source` install it replaced; a package whose fetch fails falls back to
a source clone on its own. While it patches the lock for the
install, git is told to ignore the file (`--skip-worktree`), so a tree that looks
dirty never invites a commit of the patched lock; if `composer.lock` ever does
show as modified, setup is still running or was killed (check
`python3 .claude/scripts/wait_for_setup.py`), so never commit it. The hook first runs
`bash .claude/scripts/install_php.sh`, which installs PHP 8.5 and every
extension the app needs (including `gd`) from the ondrej PPA when they are
missing, and does nothing when they are present. `composer.json` requires
`^8.5` and `composer.lock` requires `ext-gd`; without them the install fails its
platform check, `vendor/` never appears, and artisan, Wayfinder and the Vite
build are all unavailable. Never answer that platform error with
`--ignore-platform-reqs`: run the script. It targets Debian/Ubuntu containers
and needs root; `--check` reports whether PHP is satisfied without changing
anything.

**The hook runs asynchronously** (`"async": true` in `.claude/settings.json`), so
a session starts at once while the whole setup takes one to two minutes on a
fresh container (MySQL and `npm ci` run alongside composer; each phase's time is
in `timings.txt` next to the setup state); blocking on it would still delay every
fresh session's first action. A short notice in the session's context says setup is running.
Work that does not need it (reading code, editing docs) can start immediately.
Before composer, artisan, Pint, PHPStan, Pest, the Wayfinder files or the built
assets, run `python3 .claude/scripts/wait_for_setup.py` once, in the background,
and let its completion notice arrive rather than polling. It exits 0 when setup
is done and prints its summary (whether the PHP tools are installed and the Vite
build exists), and 1 if setup failed, was interrupted (killed or timed out), or
the wait timed out. `pre_push_check.py` waits on its own. Do not start your own
`npm`/`composer` work while setup is still running: it would collide with it.

When they are installed, run the checks locally instead of waiting for CI:

- `composer lint:check` (Pint) and `composer types:check` (PHPStan).
- Pest: `COMPOSER_PROCESS_TIMEOUT=0 php artisan test --parallel`. `composer
  test:pest` hits composer's 300s process timeout on this suite; in parallel it
  takes about 90s. Note `types:check` runs *before* Pest in `composer ci:check`,
  so a PHPStan error means the suite never ran at all.
- **Pest needs `.env` with an `APP_KEY`.** `phpunit.xml` sets none, so in a
  container with no `.env` every test that boots the app errors with "No
  application encryption key has been specified" (925 errors in one run). The
  hook copies `.env.example` and generates the key (with `php`, not `artisan`, so
  it works before `vendor/` exists) when `.env` is missing; if you see that
  error, do the same by hand rather than reading it as a code defect.
- **Pest needs `public/build/manifest.json`.** Every Inertia feature test renders
  the root view, which reads the Vite manifest, so without it ~200 tests fail
  with `ViteManifestNotFoundException` — a missing build, not a defect in the
  code under test. The hook runs `npm run build` after generating the Wayfinder
  files; if the manifest is missing, run `php artisan wayfinder:generate && npm
  run build` before treating any Pest failure as real.

Do not hand-run `composer install`/`composer update`, do not seed package
caches, and do not reach for `--ignore-platform-reqs` or package-source rewrites
of your own: a bare install (no `--no-dev`) is denied by the guard hook
(`.claude/hooks/install-retry-guard.sh`), as is any retry after a network
failure — use the script above, and take a denial at face value. If the script
itself fails, the proxy policy has changed: report the error, fall back to
`composer install --no-dev` for a bootable app, write and update the tests as
normal, and read Pint/PHPStan/Pest from CI (`composer ci:check`).

**While a long-running command like `composer install --no-dev` or the setup
hook (the hook's timeout stays 1800s for the slow source-clone fallback) is in
flight in the background:** wait for exactly one
completion notification — don't spawn a second or third background loop polling
the same PID/condition, and don't fill the intervening turns with no-op
`Bash`/`ReadNotifications` calls just to "do something while waiting". The
harness delivers a notification when the backgrounded command finishes; ending
the turn and letting that notification arrive costs nothing; a stack of
redundant polling loops and filler turns costs real quota for zero additional
information.

Only in the fallback `--no-dev` state is `fake()` undefined: `fakerphp/faker` is
a dev dependency, so there is no faker at all. That puts every factory and
seeder out of reach locally — not only under Pest, but through `php artisan
tinker` and `php artisan db:seed` too, which fail with "Call to undefined
function Database\Factories\fake()". Take the first such failure at face value
rather than trying a second route to the same place (a file-backed sqlite
database instead of `:memory:` does not help — the missing package is the
problem, not the connection). A local smoke check that needs rows must build
them with plain `Model::create()`; anything genuinely factory- or seeder-shaped
can only be verified in CI.

The scripts in `.claude/scripts/`:

| Script | Purpose |
| --- | --- |
| `install_php.sh` | installs PHP 8.5 and its extensions from the ondrej PPA (key pinned by fingerprint); run first by the SessionStart hook |
| `install_dev_tools.py` | the dependency installer described above (shallow git fetches as local dists), run by the SessionStart hook |
| `build_without_fonts.py` | Vite build from a temporary font-less copy of `vite.config.ts`, for sandboxes where `fonts.bunny.net` is blocked (403) and `npm run build` fails; the SessionStart hook falls back to it, `vite.config.ts` is never modified |
| `wait_for_setup.py` | blocks until the background SessionStart setup has finished and prints its summary; exit 0 ready, 1 failed/interrupted/timed out |
| `pre_push_check.py` | the one command to run before every push: waits for setup, then the ESLint, Prettier, vue-tsc, Vitest, Pint, PHPStan and Pest steps the branch's changes need, scoped to the changed files where the tool allows (`--full` for all of them, unscoped) |
| `php_duplicate_blocks.py` | the cross-file duplication survey the threshold rule above needs; a deliberate review tool, not a per-push gate |
| `selftest.py` | verifies the scripts above and the install guard hook |

Each script's docstring carries its usage and its limitations; read it before
trusting a result. Run `python3 .claude/scripts/selftest.py` after changing any
script there or the guard hook — nothing else verifies them, and a checker that
has quietly stopped working is worse than none, because it is trusted. Add a
case to it for any new script rather than writing a one-off check in the
scratchpad, and never re-embed a script in a new Bash heredoc per invocation:
the repeated inline text costs tool-input tokens on every call for no benefit
over one reusable file.

### Enumerate before editing; ask for filenames before error bodies

Two habits that pay for themselves whenever a change ripples across many files
— which is most changes here, and Pest, though it can run locally, takes a
parallel run to surface the ripple, so sweep first.

**Enumerate the sites in one sweep, keyed on a stable sibling.** Adding a key to
a Resource, a `toArray()` or a map-summary breaks every test asserting that
whole payload, and Inertia's scoped `has('x', fn (Assert $p) => ...)` closures
are exhaustive — a site you miss is a CI round. Grep a key that sits *beside*
the one you changed in every instance of the shape, not the changed key itself:
`image_url` enumerates every `ProductResource` payload assertion,
`product_image_url` every `Pallet::toMapSummaryArray()` one. One sweep up front
replaces the read-a-slice/patch/re-read loop — moving `products.ar_name` to the
frontend cost four separate reads of the same test file before the sweep was
run.

**Ask for filenames before error bodies.** When a shared type gains a required
field, `vue-tsc --noEmit` reports a near-identical error at dozens of sites and
the bodies say nothing the first one didn't. Get the file list first
(`| sed 's/(.*//' | sort -u`), then pull bodies only for files that don't fit
the pattern. Same for any wide eslint or vitest failure.

**Scripted bulk edits need an anchored pattern and a dry run.** A ` *name: '` regex
matches inside `product_name: '` with zero leading spaces; anchor with
`(?<![\w_])` and print the intended diff before writing, rather than writing and
letting the type-checker find it.

### Read the framework source before theorising about framework behaviour

Every package's source is already on disk once the SessionStart install has run
— read `vendor/laravel/framework/src/Illuminate/...` directly rather than
curling it. Only in the fallback `--no-dev` state is a dev-only package (Pint,
Pest, PHPStan, Larastan) missing; then fetch the tagged source over the
network: `curl
https://raw.githubusercontent.com/<vendor>/<package>/v<version>/src/...`, with
`<version>` from `composer.lock`. When an installed package behaves in a way its
public API appears to rule out, one read settles it, while a guess costs a CI
round plus the diagnose-edit-push cycle around it.

This is not hypothetical. `Model::preventsLazyLoading()` reporting true while
no violation was ever raised cost three CI rounds and two pushed fixes for a
cause that did not exist, before the answer turned up in four lines of
`Builder::hydrate()`. The finding itself is recorded in the strict-mode section
of `.ai/rules/app-providers.md`.

## Testing conventions

- Every model relationship/filter/scope test needs unrelated "noise" data in
  the setup (another owner's row, a sibling record) and an assertion that it's
  excluded — a test that only creates the data it expects back can pass even
  if the relation silently returns everything.
- A file already having a matching test is not proof its coverage is
  current — when changing a file, diff its actual current logic against what
  the test file asserts, don't assume coverage from the test file's mere
  existence.
- Frontend: default to a full mount, not a shallow one, unless the test is
  purely checking prop pass-through to a child. Match child components by
  reference/import, not by a name string (components with no inferred name
  silently fail name-string matching). Assert on the actual props a child
  component receives, not just that some text appears on the page.
- Every user-facing static text label needs an assertion that it renders —
  not just the strings a happy-path behavioral test happens to touch.
- Every access-controlled route (auth-gated, role-gated, ownership-gated)
  needs its own "unauthorized caller is rejected" test, for every action, not
  just the happy path — including mutating actions, with a DB assertion that
  nothing changed.
- Any endpoint that paginates needs a test that seeds more rows than one page
  and asserts the first page doesn't return all of them — a test with 1-2
  rows can't distinguish `paginate()` from a plain `get()`.

## Database query minimalism

- Any query whose result reaches a rendered view/API response should select
  only the columns actually needed by the consumer's type/shape — no bare
  `Model::all()`/unrestricted `->with('relation')` when a narrower column
  list would do. Applies to eager-loaded relations too (and remember to keep
  the foreign key in a relation's column list, even if the frontend doesn't
  render it directly).
- Don't spread a full model into an array/JSON response when only a few
  fields are needed — it silently re-exposes every future column added to
  that table.

## Authorization

- Ownership checks (does this user own this record?) go through a proper
  policy/guard mechanism, not an inline conditional duplicated across every
  action that needs it.
- Mass-assignment protection and per-request ownership authorization are
  different concerns — removing a foreign key from a fillable/whitelisted
  attribute list doesn't stop a different authenticated user from mutating
  someone else's record via a route-bound ID. Both are needed.
- A role check ("is this user an admin at all") belongs in middleware/route
  guards; a per-record ownership check belongs in a policy. Don't add a
  policy ability for something that's actually a blanket role check.

## Keeping review/survey tasks cheap

- For an open-ended review or audit request that spans many files (e.g. "review
  the admin backend surface", "audit X subsystem") where the file list itself
  is unknown up front, delegate the file-reading survey to the Explore agent
  (or a general-purpose subagent for anything needing judgment beyond locating
  code) instead of reading every file directly into the main conversation.
- When the requester has already named the exact files/components in scope
  (not "find what's relevant" but "review these N files"), prefer reading them
  directly over spawning a subagent — a subagent still has to re-read the same
  files from a cold context, so for an already-enumerated file list it adds
  overhead rather than saving it. Reserve subagent delegation for when the
  scope genuinely needs to be *discovered*, or the file count is large enough
  that protecting the main context window outweighs the agent round-trip cost.
- Only read files directly yourself when you already know which specific
  file(s) you need to act on (edit, verify a fix, etc.).
- Avoid broad multi-keyword greps (e.g. `grep -rin 'a|b|c'`) that can return
  tens of KB of matches — prefer a single targeted keyword, `-l`/`files_with_matches`
  first to see what matched before dumping content, or a narrower path scope.

## Scoping review/audit sessions to control token consumption

- Before launching a review/audit agent, scope it to the narrowest set of
  files/rule files that actually answers the question. A request like "is
  there a missing index on column X" should be a direct Grep/Read, not a
  full-subsystem audit agent — reserve broad "review the whole layer" agents
  for genuinely open-ended audit requests.
- When an audit does need to span many files, split it into multiple
  narrower agents by concern (e.g. migrations+tests vs. factories+seeders)
  rather than one agent reading everything in one pass, if the request can
  be decomposed that way.
- A request to write prompts, plans or recommendations across several
  unrelated areas is a survey too — the delegation rule in the previous
  section covers it, not just reviews and audits. One Explore agent per area
  keeps those file bodies out of the main context.
- When the deliverable is a plan, a prompt or a recommendation rather than a
  code change, stop research at "enough to name the right files and the real
  constraints": locate with `files_with_matches`, confirm with one targeted
  `grep`, and leave the full reads to whoever implements it — that session
  re-reads from a cold context anyway. Reading whole controllers and models
  to write one paragraph of prompt pays the implementation cost twice.
- Route mechanical follow-ups — adding a name to a list, deleting a stray
  line, applying the obvious fix a CI failure names — at lower effort than
  the design work that preceded them. The turn that repairs a broken
  assertion doesn't need the reasoning budget of the turn that designed the
  schema.
- Don't re-run a verification command after an edit that cannot change its
  result. A reworded docblock doesn't need the smoke script run again.
- Only read the `.ai/rules` files that are actually relevant to the files in
  scope for the current task — don't re-trigger a full `.ai/rules` index
  sweep for a narrow follow-up fix that touches 1-2 files. When checking
  `.ai/rules` for a task, `Grep` for the specific keyword/section needed
  rather than reading whole rule files (or the whole concatenated index) in
  full — a targeted grep with a few lines of context is a fraction of the
  cost of dumping every matched file's entire contents into context.
  This is what the Boost "Project Rules" instruction above means by "read
  every rule file whose globs cover the path(s) in scope": the files
  governing the paths you are actually about to edit, grepped for the
  keyword that matters — not every index row that looks adjacent to the
  feature. A change touching ten directories does not license reading ten
  rule files end to end; that is the exact failure this section exists to
  prevent.
- When handing off follow-up work as separate session prompts (e.g. after a
  review produces a punch list), paste the specific finding/file:line into
  each prompt so that session doesn't re-derive it from scratch, and set an
  explicit effort level per prompt — low for mechanical/localized fixes,
  medium+ only for tasks that require reading application code to resolve a
  design question first.
- When a review/punch-list produces several independent findings to fix in
  the same session, batch the ones that don't depend on each other's outcome
  into a single edit → verify → commit → push pass instead of a full
  read-edit-verify-commit-push round trip per finding — each round trip costs
  a full turn of tool calls, so fixing 4 independent findings one at a time
  costs roughly 4x what fixing them together does. Only split into separate
  passes when a finding is uncertain enough that you want the user to
  confirm/redirect before continuing to the next one.
- Default to batching without being asked. Don't wait for the user to say
  "batch these" — the moment a punch list has 2+ independent findings, propose
  (or just do, if the user has already signaled "keep going") a single grouped
  pass. Fixing one finding, pushing, waiting for CI, then asking "what's next"
  and repeating is the exact anti-pattern the previous bullet describes; doing
  it 4 times before switching to batches on request still costs the 4x.
- Group commits/pushes with the batch, not with the finding. Each push is a
  full CI round trip (composer ci:check, per the sandbox section above) — a
  batch of N independent findings should cost one push and one CI wait, not N.
- When an audit/review is delegated to a subagent and its findings are likely
  to become fixes in this same session, ask it to cite exact `file:line`
  excerpts for each finding (not just a narrative description) in its report.
  A subagent's file reads aren't retained by the parent conversation — only
  its text report is — so a citation-free "the flags array is never asserted"
  finding forces the fix pass to re-read the whole file from scratch to
  relocate what the subagent already found, paying the read cost twice for
  the same file.
- Also ask the agent to keep clean files terse in its report — a one-line
  "no issues: FileA.vue, FileB.ts" list, not a paragraph re-explaining why
  each file is fine. The parent conversation only needs to know where the
  findings are; a narrative justification for files with no findings adds
  report size without adding anything actionable.
- Once a large file's relevant section is already known — from a citation
  above, a prior read in this session, or a fresh `Grep` hit — don't re-`Read`
  the whole file to make a small addition (e.g. one new test appended near an
  existing one in a 500+ line test file). `Grep` for the neighboring test name
  to get its line number, then `Read` with `offset`/`limit` around just that
  region.
- Don't run whole-repo sweeps (`npm run lint:check`, `npm run format:check`,
  a bare `npx vue-tsc --noEmit` with no path scoping, `npx vitest run` with no
  path) to verify a change that only touched a handful of files — scope the
  same tool to just the touched file paths (e.g. `npx eslint <file> <file>`,
  `npx prettier --check <file>`). Reserve the unscoped whole-repo form for the
  final pre-commit confirmation pass this file's testing-conventions section
  already calls for, not for checking each intermediate edit.

## PR watching: webhook-only, no fallback polling

- When subscribed to a PR's activity (`subscribe_pr_activity`), rely on the
  webhook-driven events alone — don't also schedule a fallback
  `ScheduleWakeup`/`send_later` check-in "just in case" a webhook is missed.
  Only schedule a manual poll when watching for something webhooks
  genuinely don't cover for that PR (confirmed missing coverage), not as a
  default safety net.
- This is a repo-scoped fallback: the user's actual preference is durable
  and cross-repo via `/remember`; this note exists only for sessions in
  this repository that don't have that memory loaded.

## CI/GitHub tool usage: targeted before full, consolidated before repeated

- Diagnosing a failed check: try `get_check_run` (with a small `textLimit`
  and `textOffset` paging) before reaching for `get_job_logs`. A full job
  log easily runs to tens of thousands of characters of ANSI-coded text
  that then has to be dumped to a file, stripped, and re-grepped in
  multiple passes — that cost is avoidable whenever the check run's own
  output already names the failure.
- If a full job log genuinely is needed, request the minimum first: a
  small `tail_lines` (e.g. 100-150) with `return_content: true`, not the
  whole log. Only widen the window if that slice doesn't contain the
  failure.
- When a large log/text blob does have to be searched, strip ANSI codes
  and grep for the target string in one pass (one Python/Bash step),
  rather than a failed raw grep attempt followed by a separate cleanup
  pass followed by a second grep.
- Don't re-poll PR/CI state that a call already returned this turn.
  `pull_request_read` (`get`) already carries `mergeable_state`; a
  separate `get_status` call is only useful for repos that use legacy
  commit statuses instead of check-runs (this repo uses check-runs, so
  `get_status` returns nothing and can be skipped). Prefer one
  consolidated read over `get_workflow_run` + `list_workflow_runs` +
  `pull_request_read` when any one of them already answers the question.
- When watching a single in-flight CI run, don't stack multiple short
  (under ~5 min) `ScheduleWakeup`/`send_later` check-ins back-to-back —
  each wake re-enters with full session context. Prefer one wait long
  enough for the run to plausibly finish, and lean on the
  `check_run.completed`/`check_suite.completed` webhook events (per the
  PR-watching section above) as the actual completion signal rather than
  timing a poll to beat them.

## External input handling

- Normalize untrusted input before persisting it, don't store it verbatim —
  e.g. an uploaded image gets resized/re-encoded to a fixed format before
  being written to disk, rather than saving whatever the client sent.
- Every submit form validates client-side in addition to its backend
  validation; the backend stays authoritative for anything requiring a round
  trip (uniqueness, existence, auth).

## Forms: required and optional fields

- Follow [rules/form-required-fields.md](rules/form-required-fields.md): a field
  the server rule requires shows `RequiredMark` (Vue) or `<x-required-mark />`
  (Blade) and carries `required`; optional fields are unmarked (or "(optional)"
  where most fields are required); a form with a required field shows the legend.
- The server rule is the source of truth. Test that the marks match it, and keep
  `RequiredFieldsRatchetTest` green; check other forms with `php artisan forms:audit-required`.

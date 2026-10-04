<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

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
  every push, run `python3 .claude/scripts/pre_push_check.py` once: it runs
  ESLint, Prettier, vue-tsc, the full Vitest suite, Pint, PHPStan and Pest in
  CI's order as the single command instead of assembling this sequence by hand
  each time. It skips the three PHP steps, with a note, when the dev tools are
  not installed.

## Sandbox: dev tools install from git, and Pest needs the Vite build

A plain `composer install` cannot succeed in a Claude Code web session: every
dev package's dist URL (`api.github.com/repos/<vendor>/<package>/zipball/...`)
returns 403 through the egress proxy for any repository the session does not
have attached. Anonymous `git clone` of public repositories *is* served, so the
SessionStart hook (`.claude/hooks/session-start.sh`) runs
`python3 .claude/scripts/install_dev_tools.py`: it installs everything,
including Pint, Pest, PHPStan, Larastan and faker, with `--prefer-source`,
shallow-clones the locked `phpstan/phpstan` tag (it is dist-only), and restores
`composer.lock` byte for byte afterwards. The hook first runs
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
a session starts at once while the whole setup takes about 10 minutes on a fresh
container; blocking on it made every fresh session wait that long before its
first action. A short notice in the session's context says setup is running.
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

**While a long-running command like `composer install --no-dev` or the dev-tools
script (~9 minutes cold, ~7 GB of disk: the hook's timeout is 1800s) is in flight
in the background:** wait for exactly one
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
| `install_dev_tools.py` | the dev-tools installer described above, run by the SessionStart hook |
| `wait_for_setup.py` | blocks until the background SessionStart setup has finished and prints its summary; exit 0 ready, 1 failed/interrupted/timed out |
| `pre_push_check.py` | the one command to run before every push: waits for setup, then ESLint, Prettier, vue-tsc, Vitest, Pint, PHPStan and Pest in CI's order |
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

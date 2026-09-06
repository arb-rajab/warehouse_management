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
- In a Claude Code web session none of these tools are installed and cannot be
  installed — see the next section for what to do instead.

## Sandbox: dependencies cannot be installed, so CI is the test run

`composer install` cannot succeed in a Claude Code web session. `phpstan/phpstan`
is dist-only, and its only download host (`api.github.com/.../zipball`, which
redirects to `codeload.github.com`) returns 403 through the egress proxy. So
`vendor/` is absent and Pint, PHPStan, vue-tsc and Pest cannot run locally there.

This does not relax the testing rule above — write and update the tests exactly
as normal. It changes only where you read the result: push the branch and read
Pint/PHPStan/Pest from CI (`composer ci:check`), which runs the whole chain.
Note `types:check` runs *before* Pest, so a PHPStan error means the suite never
ran at all — fix it and look again rather than assuming the tests passed.

Do not attempt the install "just once to see", and do not work around the block
by seeding package caches, rebuilding archives from git, or repointing package
sources. A 403 from the proxy is an organization egress-policy denial: per
`/root/.ccr/README.md`, report the blocked host and move on. A guard hook
(`.claude/hooks/install-retry-guard.sh`) denies repeat install attempts after
the first failure, including backgrounded ones — take the denial at face value
rather than reaching for another route around it.

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

## External input handling

- Normalize untrusted input before persisting it, don't store it verbatim —
  e.g. an uploaded image gets resized/re-encoded to a fixed format before
  being written to disk, rather than saving whatever the client sent.
- Every submit form validates client-side in addition to its backend
  validation; the backend stays authoritative for anything requiring a round
  trip (uniqueness, existence, auth).

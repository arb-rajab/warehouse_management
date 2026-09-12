---
paths:
  - 'composer.json,composer.lock,package.json,package-lock.json,.github/dependabot.yml'
---

# Dependencies

## Always address and fix Dependabot/audit-reported issues — don't just report them
When Dependabot (PR, security-update run, or `.github/dependabot.yml`-driven update) or a local `composer audit` / `npm audit` reports a vulnerable dependency, fix it in the same session rather than only describing the problem: bump the affected package (and any package pinning it below the safe range) to a version that clears the advisory, confirm with `composer audit --locked` / `npm audit` that it reports zero findings, run the affected test suite, then commit and push. Don't wait for explicit user confirmation to bump a transitive dependency purely to clear a security advisory — that is exactly the kind of change this instruction pre-authorizes.

## `composer update <pkg> --with-all-dependencies` is required for transitive vulnerable packages
Packages like `league/commonmark` are pulled in transitively (via dedoc/scramble et al.) and aren't listed in `composer.json` directly — `composer update league/commonmark --with-all-dependencies --no-scripts --no-interaction` bumps the transitive requirer's constraint enough to land a fixed version without touching `composer.json`. This can exceed the default 120s tool timeout on a cold run; let it continue in the background rather than assuming a timeout means failure — check `composer audit --locked` afterward to confirm.

## A plain `npm install`/`npm update <pkg>` can crash with "Cannot read properties of null (reading 'edgesOut')" on this npm version — this is an npm bug, not a real peer conflict
Bumping `vitest` (and likely other packages with vitest's large optional-peer-dependency shape, e.g. `@vitest/browser-playwright`/`@vitejs/devtools-*`) on npm 10.9.7 can crash `@npmcli/arborist` with `Cannot read properties of null (reading 'edgesOut')` during `buildIdealTree`, even from a clean `rm -rf node_modules && npm install` — this reproduces on a version bump alone with no other package.json changes and is not a real peer-dependency conflict to resolve by hand. CI (`composer setup` → `npm install`, see `.github/workflows/tests.yml`) uses a fresh checkout with the same npm line and will hit the identical crash if pushed as-is. Workaround: regenerate the lockfile once with `npm install --package-lock-only --legacy-peer-deps`, then verify a plain `rm -rf node_modules && npm install` (no flags) succeeds against that now-self-consistent lockfile — the bug only triggers arborist's tree-diff/upgrade path, not reifying an already-consistent lock, so CI's plain `npm install` is safe once the committed lockfile reflects the final resolved versions. Don't add `--legacy-peer-deps` to the CI workflow or `composer.json`'s `setup` script as a "fix" — that papers over real peer conflicts too; it's only needed for the one-off local lockfile regeneration.

## `js-yaml` is a transitive dependency of `eslint` → `@eslint/eslintrc` — fix via `overrides`, not a direct bump
`package.json` has no direct `js-yaml` entry, so a version-only vulnerability fix goes in a root-level `"overrides": { "js-yaml": "^x.y.z" }` block rather than editing a dependency that isn't declared directly.

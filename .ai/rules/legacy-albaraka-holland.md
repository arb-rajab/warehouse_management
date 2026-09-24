---
paths:
  - 'legacy/albaraka-holland/**'
  - compose.yaml
  - 'pint.json,eslint.config.js,.semgrepignore,resources/css/app.css,.claude/scripts/pre_push_check.py'
---

# Legacy albaraka-holland app under legacy/albaraka-holland

## It is vendored as a separate app, not merged, because the two cannot share a PHP process
`legacy/albaraka-holland/` is the old multi-vendor store (github.com/arb-rajab/albaraka-holland). It will eventually be served at `/temp` on this app's domain. Its dependencies cannot live alongside this app's:

| | legacy | this app |
| --- | --- | --- |
| `php` | `^7.1.3` | `^8.3` (runs 8.5) |
| `laravel/framework` | `8.*` | `^13.17` |
| `laravel/sanctum` | `^2.12` | `^4.0` |
| `spatie/laravel-permission` | `^5.5` | `^8.3` |
| root namespace | `App\` | `App\` |
| assets | Laravel Mix (`webpack.mix.js`) | Vite |

Both apps declare `App\`, and the framework majors are five apart, so they cannot share a Composer `vendor/`, an autoloader or a PHP-FPM pool. Moving its code into `app/` under another namespace would mean rewriting ~1600 files and upgrading it five framework majors in one step. So it stays a complete, separate Laravel install inside this repo. It keeps its own `composer.json`, `vendor/`, `.env`, `node_modules/` and runtime, and a reverse proxy decides which app gets each request.

Never add a path from it to the root `composer.json` autoload, and never `require` it from the root app. It has **no `composer.lock`** (upstream never committed one), and it has loose constraints (`*`, `dev-master`). The session that first installs it has to pin and commit a lock inside `legacy/albaraka-holland/`, and use a PHP 7.x runtime that its dependency tree actually resolves on. Laravel 8 needs PHP 7.3 or later, whatever `require.php` says.

## It is a squashed git subtree, so updates come from upstream with subtree pull
It was added with `git subtree add --prefix=legacy/albaraka-holland https://github.com/arb-rajab/albaraka-holland main --squash` from upstream `main` at `a120282`. Pull later upstream changes with the same arguments using `git subtree pull`. Upstream history is squashed, so `git log` here shows one `Squashed 'legacy/albaraka-holland/' content` commit per pull, not the upstream commits. Local edits inside the directory merge against upstream on each pull like any other change. A fix that also belongs upstream should go there first, so it doesn't conflict on the next pull.

## Root tooling is scoped away from it on purpose
Its PHP 7 / Laravel 8 code will never pass this app's Pint preset, ESLint config or Semgrep gate. Each root tool that would otherwise sweep the whole repo excludes `legacy/`:
- `pint.json`: `"exclude": ["legacy"]`
- `eslint.config.js`: `'legacy'` in `ignores`
- `.semgrepignore`: `legacy/`
- `resources/css/app.css`: `@source not '../../legacy';`. Tailwind v4 auto-detects sources from the project root, and without this line it scanned 1226 legacy files and emitted about 7.5KB of extra CSS into this app's bundle.
- `.claude/scripts/pre_push_check.py`: `VENDORED_PREFIXES` drops `legacy/` from the changed-file list

Tools that are already path-scoped don't need a change: PHPStan (`paths:` in phpstan.neon), Prettier (`resources/`), vue-tsc (`tsconfig.json` `include`), Vitest (`resources/js/**/*.test.ts`) and Pest (phpunit.xml testsuites). Only the root `.gitignore`'s `.env` entry is unanchored, so it also ignores `legacy/albaraka-holland/.env`. Its own `.gitignore` covers its `vendor/` and `node_modules/`. When you add a new root-level tool that scans the whole tree, add the same exclusion to it.

The Semgrep exclusion only keeps this app's gate meaningful. It is not a finding that the legacy code is safe. Before `/temp` is reachable in production, the legacy tree needs its own security scan: a separate Semgrep job with a baseline, not a removal of the ignore line.

## Routing: a gateway in front of both apps, planned but not built yet
The target shape: a reverse proxy owns the public port. It sends `/temp` and `/temp/*` to the legacy app's own nginx + PHP 7.x FPM, and sends everything else to this app unchanged. The legacy app then needs `APP_URL` (and its asset URLs) to include `/temp`. It also needs its own TRUSTED_PROXIES setting, and this app's bootstrap.md rule on that applies to both apps. With nginx, match the prefix with `location = /temp` plus `location ^~ /temp/`. A bare `location /temp` also catches `/temperature`, `/templates` and so on.

`compose.yaml` does not have that gateway yet. `laravel.test` still publishes `${APP_PORT:-80}:80`. Adding the gateway means moving that port onto the gateway service. Don't add the service before the legacy runtime exists: a proxy whose upstream host doesn't resolve fails at startup, which would take down `sail up` for this app too. The production equivalent (the server's nginx) is also still to do. `.github/scripts/deploy-remote.sh` ships each release with `git archive`, so `legacy/` is already copied into every release directory. It isn't served, because nginx's root is this app's `public/`.

## Database: the legacy app gets its own database
Its baseline schema is `shop.sql` (plus `sqlupdates/`). That is the store schema (`products`, `product_translations`, `uploads`, `password_resets`) that shared-database.md describes this app once sharing. That sharing is over. Point the legacy app at its own database, never at this app's. Don't reintroduce a shared connection as a shortcut for the `/temp` migration.

## `/` and its redirect are out of scope for this work
`/` stays `WelcomeController`'s `redirect()->away('https://albaraka-holland.nl/')` (routes.md), pinned by `tests/Feature/RootRedirectTest.php`. None of the legacy migration work edits that route, the controller or that test. The gateway must pass `/` to this app untouched. `/temp` is a proxy-level route to another process, so it never appears in `routes/web.php`.

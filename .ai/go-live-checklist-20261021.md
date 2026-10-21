# Go-live checklist (store back office, storefront and warehouse app)

Prepared 2026-10-20 against `develop` at `8cf1dd9` (the PR #452 merge) · shipped in [PR #453](https://github.com/arb-rajab/warehouse_management/pull/453) · documentation only: no application code, migration, seeder, workflow or database was changed to produce it.

**Updated by the go-live hardening PR** (branch `claude/go-live-hardening-fdk4me`, [guide §41](rules/store-backoffice-migration.md#41-go-live-hardening-seeders-the-first-admin-the-production-env-template-event-cache-and-migrate---pretend-2026-10-20)). It changed code for five findings: **F1** (seeders refuse production, items 4.5 and 8.9), **F9** (the `store:create-admin` command, 4.4), **F3** (`.env.production.example`, §2), **F4** (`event:cache` in the deploy script, 5.4 and 5.9) and **F2** (the health migration, 3.4). Where an item below says "fixed in the hardening PR", read it as the state after that PR is merged; every other item, the owner decisions and the Appendix A to E text are unchanged. Nothing in it was applied to any production system.

**Updated by the storefront SEO PR** ([PR #455](https://github.com/arb-rajab/warehouse_management/pull/455), [guide §42](rules/store-backoffice-migration.md#42-storefront-seo-canonical-robots-sitemap-and-json-ld-2026-10-20)). The storefront now prints canonical, robots, Open Graph and JSON-LD tags, serves a generated `/robots.txt` and a cached sitemap, and sends `X-Robots-Tag` on the back offices and APIs. Items 11.4 to 11.9 and gate G14 are the search-engine steps only the owner can do, after launch and outside the repository. Nothing in that PR was applied to any production system.

**Updated by the storefront 500-fixes PR** ([PR #456](https://github.com/arb-rajab/warehouse_management/pull/456), guide [§43](rules/store-backoffice-migration.md#43-storefront-500s-fixed-unit_equal-top-sellers-missing-rows-2026-10-20)). A product with `unit_equal` of 0 or null, a slugless top seller, and a missing blog post or policy row no longer return a 500, and an unpublished blog post is now a 404 (deliberate; the sitemap already excluded it). The remaining slug-less cases are defect D15 in 11.3.

**Updated by the required-fields PR** ([PR #458](https://github.com/arb-rajab/warehouse_management/pull/458), guide [§44](rules/store-backoffice-migration.md#44-required-and-optional-form-fields-2026-10-21), rule [form-required-fields.md](rules/form-required-fields.md)). Forms now mark required fields; the storefront registration form gained the company name input its validator always required (it could not succeed without it). `php artisan forms:audit-required` measured the remaining gap (521 findings, [report](required-fields-audit-20261021.md)); the public forms among them are defect D16 in 11.3.

**Updated by the public address forms PR** ([PR #459](https://github.com/arb-rajab/warehouse_management/pull/459), guide [§45](rules/store-backoffice-migration.md#45-public-forms-phase-2a-address-forms-and-the-checkout-address-pick-2026-10-21)). The storefront and seller address forms and the checkout address pick now validate on the server what they mark and require (the seller endpoints validated nothing); the audit fell to 493 findings (P1 40 → 12). D16 below lists what is left.

**Updated by the phase 2a continuation PR** (branch `claude/phase-2a-longitude-forms-xdumd2`, guide [§47](rules/store-backoffice-migration.md#47-public-forms-phase-2a-continued-the-longitude-columns-seller-registration-create-customer-2026-10-21)). A migration widens `addresses.longitude` and `shops.delivery_pickup_longitude` to `decimal(18,15)`, so every longitude from -180 to 180 is stored (a value of 100 or more was a MySQL error); the address forms accept ±180 again, and the map pin starts at the admin's default location (Google Map settings), else Amsterdam, instead of Sydney. Seller registration (`shops.store`) validates within its columns and marks and requires its fields; the representative's create-customer form marks and requires the twelve fields its endpoint requires. The audit fell to 465 findings (P1 12 → 8). D16 below lists what is left.

Work it top to bottom in one sitting; the appendices carry the longer explanations. Every item says **who** does it, cites the **evidence** that justifies it and says how to **verify** it.

## How to read this file

| Tag | Meaning |
| --- | --- |
| **owner** | A decision or confirmation only the owner can give. Anything that depends on a system this repository cannot show (DNS, TLS, mail provider, hosting, backup storage, credentials, GitHub settings) is marked **Owner must confirm** and is never claimed done here. |
| **developer** | A person running commands or reviewing configuration (the "operator"). |
| **automated** | A CI job, the scheduler or the framework does it; the person only reads the result. |

- `$PHP` is the PHP 8.5 binary (the `HOSTINGER_PHP_BINARY` secret, `deploy.yml:225`); `$APP` is the live release, `$DEPLOY_PATH/current`. Run `artisan` from `$APP`.
- **Verified here** means I ran it in this session against a throwaway SQLite stand-in in `APP_ENV=production` (scratch files outside the repository, nothing persistent touched). **Read from code** means I read the code but did not execute it. Nothing was run on MySQL or against any production system (Appendix F).
- Placeholders are in `<ANGLE_BRACKETS>`. No secret or credential appears in this file; variables are referred to by name only.
- Guide = [store-backoffice-migration.md](rules/store-backoffice-migration.md), ledger = [store-backoffice-reconciliation-20261011.md](store-backoffice-reconciliation-20261011.md), audit = [role-grants-audit-20261020.md](role-grants-audit-20261020.md) (+ `.json`, `.sql`).

## 0. What reading the repository changed about the plan

These findings are why some items below differ from what the README or `.env.example` imply. Each is handled by the item(s) named.

| # | Finding | Evidence | Handled by |
| --- | --- | --- | --- |
| F1 | `php artisan db:seed --class=StoreUserSeeder --force` worked in production and created `store-admin@example.com` / `password` as a **Super Admin**; only the umbrella `DatabaseSeeder` refused production. **Verified here. Fixed in the hardening PR:** `StoreUserSeeder`, `CellStatusLogSeeder`, `CellVerificationRoundSeeder` and `StagingRealisticDataSeeder` now refuse production themselves (`Database\Seeders\Concerns\RefusesProduction`), and `StoreRoleSeeder` creates the `Clerk` demo role only in `local` and `testing`. | `database/seeders/Concerns/RefusesProduction.php`, `tests/Feature/Database/SeedersRefuseProductionTest.php` | 4.5 |
| F2 | `migrate --pretend` was not read-only: it created `wms_migrations` and really created the health table on its own SQLite connection, after which the real `migrate` failed with "table already exists". **Verified here. Cause and fix (hardening PR):** pretend mode is applied to the connection returned by the migration's `getConnection()`; the health migration did not override it (Telescope's does), so its `Schema::connection('health')` calls ran for real. It now names the `health` connection, so pretend prints its SQL and creates nothing, and a real `migrate` after a pretend run works (pinned by `CreateHealthTablesTest`). **Still true:** pretend creates the empty `wms_migrations` table itself (Laravel's `prepareDatabase()` runs `migrate:install` before pretending), which no app code can prevent, so it stays unsafe before the 3.2 rename. | `vendor/laravel/framework/src/Illuminate/Database/Console/Migrations/MigrateCommand.php:112-123,140-152`, `database/migrations/2026_08_16_151529_create_health_tables.php` | 3.4 |
| F3 | `.env.example` overrides safe production defaults: `SESSION_SECURE_COOKIE=false`, `CSP_REPORT_ONLY=true`, `TELESCOPE_ENABLED=true`, `APP_DEBUG=true`, `MAIL_MAILER=log`, `DB_CONNECTION=sqlite`. Copying it to production silently disables cookie security and CSP enforcement. **Addressed in the hardening PR** by `.env.production.example` (`.env.example` itself is unchanged). | `.env.example:2,4,46,66,70,105,123`; defaults in `config/session.php:177`, `config/secure-headers.php:630`, `config/telescope.php:20` | §2 |
| F4 | Production health runs `OptimizedAppCheck` with no sub-checks, so it also demands cached **events**; the deploy script cached config, routes and views but never events, so the health check failed until `event:cache` was run by hand. **Read from code. Fixed in the hardening PR:** `deploy-remote.sh` now runs `config:cache`, `event:cache`, `route:cache`, `view:cache` (the order of `artisan optimize`) after `migrate` and before the release is published; `deploy-remote-test.sh` pins the order and that a failing `event:cache` rolls the deploy back. | `app/Providers/HealthServiceProvider.php:38`, `vendor/spatie/laravel-health/src/Checks/Checks/OptimizedAppCheck.php:21-37`, `.github/scripts/deploy-remote.sh:329-331` | 5.9, 9.1 |
| F5 | Uploads are written to `public/uploads/all` unless `FILESYSTEM_DISK=s3`; every release is a fresh `git archive`, and only entries under `shared/public/` are re-linked. Without `shared/public/uploads`, each deploy discards every uploaded file. | `app/Services/Concerns/WritesUploads.php:34-37`, `.github/scripts/deploy-remote.sh:187,220-228` | 5.6 |
| F6 | There is **no CSP report endpoint**: `report-uri` is empty and no route exists, so violations are visible only in the browser console. `img-src` allows only `'self' data: blob:` plus the OpenStreetMap tile host, so product images served from another origin are blocked once CSP is enforced. | `config/secure-headers.php:636-638,700-713` | 6.3, 6.4 |
| F7 | `check:commands` is deliberately not scheduled, so the "Fetch products" button only queues a `cron_jobs` row that nothing processes until someone runs the command. | `routes/console.php:34-47`, `app/Http/Controllers/ProductController.php:47-94`, `app/Console/Commands/CheckForCommands.php:24-60` | 8.3 |
| F8 | The scheduler runs `php artisan queue:work` as a child process (`Process::fromShellCommandline`, needs `proc_open`), and the deploy script records that `proc_open` is **disabled** for the SSH PHP on the Hostinger host. | `vendor/laravel/framework/src/Illuminate/Console/Scheduling/Event.php:216`, `.github/scripts/deploy-remote.sh:278-283` | 5.1 |
| F9 | After `migrate` there is no `Super Admin` role (0 store roles, 0 grants, 182 permissions). Only `StoreRoleSeeder` created it, and seeders must not run in production. **Verified here. Fixed in the hardening PR:** `php artisan store:create-admin` creates the role, the first Super Admin and the assignment (4.4). | `app/Console/Commands/CreateStoreAdminCommand.php`, `database/seeders/StoreRoleSeeder.php` | 4.4 |
| F10 | `migrate:rollback`, `migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` are prohibited in production, so the only database "undo" is restoring a dump. **Verified here.** | `app/Providers/AppServiceProvider.php:137-139` | 10.2 |
| F11 | `sast.yml` has no `workflow_dispatch`: it runs only on push to `main`/`develop` and on pull requests. | `.github/workflows/sast.yml:3-8` | 1.5 |
| F12 | The `/health`, Pulse and Telescope gates all use the warehouse `admin` role on the `web` guard (`wms_users`), which the store Super Admin does not have. | `app/Providers/HealthServiceProvider.php:25`, `app/Providers/AppServiceProvider.php:45`, `app/Providers/TelescopeServiceProvider.php:73-74` | 4.6 |
| F13 | The store API is mounted under `/temp/api/v2/...`, not `/api/v2/...`; and when a minimum app version is set, `EnsureMinimumAppVersion` answers 426 to every `api`-group request without `X-App-Version`, store routes included. | `app/Providers/RouteServiceProvider.php:53-57`, `bootstrap/app.php:76`, `app/Http/Middleware/EnsureMinimumAppVersion.php:20-37` | 8.8 |
| F14 | `migrate` includes a data-destructive step on an existing store database: duplicate `coupon_usages` rows are deleted before a unique index is built. | `database/migrations/2026_10_03_000004_add_unique_user_coupon_index_to_coupon_usages_table.php:28` | 3.3, 3.6 |

---

## 1. Prerequisites and go/no-go gates

- [ ] **1.1** **[owner]** Decide and write down what the production database is: a new empty MySQL database, or an existing store database. The README says production shares one database with an existing store app; the shared-database rule says that arrangement is over and each app has its own. **Owner must confirm.**
  - Evidence: `README.md:79` vs `.ai/rules/shared-database.md:16-20`; legacy audit phase 5 targets "the store's MySQL DB as the physical host" (`.ai/rules/legacy-albaraka-holland.md`, phase table, "5 | DB merge").
  - Verify: the answer is recorded in §12 (gate G1). It decides whether 3.2 (the `migrations` → `wms_migrations` rename) applies and how much weight 3.3/3.6 carry.
- [ ] **1.2** **[owner]** Confirm the hosting target is ready: GitHub environment `main` exists with secrets `HOSTINGER_SSH_PRIVATE_KEY`, `HOSTINGER_SSH_KNOWN_HOSTS`, `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_USER`, `HOSTINGER_SSH_PORT`, the optional `HOSTINGER_PHP_BINARY`, and variables `HOSTINGER_DEPLOY_PATH` and `HOSTINGER_KEEP_RELEASES`; the host has `$DEPLOY_PATH/shared/.env`, `$DEPLOY_PATH/repo` (a git checkout), `mysqldump`, `mysql`, `composer` and `node`/`npm`. **Owner must confirm** (secrets and hosts are not visible here); the "one-time layout setup" the script refers to is not in the repository.
  - Evidence: `.github/workflows/deploy.yml:174-226`, `.github/scripts/deploy-remote.sh:147-152`.
  - Verify: a green deploy to `staging` (1.6) proves the layout; for `main` the same run on the `main` environment.
- [ ] **1.3** **[developer]** Run one **full** `tests` workflow on `develop` (about 40 PRs were merged with `[skip ci]`, so no run has covered this tree). GitHub: Actions → `tests` → Run workflow → branch `develop`. CLI: `gh workflow run tests.yml --ref develop`.
  - Evidence: `.github/workflows/tests.yml:20` (`workflow_dispatch`) and `:104-106` ("A … run checks everything"); `git log --merges --format=%s | grep -c 'skip ci'` prints 23 merge commits with the marker on this branch. GitHub Actions API (read this session): the newest `tests` run is #874 (2026-10-18, another branch) and the newest `deploy` runs are #305 (`staging`, PR #443) and #304 (`develop`, PR #441); none is for `8cf1dd9`, and the later back-office PRs (#444 to #452) carry `[skip ci]` in their merge commits. I did not audit which of the intervening PRs ran CI.
  - Verify: `gh run list --workflow tests.yml --branch develop --limit 1` shows `completed success`, and the run's `head_sha` is the release SHA recorded in §12. Jobs expected: Prettier, JS checks, Pint, PHPStan, Pest, Pest (MySQL migrations), Dependency audit, Playwright E2E, Deploy script.
- [ ] **1.4** **[automated]** Read the result of that run against what local, SQLite-only verification could not cover (Appendix D has the full table): the MySQL migration group (`tests.yml:365-372`), composer and npm audits (`:401-405`), the deploy-script rollback test (`:251-252`), the full Playwright suite (`:453`) and a font-bearing `npm run build` (`:304-305`, fetches `fonts.bunny.net`).
  - Evidence: ledger `store-backoffice-reconciliation-20261011.md:5212-5214` ("Not run: Playwright, the MySQL migration group, the composer/npm audits and the deploy-script test").
  - Verify: every job above is green; any red job is a **no-go** until fixed on `develop` and the run repeated.
- [ ] **1.5** **[automated]** Get one SAST (Semgrep) pass on the release content. It cannot be dispatched by hand; it runs on pull requests and on push to `main`/`develop`. Open the promotion pull request `develop` → `staging` **without** `[skip ci]`.
  - Evidence: `.github/workflows/sast.yml:3-8,48-101,119-133`; the repo's promotion flow shows in deploy runs for "Merge develop into staging" / "Merge staging into main" (GitHub Actions history, read via the API).
  - Verify: the `sast` check on that PR is green; the `semgrep-results` artifact (kept 30 days, `sast.yml:135-141`) shows no ERROR-severity finding.
- [ ] **1.6** **[developer]** Deploy to **staging** first by merging the `develop` → `staging` PR. `deploy` triggers on push; `verify-tested` accepts the merge only if a successful `tests` run exists for the pushed SHA or the second parent with an identical tree, otherwise it runs the whole suite first.
  - Evidence: `.github/workflows/deploy.yml:25-30,66-124,129-140,164-172`. After 1.3, the staging PR finds the run from 1.3 and does not repeat the suite.
  - Verify: the `deploy` run for `staging` is green and its log ends with "Deployed <SHA> as <release_id>" (`deploy-remote.sh:417`). Walk §7 on staging with the environment of §2 (production-like, CSP enforced).
- [ ] **1.7** **[owner]** Adopt the rule for who may deploy to production. Proposed: only the owner, or a developer the owner names in §12, merges the `staging` → `main` PR (a push to `main` deploys to the `main` environment); the `force` input of the `deploy` workflow ("Deploy without testing") is not used for go-live; the `main` environment has required reviewers. **Owner must confirm** the GitHub environment protection rules; the workflow comment says they are configured in repository settings, which are not visible here.
  - Evidence: `.github/workflows/deploy.yml:31-35,89-91,152-153`; `AGENTS.md` (do not commit or push without authorization).
  - Verify: GitHub → Settings → Environments → `main` lists required reviewers; the sign-off names the person (§12, G3).
- [ ] **1.8** **[owner]** Freeze `develop`/`staging`/`main` between the green gate run and the production deploy: any later content change makes the tested tree stale, and a skipped or `[skip ci]` merge in between would be deployed untested.
  - Evidence: `.ai/rules/ci.md:37-43` (a trailer or a run covers exactly one tree); `deploy.yml:66-124`.
  - Verify: `git rev-parse origin/staging^{tree} origin/develop^{tree}` print the same tree id on the day of the production merge.
- [ ] **1.9** **[owner]** The legacy store server was assessed as compromised (webshell, remote-code addon installer). Confirm production does **not** reuse that server or its secrets: new `APP_KEY`, database credentials, `WMS_TOKEN`, `OTAJER_TOKEN`, FCM credentials. **Owner must confirm.**
  - Evidence: `.ai/rules/legacy-albaraka-holland.md:39-93` (webshell, "Treat the production store server as compromised", rotate list), `:126-131` (`WMS_TOKEN` is "compromised"), `:1746-1748`.
  - Verify: owner statement in §12 (G4); the host's access log has no `POST /update` or `GET /cron_1` style hits (`legacy-albaraka-holland.md:82`).
- [ ] **1.10** **[developer]** Confirm no web-shell or installer files can reach the document root: only `public/index.php` is a PHP file in the repository.
  - Evidence: `.ai/rules/legacy-albaraka-holland.md:39-60,96-104`.
  - Verify: `git ls-files public | grep -E '\.php$'` prints only `public/index.php` (checked here); on the host `find "$APP/public" "$DEPLOY_PATH/shared/public" -name '*.php' ! -name index.php` prints nothing.
- [ ] **1.11** **[owner]** Confirm DNS, TLS certificate, the web server's document root (`$APP/public`, never the project root) and PHP version selection (`HOSTINGER_PHP_BINARY`, PHP 8.5). **Owner must confirm.**
  - Evidence: `composer.json:12` (`"php": "^8.5"`), `deploy.yml:222-225`, legacy audit "0.2 The legacy document root is the project root" (`legacy-albaraka-holland.md:96-104`).
  - Verify: `curl -sI https://<HOST>/ | head -1` answers over TLS; `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/.env` is 403/404, never 200; `$PHP -v` prints 8.5.x.

## 2. Environment and configuration

The production `.env` lives at `$DEPLOY_PATH/shared/.env` and is symlinked into every release (`deploy-remote.sh:200`). Start from [`.env.production.example`](../.env.production.example) (added by the hardening PR: production-safe values and a comment on every key this section flags; `tests/Feature/ProductionEnvExampleTest.php` pins them), **not** from `.env.example`, which is for local development and CI and is deliberately unchanged. Replace every `CHANGE_ME` and fill the empty secrets on the server only. Appendix A still lists every production-relevant key, the `.env.example` default and whether that default is unsafe.

- [ ] **2.1** **[developer]** `APP_ENV=production`, `APP_DEBUG=false`, a freshly generated `APP_KEY` (`php artisan key:generate --show`, paste into the file; never reuse a legacy key), `APP_URL=https://<HOST>` with no trailing slash. `.env.example` ships `local`, `true`, empty, `http://localhost:8000`.
  - Evidence: `.env.example:2-5`, `config/app.php:69,82,120`, `config/filesystems.php:46` (public disk URL derives from `APP_URL`), `app/Providers/HealthServiceProvider.php:37`.
  - Verify: `$PHP artisan about --only=environment` shows `Environment .. production` and `Debug Mode .. OFF` (checked here); `$PHP artisan tinker --execute='echo config("app.url");'` prints the https URL.
- [ ] **2.2** **[developer]** Database: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. The default connection is **sqlite** (`.env.example:46`, `config/database.php:40`); the deploy script and the backup both assume MySQL. Keep `DB_CHARSET`/`DB_COLLATION` unset (defaults `utf8mb4` / `utf8mb4_unicode_ci`).
  - Evidence: `config/database.php:97-115`, `deploy-remote.sh:154-159,175-176`, `config/backup.php:92-94`.
  - Verify: `$PHP artisan db:show` prints driver `MySQL` and the right database; `$PHP artisan migrate:status` connects (it prints "Migration table not found" on an empty database, which is fine).
- [ ] **2.3** **[developer]** Session: remove `SESSION_SECURE_COOKIE=false` from the copied file (or set it to `true`); leave `SESSION_DRIVER=database`, `SESSION_HTTP_ONLY` unset (true) and `SESSION_SAME_SITE` unset (lax). `.env.example:105` overrides the production default of `true`. The cookie name derives from `APP_NAME`; changing `APP_NAME` later logs everyone out.
  - Evidence: `config/session.php:21,89,130-133,177,190,207`, `.env.example:100-107`, `.ai/rules/legacy-albaraka-holland.md:1575` ("give the session cookies different names" if the store and warehouse ever sit on one parent domain).
  - Verify: `curl -s -D - -o /dev/null https://<HOST>/temp/login | grep -i set-cookie` shows `secure; httponly; samesite=lax` on the session cookie.
- [ ] **2.4** **[developer]** Cache, queue and filesystem drivers: `CACHE_STORE=database`, `QUEUE_CONNECTION=database` (tables `wms_cache`, `wms_jobs`, `wms_failed_jobs`), `FILESYSTEM_DISK=local` for uploads on the host's disk or `s3` with `AWS_*` set. Decide which (**owner**, 5.6); there is no Redis service in use.
  - Evidence: `config/cache.php:20`, `config/queue.php:16,41,126`, `config/filesystems.php:18,52-63`, `.env.example:109-121`.
  - Verify: `$PHP artisan tinker --execute='echo config("cache.default"),",",config("queue.default"),",",config("filesystems.default");'`.
- [ ] **2.5** **[developer]** Mail: set `MAIL_MAILER=smtp` (or your provider's mailer), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` and a real `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`. `.env.example` ships `MAIL_MAILER=log` and `hello@example.com`, so every password reset, verification, health and backup mail would be written to a log file. Mail host, SPF/DKIM and deliverability: **Owner must confirm** (no evidence in the repo).
  - Evidence: `.env.example:123-130`, `config/mail.php:17,113-116`; SMTP credentials are env-only since the settings writer was retired (`legacy-albaraka-holland.md:153-154`).
  - Verify: with `smtp_settings` and `send_newsletter` granted (Super Admin has both), open `/temp/admin/smtp-settings` and send the test email (route `test.smtp`, `routes/shop-admin.php:389`); it arrives in an inbox you own.
- [ ] **2.6** **[developer]** Security-header flags: leave `HSTS_ENABLED=true`, `CSP_ENABLED=true`, `WAF_ENABLED=true`, `HONEYPOT_ENABLED=true`, and **delete `CSP_REPORT_ONLY=true`** (or set it `false`) so the policy is enforced. Without the variable the policy is report-only everywhere except production.
  - Evidence: `.env.example:55-68`, `config/secure-headers.php:148,618,630`, `config/waf.php:16`, `config/honeypot.php:10`.
  - Verify: `curl -sI https://<HOST>/admin/login | grep -i '^content-security-policy'` prints `Content-Security-Policy:` and **not** `Content-Security-Policy-Report-Only:`.
- [ ] **2.7** **[developer]** `TRUSTED_PROXIES=<proxy IP or CIDR list>` if (and only if) the site sits behind a reverse proxy, CDN or load balancer; never `*`. Without it every client looks like the proxy, which collapses the login rate limit into one bucket and defeats the Telescope/Pulse/health IP allowlists.
  - Evidence: `.env.example:32-39`, `bootstrap/app.php:53-66`, `.ai/rules/bootstrap.md` ("TRUSTED_PROXIES must be set in production…"). The hosting topology is **Owner must confirm**.
  - Verify: provoke one WAF block from a known client address (6.9) and read the `ip` field of the `Blocked malicious request` log entry; it must be the client's address, not the proxy's (`app/Http/Middleware/BlockMaliciousRequests.php:177-182`).
- [ ] **2.8** **[developer]** Store integration keys, by name only: `WMS_TOKEN` (new secret; the warehouse integration needs it, otherwise `/temp/api/v2/warehouse/*` answers 401 to everyone), `OTAJER_TOKEN` (reissued by the ERP vendor), `OTAJER_URL` (defaults to the production ERP host), `STORE_PRODUCTS_SYNC_URL` (its path carries the API key), `STORE_ASSET_BASE_URL`. `FORCE_HTTPS` stays unset behind a proxy that does not report https (it would redirect forever); `DEMO_MODE` stays unset.
  - Evidence: `.env.example:7-15,140-146`, `config/app.php:133,210`, `config/services.php:151-152`, `config/store.php:34,49,55`, `app/Http/Middleware/WmsAuthMiddleware.php`, `.ai/rules/legacy-albaraka-holland.md:126-131,1746-1748,1763`.
  - Verify: `$PHP artisan tinker --execute='foreach (["app.wms_token","store.products_sync_url","services.otajer.token"] as $k) echo $k,"=",blank(config($k)) ? "MISSING" : "set","\n";'` prints `set` for all three (it never prints the values).
- [ ] **2.9** **[developer]** Third-party keys the store references: set only the ones you use, from the list in Appendix A (map, captcha, geolocation, social login, Firebase, payment gateways). `STORE_PAYMENT_GATEWAYS` is empty by default, which makes every gateway route a 404; only cash on delivery and the manual payment methods work until a gateway is deliberately enabled (see 8.6).
  - Evidence: `config/services.php:20-152`, `config/store.php:77`, `app/Http/Middleware/EnsurePaymentGatewayEnabled.php`.
  - Verify: `$PHP artisan route:list --path=temp/payhere` lists routes, but `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/temp/payhere/checkout/notify` is 404 while the gateway list is empty.
- [ ] **2.10** **[developer]** Logging: `LOG_STACK=daily` (rotates, keeps `LOG_DAILY_DAYS`, default 14) and `LOG_LEVEL=warning` or higher. `.env.example` ships `single` (one unbounded `laravel.log`) and `debug`.
  - Evidence: `.env.example:41-44`, `config/logging.php:55-74`.
  - Verify: after the first requests `ls $DEPLOY_PATH/shared/storage/logs` shows `laravel-<date>.log`, not an ever-growing `laravel.log`.
- [ ] **2.11** **[developer]** **Telescope off.** Set `TELESCOPE_ENABLED=false` (or remove the line: it defaults to off outside `local`). `.env.example:70` turns it on. When enabled it records request, query, model and mail data into `database/telescope.sqlite`.
  - Evidence: `config/telescope.php:20`, `tests/Feature/ConfigHardeningTest.php:51-58` (default off), `vendor/laravel/telescope/src/TelescopeServiceProvider.php:27` (no routes when disabled).
  - Verify: `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/telescope` is 404.
- [ ] **2.12** **[owner]** **Pulse: decide** disable or keep gated. `PULSE_ENABLED` defaults to true and `.env.example:75` sets it. If kept: it is reachable only by a warehouse `admin` user (`viewPulse`), from an IP in `PULSE_ALLOWED_IPS` (falls back to `TELESCOPE_ALLOWED_IPS`, which `.env.example:73` sets to `127.0.0.1`); an empty allowlist fails closed in production. Note `PULSE_ENABLED=false` stops recording but the `/pulse` route stays registered, still gated.
  - Evidence: `config/pulse.php:47,124-128,142`, `app/Providers/AppServiceProvider.php:45`, `app/Http/Middleware/RestrictToAllowedIps.php:28-34`, `vendor/laravel/pulse/src/PulseServiceProvider.php:74,107-114`.
  - Verify: from an address outside the allowlist `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/pulse` is 403.
- [ ] **2.13** **[developer]** Health: set `HEALTH_ALLOWED_IPS` (or rely on `TELESCOPE_ALLOWED_IPS`) to the operator IPs and `HEALTH_TO_ADDRESS` to a monitored inbox. Unset, notifications go to a reserved `.invalid` address and fail silently. `HEALTH_SECRET_TOKEN` and `SCHEDULE_HEARTBEAT_URL` are optional.
  - Evidence: `config/health.php:43,62,79,141,167,175`, `routes/web.php:27-29`, `.env.example:80-88`.
  - Verify: an anonymous `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/health` is 403 (or a login redirect), never 200.
- [ ] **2.14** **[automated]** Confirm there is no Horizon, and no other queue/ops dashboard to disable: `composer.json` requires no `laravel/horizon` (`config/health.php:131-133` only carries an optional heartbeat URL), `laravel/boost` is dev-only and the deploy installs with `--no-dev`, and the Scramble API docs at `/docs/api` answer 403 outside `local` because no `viewApiDocs` gate is defined.
  - Evidence: `composer.json:28-32,51`, `deploy-remote.sh:271-275`, `vendor/dedoc/scramble/src/Http/Middleware/RestrictedDocsAccess.php:11-17`, `grep -rn viewApiDocs app` finds nothing.
  - Verify: `$PHP artisan route:list | grep -c -E 'horizon|_boost'` prints 0; `curl -s -o /dev/null -w '%{http_code}' https://<HOST>/docs/api` is 403.
- [ ] **2.15** **[developer]** `BACKUP_ARCHIVE_PASSWORD` (required: `backup:run` refuses in production without it), `BACKUP_OFFSITE_DISK=s3` with `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `BACKUP_NOTIFICATION_EMAIL`. Which off-host storage is used and who holds the archive password: **Owner must confirm.**
  - Evidence: `app/Providers/AppServiceProvider.php:48-54`, `config/backup.php:168,185,246`, `.env.example:90-98`.
  - Verify: `$PHP artisan backup:run --only-db` (9.2) completes and the archive appears on both disks.

## 3. Database

- [ ] **3.1** **[developer]** Know the rules before touching the schema: every table this app owns carries a `wms_` prefix and the names are pinned by `SharedDatabaseTableNamesTest`; `products` is WMS-owned and `products:sync` is its intended single writer; `uploads` and the store's other tables are guarded `hasTable()` creates that no-op where the table already exists.
  - Evidence: `.ai/rules/shared-database.md:22-35,37-65,382-406,467-553`, `database/migrations/2026_09_28_004009_create_sellers_table.php:12-18`.
  - Verify: `tests/Feature/SharedDatabaseTableNamesTest.php` is green in 1.3.
- [ ] **3.2** **[developer]** **Existing database only:** if the target already ran this app's migrations under the old bookkeeping name, rename it once, before any `migrate`: `RENAME TABLE migrations TO wms_migrations;`. Skip this on a first install (README: production "needs nothing here"). Never run it if `wms_migrations` already exists; and do not run `migrate --pretend` first (it creates an empty `wms_migrations`, F2 and 3.4).
  - Evidence: `README.md:83-101`, `config/database.php:180-181`, `.ai/rules/shared-database.md:547-553`.
  - Verify: `SHOW TABLES LIKE 'wms_migrations';` returns a row and `SHOW TABLES LIKE 'migrations';` returns none. A database that predates the signed-`products.id` alignment must be rebuilt, not migrated in place (`README.md:97-101`).
- [ ] **3.3** **[developer]** Take a backup **before** migrating, even though the deploy script also snapshots. For the first install and for any manual migrate run: `mysqldump --single-transaction --routines --triggers --add-drop-table <DB> > pre-migrate-<date>.sql`, then check `tail -n 5 pre-migrate-<date>.sql | grep 'Dump completed'`. Keep a copy off the host (**Owner must confirm** where). On an existing store database this matters: migration `2026_10_03_000004` deletes duplicate `coupon_usages` rows (F14).
  - Evidence: `.github/scripts/deploy-remote.sh:312-322` (the snapshot the deploy takes, `shared/backups/<release_id>.sql`), `database/migrations/2026_10_03_000004_add_unique_user_coupon_index_to_coupon_usages_table.php:28`.
  - Verify: the dump ends with `-- Dump completed`; `ls -l $DEPLOY_PATH/shared/backups` shows the deploy's own snapshot after the first deploy.
- [ ] **3.4** **[developer]** Preview pending work with the **read-only** command `php artisan migrate:status --pending`; it writes nothing, even on an empty database. `migrate --pretend` (prints the SQL) is now safe for the health table (F2, fixed in the hardening PR) but is **still not read-only**: Laravel installs the empty migration table before pretending, so on an empty database it leaves `wms_migrations` behind, and on an existing store database it would create `wms_migrations` next to the old `migrations` table and make the 3.2 `RENAME TABLE` fail. Use `--pretend` only against a disposable copy, or after the 3.2 rename. Before the fix it also created the health table, after which the real `migrate` failed with `table "health_check_result_history_items" already exists`; that no longer happens.
  - Evidence: `vendor/laravel/framework/src/Illuminate/Database/Console/Migrations/MigrateCommand.php:112-123,140-152`, `database/migrations/2026_08_16_151529_create_health_tables.php` (`getConnection()`), `tests/Feature/Database/CreateHealthTablesTest.php`. **Verified here** on scratch SQLite: before the change a pretend run left the health table and `wms_migrations`; after it, only `wms_migrations`, and the real `migrate` completed (the test mutation, reverting the migration, fails it). Not run on MySQL.
  - Verify: `migrate:status` on an empty database prints "Migration table not found" and creates nothing; after a real migrate it prints "No pending migrations". On a scratch copy, `php artisan migrate --pretend --force` prints the SQL (about a thousand lines) and ends without error, and the real `migrate` then succeeds.
- [ ] **3.5** **[developer]** Apply the migrations: normally the deploy does it (`artisan migrate --force` inside maintenance mode, after the snapshot). For a manual first install: `$PHP artisan migrate --force`. The repository holds 156 migration files at this commit.
  - Evidence: `deploy-remote.sh:309-326`; `ls database/migrations | wc -l` prints 156.
  - Verify: `$PHP artisan migrate:status | grep -c Ran` prints 156 on a fresh database; `SELECT COUNT(*) FROM wms_migrations;` returns 156.
- [ ] **3.6** **[developer]** Review the migrations this series added (Appendix C lists them): permission-defining migrations (`add_store_upload_permissions`, `add_customer_administration_permissions`, `add_offers_catalog_permissions`, `add_store_logistics_permissions`, `add_order_entry_permissions`, `define_store_blog_and_support_action_permissions`, `sync_store_permissions`) insert rows and grant nothing; the schema ones are `add_section_to_wms_permissions_table`, `add_legacy_columns_to_store_tables`, `add_upload_metadata_to_uploads`, `create_wms_gateway_payments_table`, `create_wms_store_verification_codes_table`, the coupon unique index and store indexes, `add_allowed_open_levels_to_wms_settings_table`, `add_customer_archive_column_to_users_table`, `create_store_order_submissions_table` and `add_deleted_at_to_wms_users_table`.
  - Evidence: `database/migrations/` (Appendix C); `.ai/rules/store-backoffice-migration.md:581-587`.
  - Verify: after migrate, `SELECT COUNT(*) FROM wms_role_has_permissions;` is 0 on a fresh install (verified on the stand-in).
- [ ] **3.7** **[developer]** Cover the MySQL-specific risks that SQLite cannot show (details in Appendix C.2): signed `int(11)` product foreign keys (errno 3780 if a `bigint unsigned` key is referenced), the FULLTEXT index on `products(name, ar_name)` (errno 1191 if the `MATCH` column list changes), `->change()` column alterations on populated store tables, collation, and DDL locking. The SQLite suite ignores all of these; only the four MySQL migration tests in CI (1.4) and a real staging migrate cover them.
  - Evidence: `.ai/rules/shared-database.md:103-152,291-320,536-545`; guide `store-backoffice-migration.md:885-890` ("MySQL locking/collation/signed-FK compatibility are still unverified"); `database/migrations/2026_09_30_000000_add_legacy_columns_to_store_tables.php:67,71,75`; `deploy-remote.sh:11-13` (DDL auto-commits).
  - Verify: `Pest (MySQL migrations)` green (1.3); staging `migrate --force` completed from an empty schema; `SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_COLLATION <> 'utf8mb4_unicode_ci';` returns 0 rows on a fresh install (an existing store database may differ; decide per table).
- [ ] **3.8** **[developer]** After migrate confirm the permission baseline: 182 `store` permissions, no store role, no grants. (Roles and grants are created in §4.)
  - Evidence: guide `store-backoffice-migration.md:4168-4195`; **Verified here**: 182 / 0 / 0.
  - Verify: `SELECT (SELECT COUNT(*) FROM wms_permissions WHERE guard_name='store') AS permissions, (SELECT COUNT(*) FROM wms_roles WHERE guard_name='store') AS roles, (SELECT COUNT(*) FROM wms_role_has_permissions) AS grants;` returns `182, 0, 0`.
- [ ] **3.9** **[owner]** Accept (or change) the retained second writers of `products`: brand and product deletion, product duplication, digital and physical product create/edit, and the queued `import:all-products-v2` still write the table beside `products:sync`. The owner decision is pending in the guide.
  - Evidence: `.ai/rules/shared-database.md:400-406`, guide `store-backoffice-migration.md:658-664`.
  - Verify: recorded in §12 (G5) and in the risk register (4.11, R6).
- [ ] **3.10** **[developer]** Record a known documentation conflict for the sign-off: the shared-database rule says this app is read-only on `uploads`, but the uploader writes `Upload` rows.
  - Evidence: `.ai/rules/shared-database.md:45-50` vs `app/Services/Concerns/WritesUploads.php:41-48`.
  - Verify: after the first upload in §7, `SELECT id, file_name, user_id FROM uploads ORDER BY id DESC LIMIT 1;` shows the new row.

- [ ] **3.10** **[developer]** The reference data loads with `php artisan migrate` (guide §46): 246 countries (21 enabled), 4,089 states, 26 currencies, 4 languages, 7 CMS pages and the default-currency setting. It only inserts what is missing, so it is safe on a database that already has the legacy rows. Check after the first deploy: `SELECT COUNT(*) FROM countries WHERE status = 1;` returns 21 (or your own count if it already had rows) and `SELECT value FROM business_settings WHERE type = 'system_default_currency';` returns the Euro's id.
  - Evidence: `database/migrations/2026_10_21_100000_load_legacy_reference_data.php`, `app/Support/ReferenceData.php`, `tests/Feature/Database/LoadLegacyReferenceDataTest.php` (green on SQLite and on MySQL).
  - Verify: `/temp/register` shows a country dropdown with Belgium, France, Germany and the Netherlands first, and the terms pop-up shows the terms text.
- [ ] **3.11** **[owner]** Review the enabled countries on the admin Countries page. The legacy store sold to the 21 listed in guide §46; enable or disable others as needed (the phone field on the registration page offers exactly the enabled countries).
- [ ] **3.12** **[owner]** Update the currency exchange rates on the admin Currencies page. They are the legacy store's 2023 values (USD = 1, Euro = 0.92) and are stale.
- [ ] **3.13** **[owner]** Write the three empty policy pages (seller, return and support policy) and read the Terms and Privacy text once: it is the legacy text, cleaned of Word formatting. The privacy text names `info@albarakabv.com`. Zones and shipping set-up were empty in the legacy store and still are.

## 4. Permissions and the first admin

The owner launches with the **Super Admin role only**; staff roles are created later in the app. `Gate::before()` passes a Super Admin through every permission gate.

- [ ] **4.1** **[developer]** `php artisan store:sync-permissions --dry-run`. On a database that has run the migrations it prints `Dry run, nothing written: 0 would be created, 182 already exist (182 store permissions defined in code).` (**Verified here**); on a database the migration has not reached it would list 182 as to-be-created. The command only inserts missing `store`-guard rows, never updates or deletes, never touches the `web` guard and grants nothing.
  - Evidence: `app/Console/Commands/SyncStorePermissionsCommand.php:10,28-29`, `app/Support/StorePermissions.php:276`, guide `store-backoffice-migration.md:4168-4195`.
  - Verify: exit code 0 and the count line above; `--dry-run` writes nothing.
- [ ] **4.2** **[developer]** `php artisan store:sync-permissions`. Expected `0 created, 182 already existed (182 store permissions defined in code).` when the migration already ran; `182 created, 0 already existed …` if run first. A second run always creates nothing.
  - Evidence: same files; **Verified here**.
  - Verify: re-run and see `0 created`.
- [ ] **4.3** **[developer]** Confirm **182 store permissions and 0 role grants** with the query in 3.8 (the sync grants nothing to any role or user).
  - Evidence: guide `store-backoffice-migration.md:4160-4166`; audit `role-grants-audit-20261020.md:96-113`.
  - Verify: `182, 0, 0`; also `SELECT COUNT(*) FROM wms_model_has_permissions;` is 0.
- [ ] **4.4** **[developer]** Create the real first Super Admin with the command (`store:create-admin`, added by the hardening PR; before it existed this was a tinker recipe). Run it once on the host, from `$APP`:
  ```bash
  $PHP artisan store:create-admin '<owner email>' --name='<name>'
  ```
  It prompts twice, hidden (`Password (at least 12 characters)`, `Confirm password`). The password is **never** an argument, option or environment variable (nothing in the shell history, process list or log), is never printed, and must be at least 12 characters; the production password rule (mixed case, digit, symbol, breach list) is not applied by this command, so pick a strong one. In one transaction it: creates the `Super Admin` role (guard `store`) if missing; creates the store user (`user_type` `admin`, `email_verified_at` set, email stored lower-case); and assigns the role. If `wms_permissions` has no `store` rows at all it also runs the permission sync (it normally has 182 after `migrate`); if some defined rows are missing it only tells you to run `php artisan store:sync-permissions` (4.1 and 4.2). It writes no role grant other than that one assignment, no permission to any role or user, and prints exactly what it created. Exit code 0 on success, 1 on anything refused (nothing written).
  - If the email already belongs to a store user it **refuses** and changes nothing. To give an existing account the role, run it with `--promote`: it only assigns the role (no prompt, password and other fields untouched) and, as a safety limit this command adds, refuses an account that is banned or whose `user_type` is not `admin`, `staff` or `accountant`, because `IsAdmin` would answer it 404 anyway.
  - Evidence: `app/Console/Commands/CreateStoreAdminCommand.php`, `tests/Feature/Console/CreateStoreAdminCommandTest.php` (new user, existing email refused, `--promote`, confirmation mismatch, short and empty password, runs with `APP_ENV=production` while `StoreUserSeeder` refuses, never more than the one assignment), `app/Http/Middleware/IsAdmin.php:22`, `app/Providers/AppServiceProvider.php:156-163`. **Verified here:** the pest file, plus `--no-interaction` and an existing-email run in `APP_ENV=production` on a scratch SQLite database. The hidden prompt itself was exercised only through Laravel's test harness, not a real terminal.
  - Verify: `SELECT u.id, u.email, u.user_type, r.name FROM users u JOIN wms_model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\StoreUser' JOIN wms_roles r ON r.id = mr.role_id;` returns exactly one row with `Super Admin`; signing in at `/temp/login` lands on `/temp/admin` (not exercised over HTTP here).
- [ ] **4.5** **[developer]** **Do not run `db:seed` in production, and know that it now refuses.** Since the hardening PR, `php artisan db:seed --class=<X> --force` throws `Refusing to run <X> in production: it writes demo or test accounts.` and writes nothing for `DatabaseSeeder`, `StoreUserSeeder` (the `store-admin@example.com` / `password` Super Admin), `CellStatusLogSeeder` and `CellVerificationRoundSeeder` (both create mobile users with the factory's known password) and `StagingRealisticDataSeeder`. `StoreRoleSeeder` still runs in production: it creates `Super Admin` and the 182 permissions (no grants) and **no longer creates `Clerk`** (only in `local` and `testing`); nothing in the application code, routes or the deploy references a `Clerk` role (`grep -rn Clerk app routes config database resources/views`), so no production behaviour depends on it. `ProductSeeder`, `RowSeeder`, `PalletSeeder` and `PageSeeder` write demo data but no accounts, are not guarded and still run if called by name: do not. Prove none of the test accounts exist:
  - Evidence: `database/seeders/Concerns/RefusesProduction.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/StoreUserSeeder.php`, `database/seeders/StoreRoleSeeder.php`, `tests/Feature/Database/SeedersRefuseProductionTest.php`, `README.md:33` (the seeded `test@example.com` / `password`). **Verified here:** the pest file; `db:seed --class=StoreUserSeeder --force` with `APP_ENV=production` on scratch SQLite throws and the Playwright seed path (`migrate:fresh --seed` with `APP_ENV=testing`) still creates `Super Admin`, `Clerk` and `store-admin@example.com`.
  - Verify: `SELECT COUNT(*) FROM users WHERE email = 'store-admin@example.com';` → 0; `SELECT COUNT(*) FROM wms_users WHERE email = 'test@example.com';` → 0; `SELECT COUNT(*) FROM wms_roles WHERE name = 'Clerk';` → 0.
- [ ] **4.6** **[developer]** Warehouse-panel and tooling admin (`/admin/login`, `/health`, `/pulse`): these gates need a `wms_users` account with the `admin` role on the `web` guard; the store Super Admin cannot open them. In the repository only `UserFactory` creates that role, so production needs the same by-hand creation (**verified here**), and only if the owner launches the warehouse panel or wants `/health`:
  ```bash
  read -rsp 'Password: ' WMS_ADMIN_PASSWORD; echo; export WMS_ADMIN_PASSWORD
  WMS_ADMIN_NAME='<name>' WMS_ADMIN_EMAIL='<email>' $PHP artisan tinker --execute='
  $role = \Spatie\Permission\Models\Role::findOrCreate("admin", "web");
  $user = new \App\Models\User;
  $user->forceFill(["name" => getenv("WMS_ADMIN_NAME"), "email" => getenv("WMS_ADMIN_EMAIL"),
      "password" => getenv("WMS_ADMIN_PASSWORD"), "email_verified_at" => now()])->save();
  $user->assignRole($role); echo "wms user ", $user->id, " isAdmin=", var_export($user->isAdmin(), true), "\n";'
  unset WMS_ADMIN_PASSWORD
  ```
  **Owner must confirm** whether the warehouse panel is part of this launch.
  - Evidence: `database/factories/UserFactory.php:44`, `app/Models/User.php:61-64`, `routes/web.php:31-36,42`, `app/Providers/HealthServiceProvider.php:25`, `README.md:33-37`.
  - Verify: `SELECT u.email, r.name FROM wms_users u JOIN wms_model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User' JOIN wms_roles r ON r.id = mr.role_id;` returns the one account with `admin`; `/admin/login` signs in and lands on the dashboard.
- [ ] **4.7** **[owner]** Confirm the launch posture: only the Super Admin account exists; no `staff` or `accountant` accounts are created before the role plan (4.9) is approved. **Owner must confirm** (production data is not visible here).
  - Evidence: guide `store-backoffice-migration.md:4160-4166`; audit `role-grants-audit-20261020.md:96-113`.
  - Verify: `SELECT user_type, COUNT(*) FROM users WHERE user_type IN ('admin','staff','accountant') GROUP BY user_type;` shows one `admin` row and nothing else.
- [ ] **4.8** **[developer]** If any legacy store data was imported, prove guard hygiene: `SELECT COUNT(*) FROM wms_model_has_roles p JOIN wms_roles r ON r.id = p.role_id WHERE (p.model_type = 'App\\Models\\User') <> (r.guard_name = 'web');` must be 0, and the audit's read-only checks Q9a to Q9e must return nothing unexpected. On a fresh database both are trivially 0.
  - Evidence: `.ai/rules/legacy-albaraka-holland.md:1611-1614`, `.ai/role-grants-audit-20261020.sql:714-756`.
  - Verify: run only SELECTs, with a read-only account if one exists.
- [ ] **4.9** **[owner]** Plan staff roles for after launch. They are created in the app (`/temp/admin/roles`, then `/temp/admin/staffs`), granting from the 182 names grouped by the 18 sections. Use the audit as the grant plan: §F (`role-grants-audit-20261020.md:967-1015`) says per permission group whether to grant by default or whether it is an owner decision; §E (`:923-965`) says who loses access when a gate moved; the `.sql` file (Q2 to Q7) shows what production already holds. Every role that signs in to the back office needs `admin_dashboard`, or its holders land on their profile page instead (4.12).
  - Evidence: audit §E/§F, guide `store-backoffice-migration.md:4252-4258`.
  - Verify: the role plan is approved in writing before the first staff account exists.
- [ ] **4.10** **[owner]** Sign off the money and impersonation gates: not granted by default to anyone but the Super Admin: `pay_to_seller`, `seller_payment_history`, `view_seller_payout_requests`, `seller_commission_configuration`, `approve_representative_payment`, `update_order_payment_status`, `edit_order_price`, `login_as_seller`, `login_as_customer`, `delete_*`, `ban_*`.
  - Evidence: audit `role-grants-audit-20261020.md:85-89,967-1015`.
  - Verify: risk register R2 and R3 below are ticked in §12.
- [ ] **4.11** **[owner]** Sign off the accepted risks that remain open (register below). None is fixed by this checklist.
  - Evidence: the register below cites each risk; the audit's security observations are `role-grants-audit-20261020.md:1017-1034`.
  - Verify: every row R1 to R9 is marked accept or change in §12 (G8).
- [ ] **4.12** **[developer]** Know the login landing rules: admin and staff accounts go to `/temp/admin` when they can `admin_dashboard`, otherwise to `profile.index` (`/temp/admin/profile`, no permission gate); an `accountant` is admitted by `IsAdmin` but is not matched by that redirect and lands on the customer dashboard.
  - Evidence: `app/Http/Controllers/Auth/LoginController.php:216-222`, `app/Http/Middleware/IsAdmin.php:22`, guide `store-backoffice-migration.md:4252-4261`.
  - Verify: covered by the low-privilege walk in §7 (T2).

### Accepted-risk register (owner decisions that remain open)

| # | Risk | Evidence | Default at launch | Decision |
| --- | --- | --- | --- | --- |
| R1 | `edit_staff_role` can grant any store permission to any non-Super-Admin role (including the editor's own); `edit_staff` can change the editor's own role. Together they are equivalent to every permission except Super Admin. | audit G-3 (`role-grants-audit-20261020.md:1023`), `app/Http/Controllers/Store/Admin/RoleController.php:135`, `app/Http/Controllers/Store/Admin/StaffController.php:107,127,135` | Held by the Super Admin only | ☐ accept ☐ change |
| R2 | Money gates (4.10) belong to whoever is granted them; none follows automatically from "the role that ran the screen". | audit §F | Super Admin only | ☐ accept ☐ change |
| R3 | Impersonation and destructive gates (`login_as_*`, `ban_*`, `delete_*`, `delete_all_uploads`). | audit §F | Super Admin only | ☐ accept ☐ change |
| R4 | Accountant accounts log in to the customer dashboard rather than the back office. | guide `store-backoffice-migration.md:4252-4261` | No accountants at launch | ☐ accept ☐ change |
| R5 | The 144 names that existed only in production data are now defined by the sync under sections this repository chose; an existing production row keeps its own section. Production's actual rows are unknown. | guide `store-backoffice-migration.md:4168-4194` | n/a on a fresh database | ☐ accept ☐ check Q2/Q3 |
| R6 | `products` has second writers besides `products:sync`. | `.ai/rules/shared-database.md:400-406` | retained | ☐ accept ☐ change |
| R7 | Low-severity gate oddities (writes behind read permissions, creation behind edit permissions, store-permission routes outside the admin group, ungated own-data reads). | audit G-4 to G-8 | retained | ☐ accept ☐ change |
| R8 | `manage_website_scripts` activation, the script policy and rich-text WAF exceptions are held; header/footer scripts and any `<script>` text are refused by the WAF. | guide `store-backoffice-migration.md:601-604,1707-1709` | held | ☐ accept ☐ change |
| R9 | `view_uploads` lets the holder see every account's files; `profile.index` is open to any admin or staff account. | audit §F, guide `store-backoffice-migration.md:4252-4258` | Super Admin only | ☐ accept ☐ change |

## 5. Runtime and scheduler

- [ ] **5.1** **[owner]** Confirm the **one** OS-level trigger exists on the host: a cron job (or Windows Task Scheduler entry) running every minute `cd $APP && $PHP artisan schedule:run`. Nothing in the repository installs it. The queue worker, backups, `products:sync`, and the health checks all hang off it (Appendix B). Also **confirm `proc_open` is available to that PHP**: the scheduler spawns each command through a shell process, and the deploy script records `proc_open` as disabled on this host. **Owner must confirm** the crontab entry and the PHP settings.
  - Evidence: `routes/console.php:9-13,20-32`, `vendor/laravel/framework/src/Illuminate/Console/Scheduling/Event.php:216`, `.github/scripts/deploy-remote.sh:278-283`; `deploy-remote.sh:353-355` still mentions a cron-driven `queue:work`, which the scheduler entry has replaced.
  - Verify: `crontab -l | grep schedule:run`; `$PHP -r 'echo function_exists("proc_open") ? "proc_open OK" : "proc_open DISABLED";'` prints `proc_open OK` (if it prints DISABLED, nothing scheduled will run: the host needs a different PHP configuration or the scheduler needs another trigger, which is a developer task); after two minutes `$PHP artisan schedule:list` shows 9 entries (checked here) and `$PHP artisan health:list` shows the schedule check passing (9.1).
- [ ] **5.2** **[automated]** The queue is drained by the scheduler: every minute `queue:work --stop-when-empty --max-time=55` runs without overlapping (mutex bounded to 2 minutes, so a crash cannot silence it for 24 hours); the database queue re-offers a reserved job after 90 seconds. The deploy ends with `queue:restart` so no worker keeps running old code. While the app is in maintenance mode the scheduler runs nothing, which pauses the queue, backups and health heartbeats.
  - Evidence: `routes/console.php:20`, `config/queue.php:43`, `deploy-remote.sh:353-357`, `vendor/laravel/framework/src/Illuminate/Console/Scheduling/ScheduleRunCommand.php:261-265`, `tests/Feature/ConsoleScheduleTest.php:10-45`.
  - Verify: `SELECT COUNT(*) FROM wms_jobs;` stays near 0 and `SELECT COUNT(*) FROM wms_failed_jobs;` is 0 (`php artisan queue:failed` prints "No failed jobs found").
- [ ] **5.3** **[developer]** Code is released atomically: a new `releases/<id>` directory is built, the `current` symlink is flipped only after migrations and caches succeed, `.env`, `storage/` and the three SQLite files are shared across releases. Note the deploy builds assets **on the host** (`npm ci`, `npm run build`), so the host needs outbound HTTPS to `fonts.bunny.net` (the Vite font plugin downloads the font at build time).
  - Evidence: `deploy-remote.sh:5-9,194-209,264-301`, `vite.config.ts:7,15-19`, `config/secure-headers.php:672-682` (CSP allows `fonts.bunny.net`).
  - Verify: in the deploy log "Building frontend assets" completes without a font download error; on the host `ls $APP/public/build/manifest.json` exists; in the browser DevTools (Rendered Fonts) the page text uses Instrument Sans. A build made with `python3 .claude/scripts/build_without_fonts.py` is for sandboxes only and must never be deployed.
- [ ] **5.4** **[developer]** Caches, in the order the deploy runs them: `config:cache`, `route:cache`, `view:cache` (before the symlink flip, after `migrate`). `event:cache` is now part of that sequence, between `config:cache` and `route:cache` (F4, fixed in the hardening PR), so a release deployed by the updated script needs no manual step. Only a release built by an older deploy script, or a manual install, still needs `php artisan event:cache` by hand, or the health check fails. Do not cache in a shell where `.env` differs from production.
  - Evidence: `.github/scripts/deploy-remote.sh` ("Refreshing application caches"), `.github/scripts/deploy-remote-test.sh` ("a deploy caches config, events, routes and views…", "a failed event:cache rolls the deploy back…"), `app/Providers/HealthServiceProvider.php:38`, `vendor/spatie/laravel-health/src/Checks/Checks/OptimizedAppCheck.php:21-37`. **Verified here:** `bash .github/scripts/deploy-remote-test.sh` against stubbed binaries, and that deleting the `event:cache` line makes it fail; `artisan event:cache` and `event:clear` run cleanly on this tree. **Not run:** the real host, or `OptimizedAppCheck` against a real cached release.
  - Verify: `$PHP artisan about --only=cache` shows Config, Events, Routes and Views `CACHED` (the output format was checked here, uncached); `ls $APP/bootstrap/cache` lists `config.php`, `events.php`, `routes-v7.php`.
- [ ] **5.5** **[developer]** `storage:link`: `artisan storage:link` cannot run on this host (symlink() and exec() are disabled), so the deploy creates `public/storage` itself. On any other host run `php artisan storage:link`.
  - Evidence: `deploy-remote.sh:332-338`, `config/filesystems.php:78-80`.
  - Verify: `ls -l $APP/public/storage` points to `$DEPLOY_PATH/shared/storage/app/public`.
- [ ] **5.6** **[owner]** Make uploads survive releases. Either create the persistent directory `$DEPLOY_PATH/shared/public/uploads` **before the first deploy** (the deploy re-links every entry of `shared/public/` into each new release), or set `FILESYSTEM_DISK=s3`. The legacy audit states "With atomic releases `public/uploads` is the shared directory", but nothing in the repository creates it. **Owner must confirm** the host layout and, for s3, the bucket.
  - Evidence: `app/Services/Concerns/WritesUploads.php:30-37`, `deploy-remote.sh:211-228`, `.ai/rules/legacy-albaraka-holland.md:365-367`.
  - Verify: upload a file in `/temp/admin/uploads`, deploy again (or read the next release), then `ls -l $APP/public/uploads` is a symlink into `shared/public/uploads` and the file's URL still returns 200. Also confirm PHP execution is denied in `public/uploads` at the web server (no evidence in the repo).
- [ ] **5.7** **[developer]** File ownership: the SSH user, the cron user and the web user must all be able to write `$DEPLOY_PATH/shared/storage` and each release's `bootstrap/cache`; the deploy creates the `storage` subtree (`app/public`, `app/private`, `framework/cache/data`, `sessions`, `views`, `logs`).
  - Evidence: `deploy-remote.sh:191-199`.
  - Verify: `ls -ld $DEPLOY_PATH/shared/storage $APP/bootstrap/cache` and `sudo -u <web user> test -w …` (or the host's equivalent) succeed; after the first request `storage/logs` has a fresh file.
- [ ] **5.8** **[developer]** PHP 8.5 and its extensions on the CLI and web PHP: `composer.lock` requires `ext-gd`; the app also needs `pdo_mysql`, `mbstring`, `zip`, `curl`, `openssl`, `intl`. The SQLite files for Telescope, Pulse and Health need `pdo_sqlite` even when the main database is MySQL. Check upload limits too (6.10).
  - Evidence: `composer.json:12`, `.ai/agent-conventions.md:79-91`, `.ai/rules/config.md` ("All sqlite connections run WAL…").
  - Verify: `$PHP -v`; `composer check-platform-reqs --no-dev` (run with `$PHP composer`) reports every requirement `success`; `$PHP -m | grep -E 'gd|pdo_mysql|pdo_sqlite|mbstring|zip'`.
- [ ] **5.9** **[developer]** After the first deploy, run the health check once and read the result: expect `OptimizedAppCheck` to pass already (the updated deploy script runs `event:cache`, 5.4; on a release from an older script it fails until you run it by hand), `BackupsCheck` to fail until the first backup (01:30 UTC next day or `backup:run --only-db`), and `QueueCheck`/`ScheduleCheck` to fail until the heartbeats ran (a few minutes).
  - Evidence: `app/Providers/HealthServiceProvider.php:27-39`, `routes/console.php:29-31`.
  - Verify: `$PHP artisan health:check && $PHP artisan health:list` lists every check `ok` after the waits above.
- [ ] **5.10** **[developer]** Run `php artisan store:move-trade-licenses --dry-run` once if (and only if) legacy trade licences exist in `public/uploads/licenses/`; then without `--dry-run`. On a fresh install there is nothing to move.
  - Evidence: `.ai/rules/legacy-albaraka-holland.md:359-368,1743-1745`, `app/Console/Commands/MoveLegacyTradeLicensesCommand.php:26`.
  - Verify: `ls $DEPLOY_PATH/shared/public/uploads/licenses 2>/dev/null | wc -l` prints 0 afterwards.

## 6. Security

- [ ] **6.1** **[owner]** TLS and HSTS: the certificate is installed and renewing, and HTTP redirects to HTTPS. HSTS is sent for one year (`max-age=31536000`, no `includeSubDomains`, no preload), so a broken certificate later locks browsers out. **Owner must confirm** certificate and redirect at the host/CDN.
  - Evidence: `config/secure-headers.php:147-155`, `.env.example:57-59`, `config/secure-headers.php:644` (`upgrade-insecure-requests`).
  - Verify: `curl -sI https://<HOST>/ | grep -i strict-transport-security` prints `max-age=31536000`; `curl -sI http://<HOST>/ | head -1` is a 301/302 to https.
- [ ] **6.2** **[automated]** Secure headers on every response, including WAF-blocked ones: `X-Content-Type-Options: nosniff`, `X-Frame-Options: sameorigin`, `Referrer-Policy: no-referrer`, a `Permissions-Policy`, CSP and HSTS; `Server`, `X-Powered-By` and `X-XSS-Protection` withheld.
  - Evidence: `config/secure-headers.php:21,49,90,147,212,617`, `bootstrap/app.php:47-51`, `tests/Feature/SecureHeadersTest.php:3-75`.
  - Verify: `curl -sI https://<HOST>/admin/login` shows those headers and none of `Server: <version>`/`X-Powered-By`.
- [ ] **6.3** **[developer]** **CSP is enforced in production** (report-only elsewhere): walk every migrated back-office area on staging with the production environment and `CSP_REPORT_ONLY` unset (2.6), browser console open and filtered on "Content Security Policy" / "Refused to". There is **no report endpoint** (F6), so the console is the only signal; anything you see must be fixed by a developer in `config/secure-headers.php` or the page before go-live. Procedure:
  1. Confirm enforcement: `curl -sI https://<HOST>/admin/login | grep -i content-security-policy` (no `-Report-Only`).
  2. Sign in as Super Admin at `/temp/login`; open each page in the table below with DevTools open (Console and Network, "Disable cache" off); reload once with a hard refresh.
  3. For each page record "clean" or the exact console message and blocked URL.
  4. Repeat the heavy pages in Arabic (RTL) once.
  - Evidence: `config/secure-headers.php:617-875` (policy), `.ai/rules/store-backoffice-migration.md:243-290` (§2.5), `tests/e2e/store-*.spec.ts` run under enforced CSP (ledger `store-backoffice-reconciliation-20261011.md:1024-1025`: "local HTTPS and enforced CSP").
  - Verify: a filled-in table (below) with no unexplained violation.

  | CSP-sensitive feature | Page (route name, URL) | What must work with enforcement on |
  | --- | --- | --- |
  | Leaflet map + OpenStreetMap tiles | `trips.create-from-map` `/temp/admin/trips/create-from-map`; `admin.rep.visits.show` `/temp/admin/representatives/visits/show/{visit}`; `admin.rep.visits.rep-show` `/temp/admin/representatives/visits/rep-show/{user}`; `google-map.index` `/temp/admin/google-map` | map renders, tiles load from `https://tile.openstreetmap.org` (the only host added to `img-src`), marker images and Leaflet CSS load (bundled by Vite), no `unpkg.com` request |
  | Chart.js | `shop.admin.dashboard` `/temp/admin` | five canvases draw (canvas needs no CSP change); no inline-script error |
  | Tiptap rich-text editor | `blog.create`/`blog.edit` (`/temp/admin/blog/create`), `newsletters.index` (`/temp/admin/newsletter`), `support_ticket.admin_show` (ticket reply), `website.footer`, `website.appearance`, `custom-pages.create`/`custom-pages.edit` (`/temp/admin/website/...`), `digitalproducts.create`/`.edit`, `products.create`, `products.admin.edit` | toolbar works, bold/list/table/image insert, save round-trips; no `eval`/inline-script violation |
  | Uploads and file picker | `uploaded-files.index` `/temp/admin/uploads`, `uploaded-files.create`, the uploader dialog inside the product/blog/website forms, `trucks.photos` | upload succeeds, thumbnails render (`img-src 'self' data: blob:`), list pagination |
  | Product thumbnails from another origin | `products.all` `/temp/admin/products/all`, WMS `/admin/products` | thumbnails load. If `STORE_ASSET_BASE_URL`, an upload `external_link` or an S3/CDN host is not this origin, they are **blocked** by `img-src` (F6) |
  | Web font | any page, `fonts.bunny.net` in `font-src`/`style-src` | no font violation; text renders in the intended face |
  | Vue i18n | any page after switching language | no `unsafe-eval` violation |

- [ ] **6.4** **[developer]** If 6.3 shows blocked product images or any other violation, stop: the fix is a developer change to the policy (for example adding the asset host to `img-src.allow`) or to the page, followed by the CI gate again. Do not set `CSP_REPORT_ONLY=true` in production as a workaround without the owner accepting an unenforced policy in §12.
  - Evidence: `config/secure-headers.php:700-713`, `.ai/rules/store-backoffice-migration.md:280-289`.
  - Verify: re-walk the affected rows of the 6.3 table.
- [ ] **6.5** **[developer]** Blade pages that were **not** migrated receive a looser per-request policy (`store.blade_csp`: scripts and styles `'self' 'unsafe-inline' https:`) from `HandleStoreBladePages`, only on Blade responses: storefront, seller panel, store auth pages, seller login, delivery-boy login, the 503 page. `form-action` is not in that list, so it stays `'self'` (relevant to payment gateways, 8.6).
  - Evidence: `config/store.php:115-142`, `.ai/rules/store-backoffice-migration.md:246-262`, `app/Providers/RouteServiceProvider.php:62-68,79-80`.
  - Verify: `curl -sI https://<HOST>/temp/seller/login | grep -i content-security-policy` shows `'unsafe-inline'`; `curl -sI https://<HOST>/admin/login | grep -i content-security-policy` does not.
- [ ] **6.6** **[developer]** Session and login hardening: warehouse login is honeypot-protected and throttled (5 attempts per minute per email+IP, 20 per minute per email); the store login, codes and sign-up have their own limiters (`store-auth`, `store-signup`); the API is limited per token or IP.
  - Evidence: `routes/web.php:33`, `app/Providers/AppServiceProvider.php:56-121`, `.ai/rules/auth.md`.
  - Verify: against staging, six wrong passwords for a non-existent email at `/admin/login` produce an HTTP 429 on the sixth (use a fake address, never a real account).
- [ ] **6.7** **[automated]** Production error handling: `APP_DEBUG=false` (health fails `DebugModeCheck` otherwise), no custom `errors/` views exist so Laravel's generic pages render, exceptions are not flashed with passwords.
  - Evidence: `app/Providers/HealthServiceProvider.php:37`, `ls resources/views` (no `errors/`), `bootstrap/app.php:124`.
  - Verify: `curl -s https://<HOST>/temp/admin/does-not-exist` shows a generic 404 with no stack trace, file path or environment dump.
- [ ] **6.8** **[developer]** Store back-office access shape: non-admin store accounts get **404** on `/temp/admin/*` (`IsAdmin`), staff without a permission get **403**, and the four `RaizerSuperAdmin` routes (`activation.index`, `business_settings.update.activation`, `business_settings.seller_commission.activation`, `cache.clear`) additionally need the vendor flag `raizer_admin`, so the owner's first Super Admin gets 403 there unless the flag is set deliberately.
  - Evidence: `app/Http/Middleware/IsAdmin.php:22-27`, `app/Http/Middleware/RaizerSuperAdmin.php:27-30`, audit JSON `raizer_super_admin_routes`.
  - Verify: covered by §7 (S27, T1 to T4).
- [ ] **6.9** **[developer]** WAF: `BlockMaliciousRequests` stays on (SQLi, XSS, traversal and command-injection signatures over URI, `User-Agent`, `Referer` and input; credentials are skipped; fail-closed). Exclusions are only `telescope`, `up` and `.well-known/*` (plus `_boost` locally). The docs hold back every wider exception: saving header/footer scripts, any `<script>` or `onerror=` text, or text such as `curl http…` returns a bare 403 by design. Blocks are logged as `Blocked malicious request`.
  - Evidence: `config/waf.php:16-35,74-119`, `app/Http/Middleware/BlockMaliciousRequests.php:177`, `.ai/rules/middleware.md` ("Application-level WAF"), guide `store-backoffice-migration.md:601-604,1707-1709,1933-1936`.
  - Verify: `curl -s -o /dev/null -w '%{http_code}' 'https://<HOST>/temp/login?x=union%20select%201'` is 403; `grep -c 'Blocked malicious request' $DEPLOY_PATH/shared/storage/logs/laravel-*.log` counts blocks (watch list, §11).
- [ ] **6.10** **[owner]** Upload limits: the application accepts files up to 512 MB (`MultipartUploadService::MAX_BYTES`, images also capped at 40 million pixels), but PHP's `upload_max_filesize`/`post_max_size`, the web server and any proxy decide the real ceiling. **Owner must confirm** the limits you want and that PHP/web server/proxy agree.
  - Evidence: `app/Services/MultipartUploadService.php:56-59,73`.
  - Verify: `$PHP -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size"), "\n";'` (web PHP may differ: check via the host's PHP selector) and upload a file just under the chosen limit in §7.
- [ ] **6.11** **[automated]** Dependency audits are part of the full CI run: `composer audit --locked --abandoned=report` and `npm audit --omit=dev --audit-level=high` must be clean. The full `npm audit` still lists six pre-existing dev-tooling findings (four high: `braces`, `micromatch`, `fast-glob`, `postcss-selector-parser` and the eslint packages that pull them) which the production audit excludes; accept or schedule an upgrade.
  - Evidence: `.github/workflows/tests.yml:401-405`, ledger `store-backoffice-reconciliation-20261011.md:1921-1925,3638-3639,3967-3969`, `.ai/rules/store-backoffice-migration.md:3494`.
  - Verify: the `Dependency audit` job is green (1.3); the dev-tool findings are recorded in §12 (G9).

## 7. Smoke test

URLs after the first one in a cell are relative to `/temp/admin`. Run it twice: first as the **Super Admin** (every row should open, no console error, no CSP violation), then as a throwaway **low-privilege staff role**. Mutating actions (marked ▲) are done on staging only; on production open pages and read.

**Throwaway low-privilege role (build it in the app, then delete it).**

1. As Super Admin create role `ZZ-smoke-nogrants` with **no** permissions (`roles.create`, `/temp/admin/roles/create`, needs `add_staff_role`) and a second role `ZZ-smoke-dashboard` with only `admin_dashboard` and `view_all_orders`.
2. Create two staff users (`staffs.create`, `/temp/admin/staffs/create`, needs `add_staff`), one per role, with throwaway `@example.invalid` addresses.
3. Run the **T** rows below as each, in private windows.
4. Delete both staff users and both roles (`delete_staff`, `delete_staff_role`) and confirm none remain: `SELECT COUNT(*) FROM wms_roles WHERE name LIKE 'ZZ-smoke-%';` → 0 and `SELECT COUNT(*) FROM users WHERE email LIKE '%@example.invalid';` → 0.

| # | Area | Route name | URL | Permission (audit) | Check as Super Admin | Result |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | Sign-in | `shop.login` | `/temp/login` | none | form posts, lands on `/temp/admin` | ☐ |
| S2 | Dashboard | `shop.admin.dashboard` | `/temp/admin` | `admin_dashboard` | five charts, stats, top products; console clean | ☐ |
| S3 | Orders | `all_orders.index` | `/temp/admin/all_orders` | any of `view_all_orders`, `view_inhouse_orders`, `view_pickup_point_orders`, `view_seller_orders` | list, filter, paginate | ☐ |
| S4 | Order detail | `orders.show` | `/temp/admin/orders/{order}` | `view_order_details` | opens; status controls need `update_order_*` | ☐ |
| S5 | Order entry | `feat-order.create` | `/temp/admin/feat/order/create` | `add_order` | form + product search (`orders.search-options`: `add_order` or `edit_order`); submit ▲ | ☐ |
| S6 | Customers | `customers.index`, `customers.create` | `/temp/admin/customers`, `/temp/admin/customers/create` | `view_all_customers`, `edit_customer` | list, create form, address cities/states | ☐ |
| S7 | Product lists | `products.all`, `products.admin`, `products.seller` | `/temp/admin/products/all`, `/admin`, `/seller` | `show_all_products`, `show_in_house_products`, `show_seller_products` | lists; thumbnails (6.3); "Fetch" button needs `add_new_product` (8.3) | ☐ |
| S8 | Product create | `products.create` | `/temp/admin/products/create` | `add_new_product` | editor, uploader, variant/SKU JSON; save ▲ | ☐ |
| S9 | Product edit | `products.admin.edit` | `/temp/admin/products/admin/{product}/edit` | `product_edit` | translation tabs, stored variants; save ▲ | ☐ |
| S10 | Digital products | `digitalproducts.index`, `digitalproducts.create` | `/temp/admin/digitalproducts` | `show_digital_products`, `add_digital_product` | list, form, download | ☐ |
| S11 | Categories, brands, attributes | `categories.index`, `brands.index`, `attributes.index`, `colors` | `/temp/admin/categories`, `/brands`, `/attributes`, `/colors` | `view_product_categories`, `view_all_brands`, `view_product_attributes`, `view_colors` | lists; create/edit dialogs | ☐ |
| S12 | Trips | `trips.index`, `trips.create`, `trips.show` | `/temp/admin/trips/index`, `/trips/create`, `/trips/show/{trip}` | `view_all_trips`, `add_trip` | list, form, detail, checkpoint notes ▲ | ☐ |
| S13 | Trip map | `trips.create-from-map` | `/temp/admin/trips/create-from-map` | `add_trip` | Leaflet map, tiles, select orders (6.3) | ☐ |
| S14 | Drivers, trucks | `drivers.index`, `trucks.index`, `trucks.photos` | `/temp/admin/trips/drivers/index`, `/trips/trucks/index`, `/trips/trucks/photos` | `view_all_drivers`, `view_all_trucks`, `add_truck`/`edit_truck` | lists, forms, photo upload | ☐ |
| S15 | Representatives | `representatives.index`, `rep.packages.index`, `admin.rep.payments.index` | `/temp/admin/representatives`, `/representatives/packages/index`, `/admin/representatives/payments/index` | `view_all_customers`, `view_representative_packages`, `view_representative_payments` | lists | ☐ |
| S16 | Visit maps | `admin.rep.visits.index`, `admin.rep.visits.show`, `admin.rep.visits.rep-show` | `/temp/admin/representatives/visits/index`, `/show/{visit}`, `/rep-show/{user}` | `view_representative_visits` | Leaflet maps, tiles, day view | ☐ |
| S17 | Sellers | `sellers.index`, `sellers.create` | `/temp/admin/sellers`, `/sellers/create` | `view_all_seller`, `edit_seller` | list, profile dialog (`view_seller_profile`) | ☐ |
| S18 | Seller payouts | `sellers.payment_histories`, `withdraw_requests.index`, `commissions.pay_to_seller` (POST) | `/temp/admin/seller/payments`, `/withdraw_requests`, `/pay_to_seller` | `seller_payment_history`, `view_seller_payout_requests`, `pay_to_seller` | histories paginate; pay dialog opens; do **not** record a payout in production | ☐ |
| S19 | Seller settings | `business_settings.vendor_commission`, `seller_verification_form.index` | `/temp/admin/vendor_commission`, `/verification/form` | `seller_commission_configuration`, `seller_verification_form_configuration` | forms load | ☐ |
| S20 | Marketing | `coupon.index`, `flash_deals.index`, `offer_deals.index`, `dynamic-popups.index`, `newsletters.index`, `admin.subscribers.index`, `admin.notifications.all` | `/temp/admin/coupon`, `/flash_deals`, `/offer_deals/index`, `/dynamic-popups`, `/newsletter`, `/subscribers`, `/notifications` | `view_all_coupons`, `view_all_flash_deals`, `view_all_offer_deals`, `view_all_dynamic_popups`, `send_newsletter`, `view_all_subscribers`, `add_notification` | lists load | ☐ |
| S21 | Website | `website.header`, `website.footer`, `website.appearance`, `website.pages`, `custom-pages.create` | `/temp/admin/website/header`, `/footer`, `/appearance`, `/pages`, `/custom-pages/create` | `header_setup`, `footer_setup`, `website_appearance`, `view_all_website_pages`, `add_website_page` | editors; save text ▲; scripts with `<script>` are refused by the WAF (6.9) | ☐ |
| S22 | Blog | `blog.index`, `blog.create`, `blog-category.index` | `/temp/admin/blog`, `/blog/create`, `/blog-category` | `view_blogs`, `add_blog`, `view_blog_categories` | editor, save ▲ | ☐ |
| S23 | Support | `support_ticket.admin_index`, `support_ticket.admin_show`, `conversations.admin_index`, `product_query.index` | `/temp/admin/support_ticket`, `/support_ticket/{id}/show`, `/conversations`, `/product-queries` | `view_all_support_tickets`, `reply_to_support_tickets`, `view_all_product_conversations`, `view_all_product_queries` | list, detail, reply editor | ☐ |
| S24 | Settings | `general_setting.index`, `google-map.index`, `smtp_settings.index`, `test.smtp`, `currency.index`, `languages.index`, `order_configuration.index`, `payment_method.index`, `carriers.index`, `tax.index` | `/temp/admin/general-setting`, `/google-map`, `/smtp-settings`, `/smtp-settings/test`, `/currency`, `/languages`, `/order-configuration`, `/payment-method`, `/carriers`, `/tax` | `general_settings`, `google_map_setting`, `smtp_settings` (+ `send_newsletter` for the test), `currency_setup`, `language_setup`, `order_configuration`, `payment_methods_configurations`, `manage_carriers`, `vat_&_tax_setup` | pages load; Google Map picker is Leaflet (6.3); SMTP test mail (2.5) | ☐ |
| S25 | Reports | `in_house_sale_report.index`, `seller_sale_report.index`, `stock_report.index`, `wish_report.index`, `user_search_report.index`, `commission-log.index`, `wallet-history.index` | `/temp/admin/in_house_sale_report`, `/seller_sale_report`, `/stock_report`, `/wish_report`, `/user_search_report`, `/commission-log`, `/wallet-history` | `in_house_product_sale_report`, `seller_products_sale_report`, `products_stock_report`, `product_wishlist_report`, `user_search_report`, `commission_history_report`, `wallet_transaction_report` | filters, pagination, export | ☐ |
| S26 | Uploads | `uploaded-files.index`, `uploaded-files.create` | `/temp/admin/uploads`, `/uploads/create` | `view_uploads`, `upload_files` | upload an image and a document, see it, delete it ▲ | ☐ |
| S27 | Staff and roles | `staffs.index`, `roles.index` | `/temp/admin/staffs`, `/roles` | `view_all_staffs`, `view_staff_roles` | lists; role form offers all 182 names grouped by section | ☐ |
| S28 | Profile | `profile.index` | `/temp/admin/profile` | none (any admin or staff) | own profile opens, update ▲ | ☐ |
| S29 | Language and RTL | `language.change` (POST) | `/temp/language`; warehouse `POST /locale/{locale}` | none | switch to Arabic: `dir=rtl`, maps and charts keep LTR geometry; switch back | ☐ |
| S30 | Vendor-only | `activation.index`, `cache.clear` | `/temp/admin/activation` | `RaizerSuperAdmin` (`raizer_admin` flag) | expect 403 for a Super Admin without the flag | ☐ |
| S31 | Retired page | none | `/temp/admin/system/server-status` | none | 404 even for a Super Admin | ☐ |
| S32 | Warehouse panel | `login`, `admin.dashboard`, `admin.rows.index`, `admin.products.index` | `/admin/login`, `/admin`, `/admin/rows`, `/admin/products` | warehouse `admin` role | needs 4.6; dashboard, rows, products (set "Boxes / pallet", 8.1) | ☐ |
| S33 | Operator tools | `health`, `pulse`; `/up` is unnamed | `/health`, `/pulse`, `/up` | warehouse admin + allowlisted IP; `up` public | `/health` lists checks; `/pulse` per 2.12; `/up` 200; `/telescope` 404 | ☐ |

**Low-privilege walk (T rows).**

| # | Do | Expect | Evidence | Result |
| --- | --- | --- | --- | --- |
| T1 | Sign in as the `ZZ-smoke-nogrants` user at `/temp/login` | redirected to `/temp/admin/profile` (`profile.index`), not a 403 dashboard; sidebar shows no entries | `app/Http/Controllers/Auth/LoginController.php:216-222`, guide `store-backoffice-migration.md:4252-4258` | ☐ |
| T2 | Open `/temp/admin`, `/temp/admin/all_orders`, `/temp/admin/products/all`, `/temp/admin/roles` directly | each answers **403** | `app/Http/Controllers/Store/Admin/DashboardController.php:28`, audit gates in S2 to S27 | ☐ |
| T3 | POST a gated write without the permission (for example `products.fetch`) from the browser console | **403** and no `cron_jobs` row | guide `store-backoffice-migration.md:4241-4251` | ☐ |
| T4 | Sign in as the `ZZ-smoke-dashboard` user | lands on `/temp/admin`; orders list opens; order detail still 403 (`view_order_details` not granted) | audit E-table | ☐ |
| T5 | A customer account opening `/temp/admin` | **404** | `app/Http/Middleware/IsAdmin.php:22-27` | ☐ |
| T6 | Delete both throwaway roles and users (procedure above) | zero leftovers | the two `SELECT COUNT(*)` checks above | ☐ |

**Regression checks for the pages that were NOT migrated (storefront, seller panel, auth, PDFs, mobile API).**

| # | Area | Route name | URL | Check | Result |
| --- | --- | --- | --- | --- | --- |
| R1 | Storefront home | `home` | `/temp` | renders, scripts run under the Blade policy, no console error | ☐ |
| R2 | Customer sign-in and sign-up | `user.login`, `user.registration`, `password.request` | `/temp/users/login`, `/temp/users/registration`, `/temp/password/reset` | forms render; a wrong login is throttled | ☐ |
| R3 | Seller panel | `seller.login`, `seller.dashboard`, `seller.products`, `seller.orders.index`, `seller.payments.index` | `/temp/seller/login`, `/temp/seller/dashboard`, … | login, dashboard (known SQLite-only DATE_FORMAT error, 11.2), product create with SKU fragment (`product/products/sku_combinations`) | ☐ |
| R4 | Delivery-boy login and 503 | `deliveryboy.login` | `/temp/deliveryboy/login` | layout renders (one of the seven retained Blade files) | ☐ |
| R5 | Cart and checkout | `cart`, `checkout.shipping_info` | `/temp/cart`, `/temp/checkout` | cash on delivery / manual payment only | ☐ |
| R6 | PDFs and downloads | `invoice.download`, `pdf.download_brand`, `pdf.download_category` | `/temp/invoice/{order_id}`, `/temp/admin/bulk-upload/download/brand` | invoice PDF renders with fonts; bulk-upload reference downloads | ☐ |
| R7 | Mobile APIs | `api.v1.auth.login`; the store login route is unnamed | `POST /api/v1/login`, `POST /temp/api/v2/auth/login` | token issued; an authenticated GET works; see 8.8 | ☐ |

## 8. Data and integrations

- [ ] **8.1** **[developer]** First `products:sync`: it polls the ERP feed in `STORE_PRODUCTS_SYNC_URL` and upserts only `name`, `ar_name` and `published` by `id` (`Mat_ID` is assumed to equal `products.id`; nothing is pruned; the feed always wins). Run it once by hand before the scheduler does, then set "Boxes / pallet" per product in the warehouse panel (default 50 until set).
  - Evidence: `app/Console/Commands/SyncProductsCommand.php:21-80`, `.ai/rules/shared-database.md:382-425`, `README.md:109-117`, `routes/console.php:25-27`.
  - Verify: `$PHP artisan products:sync` prints `N product(s) synced.`; `SELECT COUNT(*) FROM products;` matches the feed. With the URL unset it fails cleanly with an error, not silently.
- [ ] **8.2** **[owner]** Confirm the product feed and product-image source: `STORE_ASSET_BASE_URL` is the public base of whichever app serves the files; `uploads` is store-owned and nothing in this repo refreshes it, so a stale table yields `null` image URLs.
  - Evidence: `config/store.php:34`, `.ai/rules/shared-database.md:51-58,343-370`, `README.md:103-107`.
  - Verify: a product with an upload shows its image in `products.all`; if the host differs from the app's, see the CSP note in 6.3.
- [ ] **8.3** **[owner]** Decide the catalogue **Fetch** button. `POST /temp/admin/products/fetch` (permission `add_new_product` since step 40) only inserts a `cron_jobs` row; the import `import:all-products-v2` runs only when something executes `check:commands` (explicitly not scheduled, because it writes catalogue data and needs business sign-off). Either leave the button inert, or have the owner approve running `check:commands` every minute from a second cron entry (and accept the second writer to `products`, 3.9).
  - Evidence: `routes/console.php:34-47`, `app/Http/Controllers/ProductController.php:47-94`, `app/Console/Commands/CheckForCommands.php:24-72`, `routes/shop-admin.php:142`, guide `store-backoffice-migration.md:4241-4251`.
  - Verify: click Fetch on staging, then `SELECT command, status, created_at FROM cron_jobs ORDER BY id DESC LIMIT 3;` shows a `pending` row `fetching-products-by-admin` that stays pending until `check:commands` runs.
- [ ] **8.4** **[developer]** Other cron mentions to know: the Hostinger-specific command names (`fetching-products-by-hostinger` / `-by-admin`) in `cron_jobs`, and the stale "Windows VPS" comment in `routes/console.php:9-13` (the deploy target is now Hostinger). Every data-changing artisan command stays manual: `carts:purge-old`, `check:unordered-carts`, `check:customers`, `import:members`, `import:agents`, `import:brands`, `import:categories`, `import:collections`, `import:offer-new-products`, `import:all-products`, `sync:users`, `fix:members`, `check:commands`. A test fails if one is scheduled.
  - Evidence: `routes/console.php:34-47`, `tests/Feature/ConsoleScheduleTest.php:79`, `app/Console/Commands/CheckForCommands.php:24-60`.
  - Verify: `$PHP artisan schedule:list` lists none of those.
- [ ] **8.5** **[owner]** Email: pick the provider, verify the sending domain and send the test mail (2.5). Mail fed by notifications goes through the queue, so it needs the scheduler (5.1).
  - Evidence: `config/mail.php:17`, `routes/shop-admin.php:389`, audit `role-grants-audit-20261020.md` §F (SMTP row). **Owner must confirm** provider and DNS records.
  - Verify: the 2.5 test mail arrives; a password-reset mail for a throwaway customer arrives within two minutes.
- [ ] **8.6** **[owner]** Payment gateways: none are enabled at launch (`STORE_PAYMENT_GATEWAYS` empty). Enabling one is a project, not a switch: most gateways still read credentials with `env()`, which is null once config is cached; their callbacks need CSRF exemptions that do not exist in `bootstrap/app.php`; `form-action 'self'` blocks off-site gateway form posts; `config/nagad.php:5-6` ships merchant defaults that must be replaced. The removed SDK gateways (Stripe, PayPal, Razorpay, Paystack, Flutterwave, Paytm, …) are gone.
  - Evidence: `config/store.php:60-98`, `.ai/rules/legacy-albaraka-holland.md:631-663,1755-1763`, `config/nagad.php:5-6`, `bootstrap/app.php` (no `validateCsrfTokens`).
  - Verify: owner confirms "cash on delivery / manual payment only" in §12; `curl` of a gateway callback path is 404 (2.9).
- [ ] **8.7** **[developer]** Third-party keys by name (Appendix A): set what the owner uses, nothing else; Firebase push (`FCM_*`/`FIREBASE_*`), Google/Facebook/Twitter/Apple social login, `MAP_API_KEY`, `CAPTCHA_KEY`, `TRACKING_ID`, `OPENCAGE_API_KEY`, `IP_GEO_LOCATION_KEY`. The Google Map back-office picker is Leaflet/OpenStreetMap and needs no key; the storefront's Google map still follows the `google_map` setting.
  - Evidence: `config/services.php:60-127`, guide `store-backoffice-migration.md:3367-3383`.
  - Verify: unused features are disabled in their settings pages (S24).
- [ ] **8.8** **[owner]** Mobile API consumers: two apps. (1) The warehouse app uses `/api/v1/*` (Sanctum, 24 routes in `routes/api.php`). (2) The store apps use `/temp/api/v2/*` (`routes/shop-api.php`, `shop-api_seller.php`, `shop-api_trip.php`, `shop-api_warehouse.php` for the ERP with `WMS_TOKEN`). **Owner must confirm** both apps point at the `/temp` prefix and that no minimum app version is set unless the store app sends `X-App-Version` (check with `php artisan app:set-minimum-app-version --help` and `SELECT * FROM mobile_app_version_requirements;`). The contract tests that cover them are listed in Appendix D.
  - Evidence: `routes/api.php`, `app/Providers/RouteServiceProvider.php:53-57,72-78`, `bootstrap/app.php:76`, `tests/Feature/Api/V1/*Test.php`, `tests/Feature/LegacyStoreApiTest.php`, `tests/Feature/LegacyDeliveryBoyApiTest.php`, `tests/Feature/WarehouseApiTest.php`, `.ai/rules/legacy-albaraka-holland.md:1482-1486,1734-1742` (mobile GET routes that change data; mobile coordination).
  - Verify: R7 in §7; the `Pest` job (1.3) runs those tests.
- [ ] **8.9** **[developer]** Nothing seeded with test data reaches production: `DatabaseSeeder`, `StoreUserSeeder`, `CellStatusLogSeeder`, `CellVerificationRoundSeeder` and `StagingRealisticDataSeeder` (run-by-hand, staging) refuse production since the hardening PR (4.5), and `StoreRoleSeeder` creates only `Super Admin` and the permissions there. `ProductSeeder`, `RowSeeder`, `PalletSeeder`, `CellVerificationReportSeeder` and `PageSeeder` write demo data but no accounts, are **not** guarded, and must never be called by name in production. The policy pages `PageSeeder` creates locally (terms, privacy, return, seller, support) must be created in production through Website → Pages.
  - Evidence: `database/seeders/DatabaseSeeder.php:32-42`, `database/seeders/PageSeeder.php:13-19`, `database/seeders/StagingRealisticDataSeeder.php`, `README.md:26-33`.
  - Verify: the 4.5 queries; plus `SELECT COUNT(*) FROM pallets;` and `SELECT COUNT(*) FROM rows;` are 0 before real warehouse data is entered, and `/temp/{terms-conditions,privacy-policy}` (custom pages) render once created.

## 9. Observability and backups

- [ ] **9.1** **[automated]** Health checks run every minute and are stored in `database/health.sqlite`: database, cache, used disk space, schedule, queue, backups younger than a day on the `local` disk, `APP_DEBUG` off and optimized app (events included, F4) in production. Results are viewable at `/health` (warehouse admin + allowlisted IP) and mailed (throttled to one per hour) to `HEALTH_TO_ADDRESS`.
  - Evidence: `app/Providers/HealthServiceProvider.php:27-39`, `config/health.php:14-19,43,62,79`, `routes/web.php:27-29`, `routes/console.php:29-32`.
  - Verify: `$PHP artisan health:list` shows every check `ok`; the `/health` page opens for the 4.6 account from an allowlisted IP.
- [ ] **9.2** **[developer]** Backups: `backup:run --only-db` at 01:30 and `backup:clean` at 01:00 daily; the archive is encrypted with `BACKUP_ARCHIVE_PASSWORD` and written to `local` (`storage/app/private/<APP_NAME>/…`) and `BACKUP_OFFSITE_DISK`; retention is 7 days all, 16 daily, 8 weekly, 4 monthly, 2 yearly, max 5,000 MB. Only the main database is dumped (needs `mysqldump` on the host); the SQLite health/Pulse/Telescope files and the uploaded files are **not** in this backup, so uploaded files need their own backup (**Owner must confirm**, no evidence in the repo).
  - Evidence: `routes/console.php:22-23`, `config/backup.php:92-94,168,185,195,342-375`, `config/filesystems.php:37`, `app/Providers/AppServiceProvider.php:48-54`.
  - Verify: `$PHP artisan backup:run --only-db` then `$PHP artisan backup:list` shows the new archive on both disks; the off-host copy exists.
- [ ] **9.3** **[developer]** **Restore rehearsal** (do it once before go-live, on a scratch schema): fetch the newest archive from the off-host disk, `unzip -P "$BACKUP_ARCHIVE_PASSWORD" <archive> -d restore-check` (use a password prompt, not a literal), find the `.sql` under `db-dumps/`, `mysql -e 'CREATE DATABASE restore_check'`, `mysql restore_check < <dump>`, then point `DB_DATABASE` at it in a throwaway shell and run `$PHP artisan migrate:status | grep -c Ran` (expect 156) and the 3.8 permission query (expect `182, N, M`). Drop the scratch schema afterwards. **Owner must confirm** where the archive password is stored apart from the server.
  - Evidence: `config/backup.php:121-129,185-195`, `deploy-remote.sh:81-98` (the restore the deploy itself performs).
  - Verify: the migration count and a row count of a business table match production at the dump time.
- [ ] **9.4** **[developer]** Logs: `storage/logs/laravel-<date>.log`, 14 days (2.10). There is no alert destination in the repository other than health and backup mails; a Slack log channel exists (`LOG_SLACK_WEBHOOK_URL`) but would inherit `LOG_LEVEL`. **Owner must confirm** where errors get read daily.
  - Evidence: `config/logging.php:68-91`, `config/health.php:87-98`.
  - Verify: `tail -n 50 $DEPLOY_PATH/shared/storage/logs/laravel-$(date +%F).log` after a deliberate 404 shows no ERROR flood.
- [ ] **9.5** **[owner]** External uptime monitoring on `https://<HOST>/up` (Laravel's built-in route, public, WAF-excluded) and on the storefront home. **Owner must confirm** a monitor and who receives alerts; nothing in the repository sets one up (`config/health.php:106-124` has an optional Oh Dear endpoint, disabled).
  - Evidence: `bootstrap/app.php:44`, `config/waf.php:28-35`, `config/health.php:106`.
  - Verify: the monitor shows green; stop it from paging during maintenance windows (`php artisan down` returns 503).
- [ ] **9.6** **[developer]** Queue monitoring: failed jobs land in `wms_failed_jobs`; use `php artisan queue:failed` daily, `queue:retry <id>` / `queue:flush` deliberately, and read the health `QueueCheck`.
  - Evidence: `config/queue.php:123-127`, `routes/console.php:20,31`.
  - Verify: `queue:failed` prints "No failed jobs found" on day one.

## 10. Rollback plan

| Can be rolled back? | What | How |
| --- | --- | --- |
| Yes | Code deploy | repoint `current` to a previous release (10.1) |
| **No** (not with artisan) | Migrations | restore the pre-migration dump (10.2); `migrate:rollback` is prohibited in production |
| Harmless | Permission sync | additive; `down()` preserves data, so rolling code back leaves 182 unused rows |

- [ ] **10.1** **[developer]** Roll the code back to the previous release (the deploy keeps `HOSTINGER_KEEP_RELEASES`, default 5): `ln -sfn "$DEPLOY_PATH/releases/<previous_id>" "$DEPLOY_PATH/current.tmp" && mv -Tf "$DEPLOY_PATH/current.tmp" "$DEPLOY_PATH/current"`, then `cd $APP && $PHP artisan queue:restart`. This mirrors the deploy's own atomic publish; each release carries its own config/route/view caches. The previous code must tolerate the new schema, which holds for the additive migrations in Appendix C but is **not** proven for every table.
  - Evidence: `deploy-remote.sh:340-347,357,399-409`, `deploy.yml:226`.
  - Verify: `readlink $DEPLOY_PATH/current` names the previous release; `/up` is 200; the dashboard renders.
- [ ] **10.2** **[owner]** Decide **before** go-live who may order a database restore and what data loss is acceptable: restoring to the pre-migration snapshot discards everything written since. Artisan cannot undo migrations in production (`migrate:rollback`, `migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` all answer "This command is prohibited from running in this environment", **verified here**). Procedure when decided: `php artisan down --retry=60`, restore `$DEPLOY_PATH/shared/backups/<release_id>.sql` by the same drop-all-then-load steps as `restore_database`, repoint `current` (10.1), `php artisan up`.
  - Evidence: `app/Providers/AppServiceProvider.php:137-139`, `deploy-remote.sh:81-98`. The deploy's automatic restore happens only for a failure **before** the symlink flip; after publishing, a failed post-step leaves the release live and the database untouched (`deploy-remote.sh:100-139`).
  - Verify: the rollback runs successfully against a scratch schema in the 9.3 rehearsal; the deploy-script rollback logic is exercised by `bash .github/scripts/deploy-remote-test.sh` (CI job "Deploy script").
- [ ] **10.3** **[developer]** Know how to switch the new back office off quickly. In order of speed: (a) `cd $APP && $PHP artisan down --retry=60` (every route answers 503; the scheduler also pauses, 5.2); `artisan up` to resume; (b) block `/temp/admin*` at the web server (**Owner must confirm** access); (c) ban the Super Admin account (`UPDATE users SET banned = 1 WHERE id = <id>;` stops `IsAdmin`). No feature flag turns the `/temp/admin` routes off: they are mounted unconditionally.
  - Evidence: `deploy-remote.sh:309-310`, `app/Providers/RouteServiceProvider.php:62-65`, `app/Http/Middleware/Concerns/RequiresUnbannedStoreUser.php:22-28`.
  - Verify: on staging, `curl -s -o /dev/null -w '%{http_code}' https://<STAGING>/temp/admin` is 503 while down and 302/200 after `up`.
- [ ] **10.4** **[owner]** Name who decides to roll back, who executes it and how they are reached. The repository names no on-call person. **Owner must confirm** in §12 (G11).
  - Evidence: no evidence in the repo -- owner must confirm (`AGENTS.md` only says nothing is pushed without authorization).
  - Verify: the names and contact route are written in §12 (G11) before the production merge.

## 11. Post-launch watch list (first week)

- [ ] **11.1** **[developer]** Daily, in this order (10 minutes):
  1. Health: `$PHP artisan health:list`, and the `/health` page; every check `ok`, last backup under 24 hours.
  2. Logs: `grep -hE 'ERROR|CRITICAL|EMERGENCY' $DEPLOY_PATH/shared/storage/logs/laravel-*.log | cut -c1-200 | sort | uniq -c | sort -rn | head`.
  3. Failed jobs: `$PHP artisan queue:failed`.
  4. **403s on `/temp/admin` routes** (a missing grant): `awk '$9==403 && $7 ~ /^\/temp\/admin/ {print $7}' <web-server access log> | sort | uniq -c | sort -rn | head` (log path and format: **Owner must confirm**). Each hit means a staff role lacks a permission; map the URL to its permission with the audit JSON and grant it deliberately (4.9).
  5. WAF blocks: `grep -c 'Blocked malicious request' $DEPLOY_PATH/shared/storage/logs/laravel-*.log`; a rise on a business page means a legitimate input matched a signature (6.9).
  6. CSP: ask staff to report broken maps, charts, editors or missing images; there is no automatic report (F6).
  7. Sync and queue: `SELECT MAX(updated_at) FROM products;` moves hourly after changes upstream; `SELECT COUNT(*) FROM wms_jobs;` near 0.
  8. Mail: bounce/failed-send reports at the provider (**Owner must confirm** access).
  - Evidence: `routes/console.php`, `app/Http/Middleware/BlockMaliciousRequests.php:177`, `app/Providers/HealthServiceProvider.php`.
  - Verify: results written in the sign-off sheet each day (§12, G12).
- [ ] **11.2** **[developer]** Payout and withdrawal watch: read the day's rows read-only, compare with what the finance owner expects, and never let anyone but the finance owner record a payout. Semantics (retained, not new): a cash or bank withdrawal payout closes the whole request, debits `shops.admin_to_pay` and inserts one `payments` row; any other method credits it and does not close the request. Queries: `SELECT id, seller_id, amount, payment_method, txn_code, created_at FROM payments WHERE created_at >= NOW() - INTERVAL 1 DAY ORDER BY id DESC;` and `SELECT id, user_id, amount, status, viewed, created_at FROM seller_withdraw_requests WHERE created_at >= NOW() - INTERVAL 1 DAY ORDER BY id DESC;`.
  - Evidence: guide `store-backoffice-migration.md:3660-3690` (§35), `database/migrations/2026_09_28_003010_create_payments_table.php:21-28`, `database/migrations/2026_09_28_004010_create_seller_withdraw_requests_table.php:21-28`.
  - Verify: every `payments` row has a known business reason.
- [ ] **11.3** **[owner]** Triage the **known defects** the migration documented but did not fix. None blocks launch; each needs an explicit "accept for week one" or a ticket. Evidence is cited per row:

  | # | Defect | Source |
  | --- | --- | --- |
  | D1 | A partial withdrawal payout still closes the **whole** request | guide `store-backoffice-migration.md:3671-3676` |
  | D2 | The "Clear due" form (zero or negative due) cannot succeed: it omits `payment_option`, which the writer requires | guide `store-backoffice-migration.md:3684-3686` |
  | D3 | The withdrawal form has no transaction-code field | guide `store-backoffice-migration.md:3685-3686` |
  | D4 | The withdrawal resource's create, show, edit, update and destroy are empty: there is no working approve, reject, message-write or delete workflow | guide `store-backoffice-migration.md:3678-3682` |
  | D5 | No backend maximum on a payout amount (balance or request) | guide `store-backoffice-migration.md:3662-3667` |
  | D6 | The seller dashboard (seller home) raises a SQLite-only `DATE_FORMAT` error; MySQL behaviour not run | ledger `store-backoffice-reconciliation-20261011.md:4665-4667` |
  | D7 | Root product handlers: `get_products_by_subcategory` is missing and search does not return; publishing changes stock before refusing a package | ledger `store-backoffice-reconciliation-20261011.md:4819-4830` |
  | D8 | `admin_product_edit` / `seller_product_edit` redirect digital products to URLs without `/temp` | guide `store-backoffice-migration.md:1352-1358` |
  | D9 | Order tax changes are held: entry handlers store raw product tax metadata and sum line prices | guide `store-backoffice-migration.md:672-674` |
  | D10 | Trip: "mark delivered" and invoice number apply to any order the caller names; a checkpoint status applies to any checkpoint; the CMR download opens only the first upload id | ledger `store-backoffice-reconciliation-20261011.md:3254-3257,3373-3376` |
  | D11 | Brand template reads an `order_level` column absent from both schemas (inherited; the brand module was later converted, §14) | ledger `store-backoffice-reconciliation-20261011.md:533-536` |
  | D12 | Header/footer scripts and rich-text WAF exceptions are held (R8) | guide `store-backoffice-migration.md:601-604` |
  | D13 | On MySQL stored decimals display as `12.50` in the product edit form; a product whose stored choice order does not match its stock names shows default rows and saving deletes the unmatched rows | guide `store-backoffice-migration.md:2456-2462` |
  | D14 | Blade rich-text (summernote) remains on 8 fields in 8 views; the mobile API has GET routes that change data | guide `store-backoffice-migration.md:621-626`, `.ai/rules/legacy-albaraka-holland.md:1734-1742` |
  | D15 | Storefront views that build `route('product' / 'products.category' / 'products.brand', $x->slug)` without a null-slug guard (related and offered products, every listing card, cart, wishlist, search, compare, order pages): a 500 for a row without a slug. The `unit_equal` division, the top-selling widget and the missing blog / policy rows were fixed in guide §43 | guide `store-backoffice-migration.md` §43, "Found on the way" |
  | D16 | Public storefront forms whose fields disagree with their server rules (guide §44 top 20). **Fixed** (guide §45): the storefront and seller address forms and checkout shipping info; (guide §47) seller registration (`shops.store`) and the representative's create-customer form (`store_customer`), and longitudes of 100 or more, which the address columns could not hold. **Left**: endpoints that validate nothing (password reset by code, conversations, support tickets, seller products and coupons); the create-customer rules' limits against their columns (§47 judgement 7); the registration fields `RegisterController` never stores (owner decision) | `.ai/required-fields-audit-20261021.md`, guide `store-backoffice-migration.md` §44, §45, §47 |

  - Verify: §12 (G13) lists each defect with accept / ticket.
- [ ] **11.4** **[owner]** Search engines, once the real domain serves the storefront (the checks below mean nothing on a staging host): confirm the **canonical host**. Every canonical URL, `og:url`, sitemap URL and the `Sitemap:` line is built from `APP_URL` (`app/Support/Seo.php`), not from the host that was asked. `APP_URL` must be the one public address (scheme, host, www or not): `curl -s https://<HOST>/temp | grep -E 'rel="canonical"|og:url'` must print that address. Then make every other spelling (http, the other of www / non-www) redirect to it at the web server or CDN: the application does not.
  - Evidence: `app/Support/Seo.php` (`origin()`), `.env.production.example` (`APP_URL`), guide §42.
  - Verify: `curl -sI http://<HOST>/temp` and the non-canonical host both answer a 301 to the canonical address.
- [ ] **11.5** **[owner]** Read the live **robots.txt** and the page tags: `curl -s https://<HOST>/robots.txt` lists `Disallow:` lines for `/admin`, the store back office, cart, checkout, the account pages, the seller area and the API, and a `Sitemap:` line with the public address. `curl -sI https://<HOST>/admin/login | grep -i x-robots-tag` and the same for `/temp/admin` and `/temp/api/v2/currencies` print `noindex, nofollow`. A product, a category, the home page and a policy page print `index, follow` (`curl -s <URL> | grep 'name="robots"'`); the login page, the cart and a search results page do not. A file at `public/robots.txt` would hide the generated one: there must be none.
  - Evidence: `app/Http/Controllers/RobotsController.php`, `app/Http/Middleware/SendNoIndexHeader.php`, guide §42.
  - Verify: the five commands above print what is described.
- [ ] **11.6** **[owner]** **Submit the sitemap** in Google Search Console (and Bing Webmaster Tools): verify the property for the canonical host, then add `https://<HOST>/temp/sitemap.xml` (the index; `sitemap.xml` without `/temp` once the prefix is removed). Check that Search Console reads every child file, and that the URL count is close to the number of published, approved products plus categories, brands, posts and pages. The sitemap is cached for an hour and is built only from visible content; it lists no `lastmod` for products on purpose (guide §42).
  - Evidence: `app/Services/Sitemap.php`, guide §42.
  - Verify: Search Console, Indexing, Sitemaps, shows "Success" for the index and each child file; "Discovered URLs" matches expectation.
- [ ] **11.7** **[owner]** Run a **rich-results test** (Google's Rich Results Test, or the Schema Markup Validator) on one real product URL, the home page and one blog post. Expect: a valid `Product` with name, image, description, sku, brand and a breadcrumb; no price, offer or availability, because the storefront shows prices only to signed-in, admin-verified accounts and the markup follows the page (a signed-in verified visitor sees `offers`; a crawler never does). Google lists a product without an offer as a "Product snippet", not as a merchant listing. **Owner decision:** publishing prices to everyone would change that rule in `app/Support/StructuredData.php` and in the product page; it is a business decision, not a SEO fix. `aggregateRating` appears only when the reviews section is on and the product has published reviews. The blog records no author, so `BlogPosting` carries none.
  - Evidence: `app/Support/StructuredData.php`, `resources/views/shop/frontend/product_details/details.blade.php` (the `admin_verified` price rule), guide §42.
  - Verify: the test reports no errors on the three URLs; warnings about a missing `offers` or `author` are expected.
- [ ] **11.8** **[owner]** Plan the **removal of the `/temp` prefix** together with search engines: the storefront is mounted under `/temp` temporarily, and every canonical, sitemap, `robots.txt` and JSON-LD URL follows the routes. The day the prefix is removed, every indexed `/temp/...` URL must 301 to its new address (the application does not redirect them), `APP_URL`'s sitemap address changes to `/sitemap.xml`, and the new sitemap must be resubmitted (11.6) and the old one removed. Do it once, not twice: an address that changes twice loses its history twice. The code that still names the prefix is `app/Providers/RouteServiceProvider.php`, `bootstrap/app.php` (`redirectTo`) and the `app-url` meta in the layouts; `Seo`, `Sitemap`, `StructuredData` and `RobotsController` do not.
  - Evidence: `app/Providers/RouteServiceProvider.php:53`, guide §42.
  - Verify: a written plan with the redirect map and the date, before the prefix is touched.
- [ ] **11.9** **[owner]** Not done, deliberately (guide §42), so do not expect it to be there: `hreflang` (the site serves Arabic and English from one URL by session language), AMP, an image sitemap, `twitter:site` / `twitter:creator` (no handle is stored in the settings), social profile links in `Organization`, and Search Console / Bing set-up itself. Decide whether any of them matters before spending time on it.
  - Verify: each is accepted or ticketed in §12 (G14).

## 12. Sign-off

| Gate | Item(s) | Owner | Date | Result |
| --- | --- | --- | --- | --- |
| G1 Database topology decided (empty vs existing) | 1.1, 3.2 | | | |
| G2 Full `tests` run green on the release SHA (MySQL group, audits, deploy-script test, E2E, font-bearing build); SHA recorded: ________ | 1.3, 1.4 | | | |
| G3 SAST green; staging deployed and §7 walked with CSP enforced; deploy rule and `main` environment protection confirmed | 1.5 to 1.7, 6.3 | | | |
| G4 Secrets rotated; legacy server not reused; DNS, TLS, mail, hosting and cron confirmed | 1.9, 1.11, 2.5, 5.1 | | | |
| G5 `products` second writers accepted; payment gateways off | 3.9, 8.6 | | | |
| G6 Backup before migrate; migrations applied; permission baseline 182 / 0 / 0 | 3.3, 3.5, 3.8, 4.3 | | | |
| G7 First Super Admin created; no seeded test accounts exist; warehouse admin decision | 4.4 to 4.7 | | | |
| G8 Accepted-risk register R1 to R9 signed | 4.9 to 4.11 | | | |
| G9 Dependency audits accepted (six dev-tool findings) | 6.11 | | | |
| G10 Uploads persist across releases; backups and restore rehearsal done; off-host copies confirmed | 5.6, 9.2, 9.3 | | | |
| G11 Rollback owner and procedure named | 10.2, 10.4 | | | |
| G12 First-week watch list owner assigned | 11.1 | | | |
| G13 Known defects D1 to D16 accepted or ticketed | 11.3 | | | |
| G14 Search-engine steps after launch: canonical host, robots, sitemap submitted, rich-results test, `/temp` removal plan, deliberate omissions accepted | 11.4 to 11.9 | | | |
| **Go / no-go** | all of the above | | | |

---

## Appendix A. Environment keys

"Unsafe" marks a `.env.example` default that must not reach production.

| Key | Production value | `.env.example` | Unsafe? | Source |
| --- | --- | --- | --- | --- |
| `APP_NAME` | real name (cookie and cache names derive from it) | `Laravel` (:1) | change | `config/session.php:130-133`, `config/cache.php:123` |
| `APP_ENV` | `production` | `local` (:2) | **yes** | `config/app.php:69` |
| `APP_DEBUG` | `false` | `true` (:4) | **yes** | `config/app.php:82` |
| `APP_KEY` | new, generated once | empty (:3) | set | `config/app.php:212` |
| `APP_PREVIOUS_KEYS` | only while rotating a key | absent | | `config/app.php:214-218` |
| `APP_URL` | `https://<HOST>` | `http://localhost:8000` (:5) | **yes** | `config/app.php:120` |
| `FORCE_HTTPS` | unset behind a non-reporting proxy; `On` only if sure | absent | | `config/app.php:133` |
| `TRUSTED_PROXIES` | proxy IP/CIDR, never `*` | commented (:39) | required behind a proxy | `bootstrap/app.php:53-66` |
| `APP_LOCALE`, `DEFAULT_LANGUAGE`, `APP_FALLBACK_LOCALE` | `en` (or `ar`) | `en` (:17-19) | | `config/app.php:159,174,176` |
| `APP_MAINTENANCE_DRIVER` | `file` | `file` (:22) | | `config/app.php:233-236` |
| `DB_CONNECTION` | `mysql` | `sqlite` (:46) | **yes** | `config/database.php:40` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | real | commented (:47-51) | set | `config/database.php:100-104` |
| `DB_CHARSET`, `DB_COLLATION` | unset (`utf8mb4`, `utf8mb4_unicode_ci`) | absent | | `config/database.php:106-107` |
| `SESSION_DRIVER` | `database` | `database` (:100) | | `config/session.php:21` |
| `SESSION_LIFETIME`, `SESSION_ENCRYPT` | `120`, `false` | (:101-102) | owner may tighten | `config/session.php:35,50` |
| `SESSION_SECURE_COOKIE` | unset or `true` | `false` (:105) | **yes** | `config/session.php:177` |
| `SESSION_DOMAIN`, `SESSION_PATH` | `null`, `/` | (:106-107) | | `config/session.php:146,159` |
| `CACHE_STORE`, `QUEUE_CONNECTION` | `database` | `database` (:111,113) | | `config/cache.php:20`, `config/queue.php:16` |
| `FILESYSTEM_DISK` | `local` or `s3` (5.6) | `local` (:110) | decide | `config/filesystems.php:18` |
| `AWS_*` | only for s3 uploads or offsite backups | empty (:132-136) | set | `config/filesystems.php:52-63` |
| `MAIL_MAILER` and `MAIL_*` | real SMTP | `log` (:123-130) | **yes** | `config/mail.php:17` |
| `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS` | `daily`, `warning`, 14 | `single`, `debug` (:42,44) | **yes** | `config/logging.php:57,64,72` |
| `CSP_ENABLED`, `CSP_REPORT_ONLY` | `true`; **unset or `false`** | `true`; `true` (:65-66) | **yes** | `config/secure-headers.php:618,630` |
| `HSTS_ENABLED`, `WAF_ENABLED`, `HONEYPOT_ENABLED` | `true` | `true` (:55-68) | | `config/secure-headers.php:148`, `config/waf.php:16`, `config/honeypot.php:10` |
| `TELESCOPE_ENABLED` | `false` | `true` (:70) | **yes** | `config/telescope.php:20` |
| `TELESCOPE_ALLOWED_IPS` | operator IPs (also the fallback for Pulse and health) | `127.0.0.1` (:73) | set | `config/telescope.php:125`, `config/pulse.php:142`, `config/health.php:175` |
| `TELESCOPE_DB_CONNECTION`, `PULSE_DB_CONNECTION`, `HEALTH_DB_CONNECTION` | `telescope`, `pulse`, `health` (SQLite files) | (:71,76,80) | | `config/database.php:73-95` |
| `PULSE_ENABLED`, `PULSE_ALLOWED_IPS` | decide (2.12) | `true` (:75) | decide | `config/pulse.php:47,142` |
| `HEALTH_ALLOWED_IPS`, `HEALTH_TO_ADDRESS`, `HEALTH_NOTIFICATIONS_ENABLED`, `HEALTH_SECRET_TOKEN`, `HEALTH_SLACK_WEBHOOK_URL`, `SCHEDULE_HEARTBEAT_URL` | set the first two | commented (:82,88) | set | `config/health.php:43,79,88,141,167,175` |
| `BACKUP_ARCHIVE_PASSWORD` | **required** | commented (:97) | set | `app/Providers/AppServiceProvider.php:48-54` |
| `BACKUP_OFFSITE_DISK`, `BACKUP_NOTIFICATION_EMAIL` | `s3`, ops inbox | commented (:93,98) | set | `config/backup.php:168,246` |
| `STORE_ASSET_BASE_URL`, `STORE_PRODUCTS_SYNC_URL` | set | empty (:10,15) | set | `config/store.php:34,49` |
| `WMS_TOKEN`, `OTAJER_TOKEN`, `OTAJER_URL` | new secrets / URL | empty (:142,145,146) | set | `config/app.php:210`, `config/services.php:151-152` |
| `STORE_PAYMENT_GATEWAYS`, `DEMO_MODE` | empty; unset | absent | | `config/store.php:55,77` |
| Social login | `GOOGLE_CLIENT_ID`/`_SECRET`, `FACEBOOK_CLIENT_ID`/`_SECRET`/`_PIXEL_ID`/`_PAGE_ID`/`_APP_ID`, `TWITTER_CLIENT_ID`/`_SECRET`, `SIGN_IN_WITH_APPLE_*` | absent | only if used | `config/services.php:62-127` |
| Maps, captcha, analytics, geolocation | `MAP_API_KEY`, `CAPTCHA_KEY`, `TRACKING_ID`, `OPENCAGE_API_KEY`, `IP_GEO_LOCATION_KEY` | absent | only if used | `config/services.php:103-121` |
| Firebase | `FCM_PROJECT_ID`, `FCM_SERVER_KEY`, `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT` | absent | `FCM_PROJECT_ID` defaults to a legacy project name | `config/services.php:94-97` |
| Payment gateways | `PAYHERE_*`, `IYZICO_*`, `NAGAD_*`, `PAYTM_*`, `TOYYIBPAY_*`, `NEXMO_*` | absent | only with 8.6 | `config/services.php:83-147`, `config/nagad.php`, `config/toyyibpay.php` |

## Appendix B. Scheduler (everything hangs off `schedule:run`)

| Command | When | What breaks if the cron or Task Scheduler entry is missing |
| --- | --- | --- |
| `queue:work --stop-when-empty --max-time=55` | every minute, no overlap (mutex 2 min) | no queued job ever runs: mail, notifications and the health queue heartbeat; `wms_jobs` grows; `QueueCheck` fails |
| `products:sync` | hourly, no overlap (15 min), logs `Scheduled products:sync run failed.` on failure | product `name`/`ar_name`/`published` go stale |
| `backup:clean` | daily 01:00 (mutex 10 min) | old archives accumulate (retention not applied) |
| `backup:run --only-db` | daily 01:30 (mutex 60 min) | no backups; `BackupsCheck` fails after 24 hours; no restore point |
| `health:check` | every minute | `/health` and mails go stale |
| `health:schedule-check-heartbeat` | every minute | `ScheduleCheck` reports the scheduler as not running (this is how a dead cron is noticed) |
| `health:queue-check-heartbeat` | every 5 minutes | `QueueCheck` fails |
| `model:prune` (`HealthCheckResultHistoryItem`) | daily | `health.sqlite` grows (history is kept 5 days) |
| `telescope:prune --hours=48` | daily | `telescope.sqlite` grows (only if Telescope is on) |

Evidence: `routes/console.php:7-32`; `php artisan schedule:list` (checked here, 9 entries). Deliberately **not** scheduled (data-changing, need business sign-off): `routes/console.php:34-47`. Maintenance mode pauses all of it (`vendor/laravel/framework/src/Illuminate/Console/Scheduling/Event.php:287`).

## Appendix C. The migration series

156 migration files in total. Those that matter for go-live:

**C.1 Added by the back-office series (2026-09-28 to 2026-10-20).**

| Migration | Kind |
| --- | --- |
| `2026_09_28_000000` … `2026_09_30_000018` (94 `create_*` files) | store tables created only if missing (pattern: `database/migrations/2026_09_28_004009_create_sellers_table.php:12-18`, `down()` returns early in production at `:48-50`) |
| `2026_09_28_006000_add_section_to_wms_permissions_table` | schema: `wms_permissions.section` |
| `2026_09_29_000000_add_category_id_to_products`, `2026_09_29_000001_add_user_id_to_uploads` | schema: columns |
| `2026_09_30_000000_add_legacy_columns_to_store_tables` | schema: columns plus `->change()` alterations |
| `2026_10_01_000001_add_upload_metadata_to_uploads` | schema: columns |
| `2026_10_03_000001_create_wms_gateway_payments_table`, `2026_10_03_000003_create_wms_store_verification_codes_table` | schema: new tables |
| `2026_10_03_000002_add_user_id_created_at_index_to_cell_status_logs_table`, `2026_10_03_000005_add_indexes_to_store_tables` | schema: indexes |
| `2026_10_03_000004_add_unique_user_coupon_index_to_coupon_usages_table` | **deletes duplicate rows**, then adds a unique index |
| `2026_10_04_000001_add_allowed_open_levels_to_wms_settings_table` | schema: column |
| `2026_10_08_000001_add_customer_archive_column_to_users_table` | schema: column on the store `users` |
| `2026_10_08_090745_add_store_upload_permissions` | permissions |
| `2026_10_08_102608_add_customer_administration_permissions` | permissions |
| `2026_10_08_211642_add_offers_catalog_permissions` | permissions |
| `2026_10_09_230302_add_store_logistics_permissions` | permissions (includes `delete_truck`) |
| `2026_10_10_035932_create_store_order_submissions_table` | schema: new table, drops `order_details.entry_unit` on rollback |
| `2026_10_10_035933_add_order_entry_permissions` | permissions (does not flush the permission cache, audit G-10) |
| `2026_10_11_235900_define_store_blog_and_support_action_permissions` | permissions |
| `2026_10_15_000000_add_deleted_at_to_wms_users_table` | schema: soft delete on `wms_users` |
| `2026_10_20_120000_sync_store_permissions` | permissions: all 182 via `StorePermissions::sync()`; `down()` preserves data |

**C.2 MySQL-specific risks the SQLite suite cannot show.**

- *Signed foreign keys.* `products.id` and `uploads.id` are signed `int(11)`; every product foreign key is a plain `integer`, never `foreignId()`. A `bigint unsigned` reference is rejected by MySQL (errno 3780) and sqlite never notices. Source: `.ai/rules/shared-database.md:291-320,536-545`, `README.md:97-101`; only a source-text test guards it.
- *FULLTEXT.* The `products(name, ar_name)` index must match the `MATCH` column list exactly (errno 1191 otherwise); sqlite guards on the driver. Source: `.ai/rules/shared-database.md:103-152`; MySQL-only test `AddFulltextIndexToProductsTableTest` (`tests.yml:371`).
- *Collation.* The default is case-insensitive `utf8mb4_unicode_ci`; ordering of Arabic text differs from sqlite's byte order, which is why name ordering stays on the base column. Source: `config/database.php:107`, `.ai/rules/shared-database.md:272-283`, guide `store-backoffice-migration.md:959-963` (an earlier MySQL 8.4.11 probe), `:887-890` ("still unverified").
- *Index key length.* `Schema::defaultStringLength(191)` caps indexed varchars for utf8mb4; guarded by `SchemaIdentifierLengthTest` (`tests.yml:370`). Source: `app/Providers/AppServiceProvider.php:42`.
- *Locking and DDL.* MySQL commits DDL immediately; a failed migration leaves half-created tables, which is why the deploy brackets migrate with maintenance mode and a snapshot. The `->change()` alterations in `add_legacy_columns_to_store_tables` and the unique-index build on `coupon_usages` rebuild or lock populated tables on an existing store database. No test measures lock time. Source: `deploy-remote.sh:6-13`, guide `store-backoffice-migration.md:887-890`.
- *Version.* CI uses `mysql:8.4` (`tests.yml:338`); the local attested run uses 8.0 (`.ai/rules/ci.md:71`); production's version is **Owner must confirm**.

## Appendix D. What the full CI run covers that local runs did not

| Job (`tests.yml`) | Covers | Local run |
| --- | --- | --- |
| `Pest (MySQL migrations)` `:322-372` | four migration tests on a real MySQL 8.4: redundant FK indexes, identifier length, FULLTEXT, store indexes | not run (SQLite only) |
| `Dependency audit` `:377-405` | `composer audit --locked --abandoned=report`, `npm audit --omit=dev --audit-level=high` | not run |
| `Deploy script` `:239-252` | rollback guarantees of `deploy-remote.sh` against stubbed binaries | not run |
| `Playwright E2E` `:407-453` | full browser suite (`npm run test:e2e` = `vite build && playwright test`, `package.json:15`) | partial / skipped |
| `Pest` `:272-315` | full suite after a real `npm run build` that fetches the Bunny font | font-less build (`.claude/scripts/build_without_fonts.py`) |
| `Pint`, `PHPStan`, `Prettier`, `JS checks` | formatting, static analysis, ESLint, vue-tsc, Vitest | run locally on SQLite |
| `sast.yml` | Semgrep (security-audit, OWASP, secrets, PHP, JS, TS rulesets); ERROR severity gates | not run; no manual trigger (F11) |

Mobile-API contract tests (run inside `Pest`): `tests/Feature/Api/V1/*Test.php` (warehouse app), `LegacyStoreApiTest.php`, `LegacyStoreApiCodesTest.php`, `LegacyStoreApiMoneyTest.php`, `LegacyDeliveryBoyApiTest.php`, `WarehouseApiTest.php`, `WmsAuthMiddlewareTest.php`, `EnsureMinimumAppVersionTest.php`, `TokenExpirationTest.php`.

## Appendix E. Documentation that is out of date (do not rely on it)

| Document | Says | Code says |
| --- | --- | --- |
| `README.md:79-81` | one shared MySQL database with a store app; staging deploys from `.github/workflows/deploy-staging.yml` on a green `tests` run | `.ai/rules/shared-database.md:16-20`: own database; the workflow is `deploy.yml`, triggered by push to `develop`/`staging`/`main` (`deploy.yml:25-30`) |
| `README.md:43` | `/` redirects to `/login` | `/` redirects away to `https://albaraka-holland.nl/` (`app/Http/Controllers/WelcomeController.php`, `tests/Feature/RootRedirectTest.php:5-9`) |
| `README.md:19` | PHP 8.4+ | `composer.json:12` requires `^8.5` |
| `.ai/rules/shared-database.md:45-50` | the app never writes `uploads` | `app/Services/Concerns/WritesUploads.php:41-48` creates `Upload` rows |
| `routes/console.php:9-13` | the deployment target is a Windows VPS | the target is Hostinger (`deploy.yml:20-24`) |
| `.github/scripts/deploy-remote.sh:353-355` | queue drained by a cron `queue:work` | the scheduler runs it (`routes/console.php:20`) |
| `.env.example:61-63` | CSP "ships report-only" | enforced in production unless `CSP_REPORT_ONLY` is set (`config/secure-headers.php:630`) |

## Appendix F. What was verified, and what was not

**Checked in this session.**

- Every `path:line` citation in this file was resolved by a script (file exists, line within the file); see the shipping notes in the pull request.
- Artisan command names via `php artisan list` / `--help`: `migrate` (`--pretend`, `--force`), `migrate:status`, `store:sync-permissions` (`--dry-run`), `store:move-trade-licenses` (`--dry-run`), `backup:run` (`--only-db`), `backup:clean`, `backup:list`, `queue:work` (`--stop-when-empty`, `--max-time`), `queue:restart`, `queue:failed`, `health:check`, `health:list`, `products:sync`, `schedule:run`, `schedule:list` (9 entries), `storage:link`, `config:cache`, `route:cache`, `view:cache`, `event:cache`, `down`, `up`, `tinker --execute`, `app:set-minimum-app-version`. Composer and npm script names against `composer.json` / `package.json`; workflow names against `.github/workflows`; route names against `php artisan route:list --json` (1,190 routes, 434 under `/temp/admin`).
- In `APP_ENV=production` on throwaway SQLite files outside the repository: fresh `migrate` (156 migrations, 182 store permissions, 0 store roles, 0 grants); `store:sync-permissions` dry-run and real; `migrate:status` on an empty database; `migrate --pretend` side effects (F2); seeder behaviour with and without `--force` (F1); the prohibited destructive commands (F10); the two account-creation recipes (4.4, 4.6).

**Checked in the hardening PR (on this tree, not on any production system).**

- Pest files for each change: `SeedersRefuseProductionTest`, `DatabaseSeederTest`, `StoreRoleSeederTest` and the other seeder tests; `CreateStoreAdminCommandTest`; `ProductionEnvExampleTest`; `CreateHealthTablesTest`; each was also run with its fix reverted or a value changed to confirm it fails.
- `bash .github/scripts/deploy-remote-test.sh` (the deploy-script test) against stubbed binaries, with and without the `event:cache` line.
- In `APP_ENV=production` on scratch SQLite files: `db:seed --class=StoreUserSeeder --force` refused; `store:create-admin` with `--no-interaction` and with an existing email; `migrate --pretend` then `migrate`. Under `APP_ENV=testing`: the Playwright seed command (`migrate:fresh --seed`) still creates `Super Admin`, `Clerk` and `store-admin@example.com`.
- The ledger entry for step 41 lists the full local gate (`pre_push_check.py --full`) and what CI ran.

**Not verified (and not claimed).**

- Anything external: DNS, TLS, the web server and PHP configuration of the host, `proc_open`, mail provider, backup storage, GitHub secrets, environment protection, cron entries, who is on call.
- Any MySQL behaviour: collation, signed foreign keys, FULLTEXT, locking and DDL timing were taken from the documents and CI configuration, not run. No `migrate` was run on MySQL.
- Production data: which permission rows, roles or accounts exist; whether an existing store database is being reused.
- Real login over HTTP with the recipe-created account; the browser walks in §6.3 and §7; the full deploy script on a host; `event:cache` and `OptimizedAppCheck` together (read from code only).
- For the original PR #453 that carried this file: `pre_push_check.py` was not run (docs-only change); PR CI, SAST and deploy did not run (`[skip ci]`). The hardening PR is not `[skip ci]`, so it is the first to run them.

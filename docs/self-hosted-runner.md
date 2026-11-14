# Self-hosted CI runners (Windows VPS)

The `tests`, `sast` and `deploy` workflows run on runners that live on the team VPS,
not on GitHub-hosted runners, so CI does not consume GitHub-hosted minutes. This page
is the runbook: what it is, how to set it up, how to run it, and what to do when a job
fails on it. The provisioning scripts are in
[`.github/runner-setup/`](../.github/runner-setup/README.md); the rules that keep the
workflows and the scripts consistent are in [`.ai/rules/ci.md`](../.ai/rules/ci.md).

> **Honest status.** The workflows and these scripts were written without access to the
> VPS or any Windows machine. Nothing here has run on Windows yet. The first pull request
> that runs on the VPS is the real test, and some jobs will probably need a fix (see
> [First run: what is unproven](#first-run-what-is-unproven)). Where this page says a
> command "does" something on Windows, read "is meant to".

## What this is, and why

| | |
| --- | --- |
| Where | One Windows Server VPS (reached through Radmin), no Docker, no WSL, 4+ cores, 8 GB+ RAM |
| What runs | Every job of `tests.yml`, `sast.yml` and `deploy.yml`, each `run:` step in Git for Windows' bash |
| How many | 3-4 instances of the official `actions/runner` (default 4), each a Windows service with auto-start, running one job at a time |
| Selected by | The repository variable `CI_RUNNER_LABELS` (JSON), default `["self-hosted","windows","warehouse-vps"]` |
| Fallback | None: with no runner online, jobs wait. See [Taking the VPS out of service](#taking-the-vps-out-of-service) |

Why: a typical pull request costs about 24 billed runner minutes before attestation
and about 1 with it (`AGENTS.md`), each job rounded up to a whole minute. A VPS the team already
pays for makes that free at the margin, at the price of owning the machine.

## Architecture in a few lines

```
GitHub  <--HTTPS (runner polls)--  C:\actions-runners\wh-1 .. wh-4   (4 Windows services, auto start,
                                      |  each: own folder, own .env, own E2E port pair,   run as a local
                                      |  hook wipes _work after every job)                non-admin account)
                                      |
   machine-wide, read-only for jobs:  +-- C:\ci-tools\php, composer   Git for Windows (bash, ssh, openssl)
   shared on loopback:                +-- MySQL 8.4 service on 127.0.0.1:3307 (databases whci_<runner>_<run>_<attempt>)
   maintenance:                       +-- scheduled task, weekly: stale work folders, caches, browsers, databases
```

- **PHP, Composer, Python, gh, Git** are installed once on the machine. Jobs only check them
  (`.github/scripts/require-php.sh`, `Find Python`); `setup-php` and `actions/cache` run only on
  GitHub-hosted runners (`runner.environment`).
- **Node and the Playwright browsers** come from `actions/setup-node` and
  `npx playwright install chromium` into each instance's own tool cache
  (`_work\_tool`), so instances never share an install.
- **MySQL** is one native service shared by all instances. Each `Pest (MySQL migrations)` job
  creates, migrates and drops its own `whci_...` database
  (`.github/scripts/ci-mysql.php`); the CI account can touch only schemas named `whci\_%`.
- **E2E ports**: instance `n` (from 1) uses `E2E_PORT=8010+2(n-1)` and
  `E2E_BACKEND_PORT=8011+2(n-1)` from its `.env`: instance 1 uses 8010/8011, instance 4 uses
  8016/8017 (`tests/e2e/support/ports.mjs`).
- **Clean workspace per job**: `hooks\job-completed.ps1` (the runner's
  `ACTIONS_RUNNER_HOOK_JOB_COMPLETED`) stops what the job left running and deletes the job
  workspace under `_work`, keeping `_tool`, `_actions` and `_temp`.
- **Runner names are unique** (`<computer>-wh-<n>`): the MySQL leftover cleanup identifies a runner
  by its name.

## Prerequisites

**The machine** (decided, not checked by GitHub): Windows Server, 64-bit, administrator access
through Radmin, 4+ cores, 8 GB+ RAM, tens of GB free (the scripts warn below 60 GB free), no WSL
(its `bash.exe` would win over Git Bash), no Docker needed.

**Outbound HTTPS** (the check in step 1 tests the first group; GitHub's own list of runner hosts
also includes `*.actions.githubusercontent.com` and others, see its self-hosted runner
documentation):

| For | Hosts |
| --- | --- |
| Runner, checkout, actions, status | `github.com`, `api.github.com`, `codeload.github.com`, `*.actions.githubusercontent.com` |
| `actions/setup-node` | `nodejs.org` (and GitHub) |
| Composer / npm | `repo.packagist.org`, `registry.npmjs.org` |
| Semgrep | `pypi.org`, `files.pythonhosted.org`, `semgrep.dev` (rules) |
| Playwright | `cdn.playwright.dev` (some versions `playwright.download.prss.microsoft.com`) |
| Step 1/2 downloads | `windows.php.net`, `getcomposer.org`, `curl.se`, `aka.ms`, `www.python.org` (only if Python is missing), `cdn.mysql.com` |
| Deploy | The Hostinger SSH host and port (secret `HOSTINGER_SSH_HOST` / `HOSTINGER_SSH_PORT`), outbound from the VPS; Hostinger must accept the VPS's IP |

**On GitHub**: repository admin rights (to create the registration token and the variable), and the
deploy environments' secrets already in place (`HOSTINGER_*`, unchanged by this move).

**On your side before step 2**: the version and MD5 of the MySQL 8.4 Windows ZIP from
<https://dev.mysql.com/downloads/mysql/8.4.html> (Windows (x86, 64-bit), ZIP Archive; the MD5 is
shown beside the download; the page also offers a GnuPG signature, which is the stronger check
and is not automated). If no Python 3.10+ exists, the version of the python.org installer and its
MD5 from the release page.

## Setup, step by step

All commands run in an **elevated, 64-bit Windows PowerShell** on the VPS, in the folder holding the
scripts. Copy `.github\runner-setup` (and, optionally, `.github\scripts` next to it) to the VPS with
Radmin's file transfer; run `Get-ChildItem . | Unblock-File` if a browser downloaded them. Every
script takes `-DryRun`: run it first and read the plan. Each stops at its first error and is safe to
re-run.

1. **Prerequisites**

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\01-prerequisites.ps1 -DryRun
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\01-prerequisites.ps1
   ```

   Installs Git for Windows (default path), PHP 8.5 NTS x64 with the extensions and `php.ini`
   settings `require-php.sh` checks, Composer 2 (callable from bash), gh, the Visual C++ runtime,
   `Server-Media-Foundation`; sets `LongPathsEnabled` and `core.longpaths`; puts Git Bash ahead of
   System32 on the machine `PATH`; adds Defender exclusions; checks ports and outbound hosts; and
   runs a few probes (bash finds its tools, the E2E certificate command works from the Windows
   `PATH`, `MSYS=winsymlinks:sys` makes a dangling symlink). If there is no Python 3.10+, add
   `-PythonVersion <x.y.z> -ChecksumOverride @{ 'python-<x.y.z>-amd64.exe' = '<md5>' }`. **Reboot
   if it says so, and in any case before step 3** (so every service sees the new `PATH`), then
   open a new elevated PowerShell.

2. **MySQL**

   ```powershell
   .\02-mysql.ps1 -MysqlVersion 8.4.x -MysqlChecksum <md5 from the download page> -DryRun
   .\02-mysql.ps1 -MysqlVersion 8.4.x -MysqlChecksum <md5 from the download page>
   ```

   Extracts MySQL, writes `my.ini` (`bind-address=127.0.0.1`, `port=3307`, `mysqlx=OFF`,
   `disable-log-bin`, `utf8mb4` / `utf8mb4_unicode_ci`), initializes the data directory, registers
   the automatic service `MySQL84` (as NetworkService), and creates `warehouse_ci` with
   ``GRANT ALL ON `whci\_%`.*`` for `127.0.0.1` and `localhost`. It **prints the root and
   `warehouse_ci` passwords once**: store them in your password manager now. They are not
   written anywhere. It then verifies version, loopback-only listening, settings, and that the CI
   account can create `whci_` databases and nothing else.

3. **Runners**

   Create the registration token: repository **Settings > Actions > Runners > New self-hosted
   runner**, choose Windows x64, copy only the value after `--token` (valid for one hour, enough
   for all instances; it needs repository admin rights). An admin with `gh` can also run
   `gh api -X POST repos/arb-rajab/warehouse_management/actions/runners/registration-token --jq .token`.

   ```powershell
   .\03-runners.ps1 -DryRun
   .\03-runners.ps1
   ```

   Asks (secure prompts, nothing echoed or stored) for the registration token and the
   `warehouse_ci` password from step 2. Creates the local account `ci-runner` (not an
   administrator; its generated password is shown once, keep it: adding instances later needs it),
   then per instance `C:\actions-runners\wh-N`: extracts the runner, writes `.env`
   (`E2E_PORT`, `E2E_BACKEND_PORT`, `CI_MYSQL_HOST`, `CI_MYSQL_PORT`, `CI_MYSQL_USER`,
   `CI_MYSQL_PASSWORD`, `ACTIONS_RUNNER_HOOK_JOB_COMPLETED`), installs the hook, registers the
   runner (labels `self-hosted`, `Windows`, `X64`, `warehouse-vps`) as an auto-start service that
   starts after MySQL and restarts on failure, and waits for "Listening for Jobs". Use
   `-PerInstanceAccounts` for one account per instance (see
   [Security trade-offs](#security-trade-offs)).

4. **Maintenance**

   ```powershell
   .\04-maintenance.ps1 -DryRun
   .\04-maintenance.ps1
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File C:\ProgramData\warehouse-ci\04-maintenance.ps1 -Mode Clean -DryRun
   ```

   Registers the weekly task `WarehouseCI-Maintenance` (Sunday 03:30, as SYSTEM). The last command
   lists what a cleanup would remove now. Run it for real with
   `Start-ScheduledTask -TaskName WarehouseCI-Maintenance`; logs are in
   `C:\ProgramData\warehouse-ci\logs\`.

5. **Check on GitHub**: Settings > Actions > Runners shows every instance **Idle** with the labels
   `self-hosted`, `Windows`, `X64`, `warehouse-vps`.

6. **The repository variable `CI_RUNNER_LABELS`: only if your labels differ from the default.**
   The workflows default to `["self-hosted","windows","warehouse-vps"]`, which is what step 3
   registers, so with the default set-up **do not create the variable**. If you register other
   labels (for example another `-ExtraLabels`), set Settings > Secrets and variables > Actions >
   Variables > `CI_RUNNER_LABELS` to the JSON list, e.g. `["self-hosted","windows","my-label"]`.

7. **Open a pull request** (or re-run the checks on an open one). That first run on the VPS is the
   real Windows test; watch it with the list below.

## First run: what is unproven

The earlier steps recorded these as unproven. None was ever run on Windows. Watch them on the
first runs, and do not assume a green job means all of them are fine.

| Watch | What to look at | If it is wrong |
| --- | --- | --- |
| Semgrep file count | The `sast` summary prints `Scanned N file(s).`; Linux scanned about 2349. A large drop means Semgrep on Windows skips files (paths, ignore rules, line endings). `semgrep_report.py` only fails on zero | Compare file lists with a Linux run before trusting the gate |
| MSYS `sys` symlinks | The `Deploy script` job (`deploy-remote-test.sh`) needs real, dangling symlinks via `MSYS=winsymlinks:sys`; it stops first with `ln -s does not make real symlinks here` | Step 1 probes the same thing; see the troubleshooting row |
| Pest and Pint `--parallel` | `php artisan test --parallel` (Paratest) and Pint on Windows: worker start, temp paths, SQLite in-memory databases, timeouts | Fall back to a single process for the failing tool and record it in `.ai/rules/ci.md` |
| Ghostscript-dependent tests | `PdfOptimizer` calls `gs` (config default `gs`); Windows ships `gswin64c.exe`, and no Ghostscript is installed here. The PDF tests found (`PdfOptimizerTest`, `ReceiptFileControllerTest`) fake the process, and `deploy-remote-test.sh` stubs `gs`, so they should not need it; a test that runs the real binary would fail | Install Ghostscript and set `GHOSTSCRIPT_BINARY`, or fake the process in that test |
| Path-separator assumptions | Tests or code that compare `/` paths, build paths with `/`, or assume POSIX temp directories; failures read as "path not found" or string mismatches containing `\` | Fix the test to use `DIRECTORY_SEPARATOR` or normalise; do not skip |
| Windows Server timeouts | Every job has `timeout-minutes` (plan 5, most 20, Semgrep 30; the E2E web server 120 s). Defender scanning, four jobs sharing the cores, first-time Node and Chromium downloads and slow NTFS can push jobs over | Look at the step timings first; tune exclusions and instance count before raising a timeout |
| Runner mechanics | `.env` is honoured by the services; `config.cmd` accepted `ACTIONS_RUNNER_INPUT_TOKEN` and `..._WINDOWSLOGONPASSWORD` from the environment and granted the account "log on as a service"; the hook runs and wipes `_work`; `.path` captured the right `PATH` | See the troubleshooting rows; the hook logs to `_diag\job-completed-hook.log` |
| Vendor endpoints | Step 1-3 read `windows.php.net/downloads/releases/releases.json`, `getcomposer.org/versions` and `.sha256sum`, GitHub release asset digests and notes, `curl.se/ca/cacert.pem.sha256`, `cdn.mysql.com`. The formats were written from memory and could not be fetched from the sandbox | A script that cannot find a checksum stops and names `-ChecksumOverride` |
| Playwright Chromium | Headless Chromium on Windows Server needs Media Foundation (step 1 installs it); also the E2E certificate made by `openssl` run from node | See "Playwright job fails to launch Chromium" |

### What was and was not machine-checked

Checked by the author, on Linux only: all four scripts and the embedded hook parse under the
PowerShell 7.5 parser with no PowerShell 7-only syntax (ternary, `??`, `&&`, `?.`); the pure
functions (random secrets, checksum comparison, release-asset checksum discovery against mocked
releases, `php.ini` generation from the real `php.ini-production` of PHP 8.5, `.env` merging,
`my.ini` text, age-based file removal) ran as unit tests under PowerShell 7; the two embedded PHP
SQL helpers ran against a MySQL 8.0 server (empty-root bootstrap, grants on `` `whci\_%` ``,
verification, database cleanup); the PHP extension list equals the one in `require-php.sh`.

**Not checked**: PSScriptAnalyzer (the PowerShell Gallery is unreachable from the sandbox);
Windows PowerShell 5.1 itself (only the 7.5 parser was available, so a 5.1-only runtime quirk is
possible); everything that touches Windows (services, registry, ACLs, `Install-WindowsFeature`,
Defender, scheduled tasks, the runner's `config.cmd`, MSYS symlinks, `robocopy`); MySQL 8.4 (8.0
was the only server available); vendor download formats (above).

## Operations

### Add or remove an instance

- **Add**: `.\03-runners.ps1 -Instances 5` (needs a fresh registration token and the service
  account password). It creates `wh-5` with ports 8018/8019 and leaves the others alone. Check the
  new pair is free; keep the count near the core count (4 cores: 3-4 instances).
- **Remove**: `.\03-runners.ps1 -Remove 4` asks for a *removal* token (Settings > Actions > Runners >
  the runner > Remove), stops the service, unregisters the runner from GitHub and deletes the folder.
  The instance must be idle. `-Remove 4 -LocalOnly` deletes locally without a token; delete the
  offline entry under Settings > Actions > Runners yourself. The local account stays; remove it
  with `Remove-LocalUser` when nothing uses it.

### Update the runner and the toolchain

Do these when the runners are idle: `Get-Process Runner.Worker -ErrorAction SilentlyContinue`
prints nothing (replacing PHP or MySQL under a running job breaks it).

| What | How |
| --- | --- |
| The runner | It updates itself (GitHub's default). New instances get the newest release |
| Node | Nothing on the VPS: `actions/setup-node` fetches it into each tool cache; the maintenance task prunes old versions |
| Playwright browsers | Automatic per Playwright version (each instance's tool cache); pruned by maintenance |
| PHP patch release, Composer, Git, gh | `Get-Service 'actions.runner.*' \| Stop-Service`, then `.\01-prerequisites.ps1 -Update`, then start the services. The old PHP stays as `C:\ci-tools\php.old-<date>` for a month |
| PHP minor (workflow bumps `php-version`) | The same with `-PhpMinor 8.6 -Update`; `require-php.sh` fails with `PHP X.Y is on PATH` until you do |
| MySQL 8.4 patch release | Stop the runner services, `.\02-mysql.ps1 -Update -MysqlVersion 8.4.y -MysqlChecksum <md5>`, start them |
| Windows updates | Allow them in a window when runners are idle; services start again after the reboot, MySQL first |

### Rotate the CI database password

```powershell
.\02-mysql.ps1 -RootPassword (Read-Host -AsSecureString 'root') -RotateCiPassword
.\03-runners.ps1 -CiMysqlPassword (Read-Host -AsSecureString 'warehouse_ci')
```

The second command rewrites every `.env` and restarts the services of idle instances; restart a busy
one yourself later (it warns).

### Looking at the machine

`Get-Service 'actions.runner.*', MySQL84`; per instance `C:\actions-runners\wh-N\_diag\Runner_*.log`,
`Worker_*.log` (a job's own log) and `job-completed-hook.log`; `Get-NetTCPConnection -State Listen
-LocalPort 8010,8011,3307`; the maintenance logs above. Event Viewer > Windows Logs > System shows
service start failures.

## Taking the VPS out of service

For maintenance or an outage, point the workflows at a GitHub-hosted runner for the time being:
set the repository variable `CI_RUNNER_LABELS` (Settings > Secrets and variables > Actions >
Variables) to the JSON string `"ubuntu-latest"` (with the quotes). New runs use it at once (cancel
and re-run jobs already queued for the VPS). The steps that differ by `runner.environment` switch to
`setup-php`, `actions/cache` and the Docker `mysql:8.4` automatically. Delete the variable to go
back to the VPS.

**What it costs**: every job then bills GitHub-hosted minutes against the account's included
minutes and, beyond them, per minute for a private repository, each job rounded up to a whole
minute. A typical unattested pull-request run is about 24 billed runner minutes, about 1 with a valid
attestation (`AGENTS.md`); `deploy.yml` and `sast.yml` add their own. There is no automatic
fallback: with the variable unset and the VPS down, jobs simply queue.

**Decommissioning for good**: `-Remove` every instance, `Unregister-ScheduledTask
WarehouseCI-Maintenance`, stop and `mysqld --remove MySQL84`, delete `C:\actions-runners`,
`C:\mysql*`, `C:\ci-tools` and `C:\ProgramData\warehouse-ci`, remove the Defender exclusions, then
change the workflows' default labels (the CI is not meant to be left pointing at a dead VPS).

## Security trade-offs

- **Instances that share one Windows user can read each other's files.** With the default
  single `ci-runner` account, a job on instance 1 can read instance 2's job files, caches and
  `.env` (the CI database password) while it runs, and, during a deploy, the SSH key in that
  instance's `RUNNER_TEMP\deploy-ssh`. The per-job directory only stops them overwriting each
  other. Stronger: `-PerInstanceAccounts` (one local account per instance, each folder readable
  only by its own account, separate profiles and caches), or a dedicated deploy runner (one
  instance used only for `deploy.yml`, with its own label; that needs a workflow change this
  repository does not have). The trade-off is more accounts to manage.
- **A job is arbitrary code from a pull request.** Keep the repository private, keep "Run workflows
  from fork pull requests" off, and require approval for outside collaborators.
- **The runner account is not an administrator**, but it can reach everything its own folder, its
  profile, loopback MySQL (only `whci\_%` schemas, no global privileges) and the network allow. The
  tools under `C:\ci-tools` and the MySQL binaries are read-only for it.
- **Defender exclusions** (runner folders, MySQL data, the service profiles) trade scanning for
  speed on those paths.
- **Secrets**: the registration token and passwords are never stored by the scripts; the
  runner's own `.credentials` and the CI password in `.env` sit in access-restricted instance
  folders. The MySQL root password exists only in your password manager.
- **Machine access**: Radmin is the way in; keep it patched and its password strong. MySQL listens
  only on loopback and the X protocol is off, so no firewall rule is needed or added.

## Troubleshooting

Messages are quoted as the workflows print them. `<...>` is a placeholder.

| You see | Likely cause | Fix |
| --- | --- | --- |
| Job stays *Queued* ("Waiting for a runner to pick up this job") | No online, idle runner has every label in `CI_RUNNER_LABELS`; services stopped; labels differ | `Get-Service 'actions.runner.*'`; Settings > Actions > Runners; compare labels with the variable |
| `PHP is not on PATH. This self-hosted runner must provide PHP 8.5` | The service's `PATH` lacks `C:\ci-tools\php` (installed after the runner registered, or no reboot) | Re-run step 1, reboot, delete the instance's `.path`, restart its service |
| `PHP <x.y> is on PATH; the workflow needs PHP 8.5` | Another PHP is earlier on the `PATH` (XAMPP, an old install) | `where.exe php` as the service account; put `C:\ci-tools\php` first (step 1 does) |
| `missing PHP extensions: <list>` | `php.ini` lacks them or the build does not ship them | Re-run step 1 (rewrites `php.ini`); `php -m`; compare with `require-php.sh` |
| `Composer is not on PATH` / `Composer on PATH is not version 2` | The shim is missing or an old Composer wins | Re-run step 1; from Git Bash `composer --version` |
| `Xdebug is loaded; it slows every PHP job` / `memory_limit is not -1` | Another `php.ini` is read (`PHPRC`, `PHP_INI_SCAN_DIR`) | `php --ini`; remove the extra ini; re-run step 1 |
| `<CI_MYSQL_PORT, ...> is not set on this runner, so the MySQL migration tests have no server` | `.env` missing a key, or the service has not been restarted since `.env` changed | Check `C:\actions-runners\wh-N\.env`; restart the service when idle (step 3 does) |
| `CI_MYSQL_PORT is '<x>', not a port number` | A typo in `.env` | Fix `.env`; re-run step 3 |
| `MySQL is unreachable at <host>:<port> as <user> (...)` | `MySQL84` stopped, wrong port, or the password in `.env` is not the database's (`Access denied`) | `Get-Service MySQL84`; `C:\mysql-data\mysql-error.log`; rotate the password as above |
| `MySQL <x> answers at <host>:<port>; the migration tests run against MySQL 8.4` | Another MySQL owns that port | Fix `-Port` in step 2 and `-MysqlPort` in step 3 |
| `The database name <name> is longer than MySQL's 64 characters` / `GITHUB_RUN_ID '<x>' and GITHUB_RUN_ATTEMPT '<y>' must be numbers` | Runner name far too long / a hand-run script without those variables | Shorter `-NamePrefix`; set the variables |
| `Could not drop <name>` (warning) | MySQL went away mid-job | Harmless; the next job on that runner drops it, maintenance drops the rest |
| `E2E port <n> (E2E_PORT) and <m> (E2E_BACKEND_PORT) is in use` | Another instance has the same pair, or a Playwright server survived a job (hook failed) | Compare each `.env`; `Get-NetTCPConnection -LocalPort <n>`, stop the process; read `_diag\job-completed-hook.log` |
| `E2E port <n> (E2E_PORT) ... is in use. E2E_PORT and E2E_BACKEND_PORT are not set on this runner` | The instance's `.env` lacks them | Re-run step 3; restart the service when idle |
| `E2E_PORT must be a port number from 1024 to 65535` | A bad value in `.env` | Fix it |
| Playwright job fails to launch Chromium (no exact message was recorded) | `Server-Media-Foundation` missing or pending a reboot; `openssl` not on the Windows `PATH` (the certificate step); a blocked download | Step 1 checks both; `Get-WindowsFeature Server-Media-Foundation`; run `openssl version` from cmd |
| `HOSTINGER_SSH_PRIVATE_KEY and HOSTINGER_SSH_KNOWN_HOSTS must both be set on the <env> environment.` | The environment's secrets are missing | Set them under Settings > Environments > `<env>` |
| `HOSTINGER_DEPLOY_PATH is not set as a variable on the <env> environment.` | The variable is missing | Set it as an environment variable |
| `/usr/bin/ssh is missing; this runner's bash must be Git for Windows'` | `bash` resolved to WSL or another bash, or Git is not in its default path | `where.exe bash` must list `C:\Program Files\Git\bin\bash.exe` first; remove WSL; re-run step 1 |
| `The runner's temp directory (<path>) contains whitespace or '%', which ssh options cannot carry` | The runner folder has a space or `%` | The scripts refuse such paths; move the instance to a plain path |
| `Host key verification failed.` / `Permission denied (publickey)` / ssh timeout | The pinned `known_hosts` line or key is wrong, or Hostinger does not accept the VPS's address or port | Re-check the secrets; test `ssh` from Git Bash on the VPS with the same options; ask Hostinger about IP allow-listing |
| `No Python 3.10+ on PATH (tried python3, python and py).` | No Python, or only the Microsoft Store alias | Step 1 with `-PythonVersion`; turn off the Store app execution aliases (Settings > Apps > Advanced app settings) for the service account too |
| `Could not read the PR commits; refusing to start the CI jobs without verifying attestation.` / `Could not list the PR files` | `gh api` failed: `api.github.com` unreachable, no `GH_TOKEN`, rate limit | Check connectivity from the VPS; re-run |
| `Installed Semgrep reports '<x>', expected 1.155.0.` or `pip` fails with "No matching distribution" | No Windows wheel for that Python, a too-new Python, or `pypi.org` / `files.pythonhosted.org` blocked | Use a Python the wheel supports; check the outbound hosts |
| `Semgrep scanned no files — the scan covered nothing, failing the job.` | Semgrep on Windows found nothing to scan (path or ignore handling) | Run `semgrep scan` by hand in the workspace; compare with the Linux count |
| `ln -s does not make real symlinks here (one to a missing target included).` | `MSYS=winsymlinks:sys` ineffective for that Git Bash or filesystem | Step 1 reports the same probe; check Git's version and the volume (NTFS) |
| `Filename too long` / paths past 260 characters | Long paths not enabled | Step 1 sets `LongPathsEnabled` and `core.longpaths`; reboot |
| Runner service will not start (error 1069 logon failure) | Wrong service-account password, or the account lacks "Log on as a service" | Re-register the instance (`-Remove n -LocalOnly`, `.\03-runners.ps1`), or grant the right in `secpol.msc` > Local Policies > User Rights Assignment |
| Runners offline after a reboot | MySQL or a runner service did not start | Event Viewer > System; `Start-Service MySQL84` then the runner services |
| Disk nearly full | Caches, browsers or workspaces piled up | `Start-ScheduledTask WarehouseCI-Maintenance`; read its log; fewer instances or a bigger disk |
| A job's workspace was not cleaned | The hook failed or was not set | `_diag\job-completed-hook.log`; `.env` must hold `ACTIONS_RUNNER_HOOK_JOB_COMPLETED`; re-run step 3 |

## Reference

| Item | Value |
| --- | --- |
| Services | `actions.runner.<owner>-<repo>.<computer>-wh-<n>`, `MySQL84`; task `WarehouseCI-Maintenance` |
| Per-instance `.env` | `E2E_PORT`, `E2E_BACKEND_PORT`, `CI_MYSQL_HOST`, `CI_MYSQL_PORT`, `CI_MYSQL_USER`, `CI_MYSQL_PASSWORD`, `ACTIONS_RUNNER_HOOK_JOB_COMPLETED` |
| MySQL | `127.0.0.1:3307`, user `warehouse_ci`, grants on `` `whci\_%`.* `` for `127.0.0.1` and `localhost`, databases `whci_<runner>_<run>_<attempt>` |
| Labels | `self-hosted`, `Windows`, `X64` (automatic) + `warehouse-vps`; selected by `CI_RUNNER_LABELS` |
| Where the workflows depend on this | `.github/workflows/*.yml` (the `CI_RUNNER_LABELS` expression), `.github/actions/setup-backend`, `.github/scripts/require-php.sh`, `.github/scripts/ci-mysql.php`, `tests/e2e/support/ports.mjs` |

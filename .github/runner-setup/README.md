# Runner VPS provisioning scripts

Four PowerShell scripts that turn a clean Windows Server VPS into the self-hosted
CI runners the workflows expect. The runbook, with the why, the architecture, the
troubleshooting table and the first-run watch list, is
[docs/self-hosted-runner.md](../../docs/self-hosted-runner.md); read it first.

**Status: written without access to a Windows machine. Parsed with PowerShell
7.5's parser and unit-tested in part (see the runbook); never run on Windows.**
Always start with `-DryRun`, read the plan, and expect to fix a first-run problem.

## Run order

| Step | Script | Does | Needs |
| --- | --- | --- | --- |
| 1 | `01-prerequisites.ps1` | Git for Windows, PHP 8.5 NTS x64 (+ extensions, `php.ini`), Composer 2, Python 3.10+ (if absent), gh, Visual C++ runtime, Server-Media-Foundation, long paths, Defender exclusions, port and outbound-host checks | Internet, administrator; may need a reboot |
| 2 | `02-mysql.ps1` | MySQL 8.4 service on `127.0.0.1:3307`, `my.ini`, the `warehouse_ci` account with grants on `` `whci\_%`.* `` | Step 1; MySQL ZIP version and the checksum from its download page |
| 3 | `03-runners.ps1` | N runner instances as auto-start services, per-instance `.env`, the job-completed hook, the service account | Steps 1 and 2; a registration token; the `warehouse_ci` password |
| 4 | `04-maintenance.ps1` | Weekly disk-cleanup scheduled task (and the cleanup itself) | Step 3 |

Run each from an **elevated 64-bit** Windows PowerShell 5.1 (the Windows Server
default), opened *after* the previous step so it sees the new `PATH`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\01-prerequisites.ps1 -DryRun
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\01-prerequisites.ps1
```

Copy the whole folder to the VPS (Radmin file transfer works); a file downloaded
through a browser needs `Unblock-File` first. `01-prerequisites.ps1` finishes by
running `.github/scripts/require-php.sh` through Git Bash when it finds it at
`..\scripts\` (copy `.github\scripts` next to the folder to get that check).

## What every script does the same way

- **`-DryRun`** (alias `-WhatIf`): prints `[plan]` lines for every change and
  changes nothing. Checks still run, so the plan shows the real current state.
- **Idempotent**: what is already right prints `[ok]` and is left alone. Nothing is
  upgraded without `-Update`. Access rules are applied once, not rewritten and
  re-propagated on every run.
- **Stops at the first error** (exit code 1) and says what to fix. Warnings are
  repeated in the summary.
- **Downloads are verified** before use: SHA256 published with the vendor's release
  (Git, gh, runner, PHP, Composer, the CA bundle), the MD5 or SHA256 you copy from the
  vendor's page (MySQL, Python), plus the Authenticode signature of the Visual C++ and
  Python installers. With no published checksum a download is refused; pass it with
  `-ChecksumOverride @{ '<file name>' = '<sha256 or md5>' }` or place the file in
  `C:\ci-tools\downloads` yourself.
- **Secrets**: generated with a cryptographic RNG, shown once at the end, never
  written to disk except the CI password inside each instance's access-restricted
  `.env` (the workflows read it from the runner's environment), never put on a command
  line (they travel in the child process's environment), never in the repository.
- Windows PowerShell 5.1 syntax, ASCII only, no module installs. The shared helper
  block between the `#region shared helpers` markers is identical in all four files
  (each file must stay standalone: `04-maintenance.ps1` is copied to a folder only
  administrators can write, because its task runs as SYSTEM). Edit the four together.

## Parameters (the ones you will use)

`Get-Help .\<script>.ps1 -Detail` lists all of them.

| Script | Parameter | Default | Meaning |
| --- | --- | --- | --- |
| all | `-DryRun` / `-WhatIf` | off | Plan only |
| 01 | `-ToolsRoot` | `C:\ci-tools` | PHP, Composer, downloads; locked to administrators |
| 01 | `-PhpMinor` | `8.5` | PHP major.minor; newest patch release |
| 01 | `-PythonVersion` | none | Only when no Python 3.10+ exists (give its MD5 via `-ChecksumOverride`) |
| 01 | `-ChecksumOverride` | `@{}` | File name to checksum, for what cannot be looked up |
| 01 | `-Instances` / `-ServiceAccount` | `4` / `ci-runner` | Size the port check and Defender exclusions |
| 01 | `-Update` | off | Replace Git, PHP, Composer and gh with the newest release |
| 01 | `-SkipNetworkCheck`, `-SkipDefender` | off | Skip those checks |
| 02 | `-MysqlVersion`, `-MysqlChecksum` | none | `8.4.x` and its MD5/SHA256 from <https://dev.mysql.com/downloads/mysql/8.4.html> (required for a first install) |
| 02 | `-MysqlZipPath` | none | A ZIP you downloaded yourself (still checked) |
| 02 | `-Port` | `3307` | Private port; use the same in step 3 (`-MysqlPort`) |
| 02 | `-CiUser` | `warehouse_ci` | The account the jobs use |
| 02 | `-RootPassword` | none | `SecureString`; for a re-run that verifies or repairs accounts |
| 02 | `-RotateCiPassword` | off | New CI password (needs `-RootPassword`); then update step 3 |
| 02 | `-Update` | off | Move the service to the version in `-MysqlVersion` |
| 03 | `-Instances` | `4` | Instances 1..N; a larger number adds, a smaller one removes nothing |
| 03 | `-Token` | prompt | `SecureString`; registration token (removal token with `-Remove`) |
| 03 | `-RepoUrl` | `https://github.com/arb-rajab/warehouse_management` | Repository to register against |
| 03 | `-ServiceAccount` | `ci-runner` | Local non-administrator account; created if missing |
| 03 | `-PerInstanceAccounts` | off | One account per instance (`ci-runner-1`..): stronger isolation |
| 03 | `-ServicePassword` | generated | Password for a new account, or an existing one's |
| 03 | `-CiMysqlPassword` | prompt | `warehouse_ci` password; giving it again updates every `.env` |
| 03 | `-MysqlPort`, `-MysqlHost`, `-MysqlUser` | `3307`, `127.0.0.1`, `warehouse_ci` | Written into each `.env` |
| 03 | `-ExtraLabels` | `warehouse-vps` | Added to the automatic `self-hosted`, `Windows`, `X64` |
| 03 | `-NamePrefix` | `wh` | Folders `wh-1`.., runner names `<computer>-wh-1`.. |
| 03 | `-Remove 3,4` / `-LocalOnly` | off | Take instances out (needs a removal token, or `-LocalOnly`) |
| 04 | `-Mode` | `Install` | `Install` registers the task; `Clean` cleans now |
| 04 | `-Day`, `-At` | `Sunday`, `03:30` | When the weekly task runs |
| 04 | `-KeepNodeVersions`, `-KeepBrowserRevisions`, `-CacheMaxAgeDays`, `-NpmCacheMaxGb`, `-MinFreeGb` | `2`, `2`, `30`, `5`, `20` | Cleanup thresholds (also stored in the task) |

## What ends up where

| Path | Holds |
| --- | --- |
| `C:\ci-tools\` | `php\`, `composer\`, `downloads\` (administrators write, users read) |
| `C:\mysql\mysql-8.4.x-winx64\`, `C:\mysql-data\` | MySQL program, data directory, `my.ini`, error log (data: administrators and NetworkService only) |
| `C:\actions-runners\wh-N\` | One runner instance each (SYSTEM, administrators, its service account only); `.env`, `hooks\job-completed.ps1`, `_work\` |
| `C:\actions-runners\_downloads\` | The runner zip |
| `C:\ProgramData\warehouse-ci\` | Installed maintenance script and its logs (`logs\maintenance-*.log`) |
| Machine `PATH` | `Git\cmd`, `Git\bin`, `C:\ci-tools\php`, `C:\ci-tools\composer` in front of System32; `Git\mingw64\bin` (for `openssl`) at the end |

Instance `n` (counting from 1) listens for E2E on `E2E_PORT=8010+2(n-1)` and
`E2E_BACKEND_PORT=8011+2(n-1)`: instance 1 uses 8010/8011, instance 4 uses
8016/8017 (`tests/e2e/support/ports.mjs`).

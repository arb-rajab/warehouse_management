#Requires -Version 5.1
<#
.SYNOPSIS
    Step 3 of 4: installs the GitHub Actions runner instances as auto-start Windows services.

.DESCRIPTION
    For each instance 1..N (default 4) this script:
      - creates a folder such as C:\actions-runners\wh-1 (no spaces, no %), locked to
        SYSTEM, Administrators and the service account;
      - extracts the official actions/runner (checked against the SHA256 the release publishes);
      - writes the instance's .env: its own E2E_PORT / E2E_BACKEND_PORT pair (instance n uses
        8010+2(n-1) and 8011+2(n-1)), CI_MYSQL_HOST / PORT / USER / PASSWORD, and the
        job-completed hook;
      - installs hooks\job-completed.ps1, which wipes that instance's job workspace after
        every job (clean workspace per job) but keeps _tool, _actions and _temp;
      - registers the runner (unique name <computer>-wh-n, labels self-hosted, Windows, X64 plus
        warehouse-vps) as a service that starts automatically, after MySQL, restarts on failure,
        and runs as a local non-administrator account.
    -Remove n[,m] takes instances out again.

    Needs the registration token that only a repository admin can create (repo Settings > Actions
    > Runners > New self-hosted runner; valid for one hour, usable for all instances). It is
    asked for as a secure prompt (or -Token), kept in memory, handed to config.cmd through the
    environment of that one child process, and never written to disk.

    The service account password is generated when the account is created and shown once.
    Passwords of existing accounts are asked for when an instance needs registering.

    Safe to re-run: configured instances are not touched except for what drifted (.env, hook,
    service settings). A changed .env restarts the service only while that instance is idle.
    -DryRun (alias -WhatIf) changes nothing. Stops at the first error. Not tested on Windows.

.PARAMETER Instances
    How many instances should exist (1..N). Re-running with a larger number adds instances; a
    smaller number does not remove any (use -Remove).
.PARAMETER Token
    Registration token (SecureString). Prompted for when needed. For -Remove, the removal token.
.PARAMETER ServiceAccount
    Local account that runs the services (created if missing; must not be an administrator).
.PARAMETER PerInstanceAccounts
    One account per instance (<ServiceAccount>-<n>) instead of one shared account. Stronger
    isolation: instances then cannot read each other's job files, .env or deploy key.
.PARAMETER ServicePassword
    Password for a new account (or the existing account's password); generated when omitted.
.PARAMETER CiMysqlPassword
    The CI_MYSQL_PASSWORD from step 2; prompted for when an instance has none yet. Giving it
    again updates every instance's .env.
.PARAMETER RunnerZipPath
    A runner zip you downloaded yourself; use -ChecksumOverride for its SHA256.
.PARAMETER Remove
    Instance numbers to remove, e.g. -Remove 3,4. Needs a removal token (repo Settings > Actions >
    Runners > the runner > Remove), or -LocalOnly.
.PARAMETER LocalOnly
    With -Remove: delete the service and folder without telling GitHub (the runner then shows as
    offline until you delete it under Settings > Actions > Runners).
.PARAMETER DryRun
    Print what would change; change nothing. Alias: -WhatIf.
#>
[CmdletBinding()]
param(
    [ValidateRange(1, 16)][int]$Instances = 4,
    [string]$RepoUrl = 'https://github.com/arb-rajab/warehouse_management',
    [securestring]$Token,
    [string]$RunnersRoot = 'C:\actions-runners',
    [string]$NamePrefix = 'wh',
    [string[]]$ExtraLabels = @('warehouse-vps'),
    [string]$ServiceAccount = 'ci-runner',
    [securestring]$ServicePassword,
    [switch]$PerInstanceAccounts,
    [securestring]$CiMysqlPassword,
    [string]$MysqlHost = '127.0.0.1',
    [ValidateRange(1024, 65535)][int]$MysqlPort = 3307,
    [string]$MysqlUser = 'warehouse_ci',
    [string]$MysqlServiceName = 'MySQL84',
    [switch]$SkipMysqlCheck,
    [int]$E2EBasePort = 8010,
    [string]$RunnerZipPath,
    [hashtable]$ChecksumOverride = @{},
    [int[]]$Remove,
    [switch]$LocalOnly,
    [Alias('WhatIf')][switch]$DryRun
)

#region shared helpers (the same block sits in every script of this folder; edit all four together)
Set-StrictMode -Version 1.0
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
}
catch {
    Write-Verbose 'Could not enable TLS 1.2 explicitly; relying on the system default.'
}

$script:Warnings = New-Object System.Collections.Generic.List[string]
$script:RebootNeeded = $false

function Write-Section {
    param([string]$Text)
    Write-Host ''
    Write-Host ('== ' + $Text) -ForegroundColor White
}
function Write-Ok { param([string]$Text) Write-Host ('  [ok]    ' + $Text) -ForegroundColor Green }
function Write-Do { param([string]$Text) Write-Host ('  [do]    ' + $Text) -ForegroundColor Cyan }
function Write-Plan { param([string]$Text) Write-Host ('  [plan]  ' + $Text) -ForegroundColor Yellow }
function Write-Warn {
    param([string]$Text)
    $script:Warnings.Add($Text)
    Write-Host ('  [warn]  ' + $Text) -ForegroundColor Yellow
}

# Every change goes through here: with -DryRun (alias -WhatIf) it only says what it would do.
# Read-only checks stay outside, so a dry run still reports the real current state.
# State set inside an action must use $script: variables (the block runs in a child scope).
function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Description,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )
    if ($DryRun) {
        Write-Plan $Description
        return
    }
    Write-Do $Description
    & $Action
}

# Runs a native program, stops on a non-zero exit code (PowerShell 5.1 does not), and
# never echoes the arguments, which may carry secrets. Returns ExitCode and Output.
function Invoke-Native {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [string[]]$Arguments = @(),
        [switch]$IgnoreExitCode
    )
    $saved = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = & $FilePath @Arguments 2>&1 | ForEach-Object { $_.ToString() }
        $code = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $saved
    }
    if ($null -eq $raw) { $lines = @() } else { $lines = @($raw) }
    if ((-not $IgnoreExitCode) -and ($code -ne 0)) {
        $tail = ($lines | Select-Object -Last 15) -join [Environment]::NewLine
        throw ('{0} exited with code {1}.{2}{3}' -f (Split-Path -Leaf $FilePath), $code, [Environment]::NewLine, $tail)
    }
    return [pscustomobject]@{ ExitCode = $code; Output = $lines }
}

function ConvertTo-PlainText {
    param([Parameter(Mandatory = $true)][securestring]$Secure)
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

# Cryptographically random, without modulo bias. The default alphabet has no look-alike characters.
function New-RandomSecret {
    param(
        [int]$Length = 32,
        [string]$Alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    )
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    try {
        $limit = 256 - (256 % $Alphabet.Length)
        $chars = New-Object System.Collections.Generic.List[char]
        $buffer = New-Object byte[] 1
        while ($chars.Count -lt $Length) {
            $rng.GetBytes($buffer)
            if ($buffer[0] -lt $limit) { $chars.Add($Alphabet[$buffer[0] % $Alphabet.Length]) }
        }
        return (-join $chars)
    }
    finally {
        $rng.Dispose()
    }
}

function Test-FileChecksum {
    param([string]$Path, [string]$Expected)
    $Expected = $Expected.Trim()
    if ($Expected.Length -eq 64) { $algorithm = 'SHA256' }
    elseif ($Expected.Length -eq 32) { $algorithm = 'MD5' }
    else { throw ('Unsupported checksum "{0}": need 64 hex characters (SHA256) or 32 (MD5).' -f $Expected) }
    return ((Get-FileHash -LiteralPath $Path -Algorithm $algorithm).Hash -ieq $Expected)
}

# Downloads only with a vendor-published checksum, verifies before the file gets its final name,
# and reuses an earlier download whose checksum still matches.
function Save-Verified {
    param([string]$Url, [string]$Destination, [string]$Checksum)
    if ([string]::IsNullOrWhiteSpace($Checksum)) {
        throw ('No published checksum for {0}; refusing to install an unverified download. Pass it with -ChecksumOverride or -MySqlChecksum.' -f $Url)
    }
    if (Test-Path -LiteralPath $Destination) {
        if (Test-FileChecksum -Path $Destination -Expected $Checksum) {
            Write-Ok ('Using the earlier download {0} (checksum verified).' -f $Destination)
            return
        }
        Remove-Item -LiteralPath $Destination -Force
    }
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Destination) | Out-Null
    $partial = $Destination + '.partial'
    Invoke-WebRequest -Uri $Url -OutFile $partial -UseBasicParsing -Headers @{ 'User-Agent' = 'warehouse-runner-setup' }
    if (-not (Test-FileChecksum -Path $partial -Expected $Checksum)) {
        Remove-Item -LiteralPath $partial -Force
        throw ('Checksum mismatch for {0}; the download was deleted.' -f $Url)
    }
    Move-Item -LiteralPath $partial -Destination $Destination -Force
    Write-Ok ('Downloaded and verified {0}.' -f (Split-Path -Leaf $Destination))
}

function Expand-Zip {
    param([string]$Zip, [string]$Destination, [switch]$IntoExisting)
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if ((-not $IntoExisting) -and (Test-Path -LiteralPath $Destination)) { throw ('{0} already exists; refusing to extract over it.' -f $Destination) }
    [IO.Compression.ZipFile]::ExtractToDirectory($Zip, $Destination)
}

# A published asset and its SHA256 from the release itself: the asset digest, else the release
# notes (a custom regex with one capture group, or "name ... hash" / "hash name"), else a
# *checksums*.txt asset. Returns Sha256 = $null when none says anything.
function Get-GitHubAsset {
    param([string]$Repo, [string]$NamePattern, [string]$BodyRegex)
    $headers = @{ 'User-Agent' = 'warehouse-runner-setup'; 'Accept' = 'application/vnd.github+json' }
    $release = Invoke-RestMethod -Uri ('https://api.github.com/repos/{0}/releases/latest' -f $Repo) -Headers $headers
    $asset = @($release.assets | Where-Object { $_.name -match $NamePattern }) | Select-Object -First 1
    if (-not $asset) { throw ('The latest {0} release ({1}) has no asset matching {2}.' -f $Repo, $release.tag_name, $NamePattern) }
    $sha = $null
    $digest = $asset.PSObject.Properties['digest']
    if ($digest -and ([string]$digest.Value -match '^sha256:([0-9a-fA-F]{64})$')) { $sha = $Matches[1] }
    $body = [string]$release.body
    if ((-not $sha) -and $BodyRegex -and ($body -match $BodyRegex)) { $sha = $Matches[1] }
    if (-not $sha) {
        $name = [regex]::Escape([string]$asset.name)
        if ($body -match ('(?i)' + $name + '[^0-9a-f]{0,40}([0-9a-f]{64})')) { $sha = $Matches[1] }
        elseif ($body -match ('(?i)([0-9a-f]{64})[ \t*|`]+' + $name)) { $sha = $Matches[1] }
    }
    if (-not $sha) {
        $sums = @($release.assets | Where-Object { $_.name -match '(?i)(checksums?|sha256sums?)[^/]*\.txt$' }) | Select-Object -First 1
        if ($sums) {
            $text = [string](Invoke-RestMethod -Uri $sums.browser_download_url -Headers $headers)
            $name = [regex]::Escape([string]$asset.name)
            if ($text -match ('(?im)^([0-9a-f]{64})\s+\*?' + $name + '\s*$')) { $sha = $Matches[1] }
        }
    }
    return [pscustomobject]@{ Tag = [string]$release.tag_name; Name = [string]$asset.name; Url = [string]$asset.browser_download_url; Sha256 = $sha }
}

function Update-ProcessPath {
    $parts = @([Environment]::GetEnvironmentVariable('Path', 'Machine'), [Environment]::GetEnvironmentVariable('Path', 'User'))
    $env:Path = ($parts | Where-Object { $_ }) -join ';'
}

# icacls with well-known SIDs (locale independent), inheritance removed, grants replaced.
# Example grant: '*S-1-5-32-545:(OI)(CI)RX' (Users: read and execute).
function Set-DirectoryAcl {
    param([string]$Path, [string[]]$Grants)
    $icaclsArguments = @($Path, '/inheritance:r')
    foreach ($grant in $Grants) { $icaclsArguments += @('/grant:r', $grant) }
    Invoke-Native -FilePath 'icacls.exe' -Arguments $icaclsArguments | Out-Null
}

# True once inheritance is off and every given SID has an access rule.
function Test-AclReady {
    param([string]$Path, [string[]]$RequiredSids)
    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected) { return $false }
    $present = @($acl.Access | ForEach-Object { try { $_.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value } catch { '' } })
    foreach ($sid in $RequiredSids) { if ($present -notcontains $sid) { return $false } }
    return $true
}

# Applies the ACL once (a re-run does not rewrite and re-propagate it over a big tree); -DryRun only says so.
function Confirm-DirectoryAcl {
    param([string]$Path, [string[]]$Grants, [string[]]$RequiredSids, [string]$What)
    if (-not (Test-Path -LiteralPath $Path)) { Write-Plan ('{0} ({1})' -f $What, $Path); return }
    if (Test-AclReady -Path $Path -RequiredSids $RequiredSids) { Write-Ok ('{0}: access already restricted.' -f $Path); return }
    if ($DryRun) { Write-Plan ('{0} ({1})' -f $What, $Path); return }
    Set-DirectoryAcl -Path $Path -Grants $Grants
    Write-Ok ('{0}: {1}' -f $Path, $What)
}

function Write-Utf8File {
    param([string]$Path, [string]$Text)
    [IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}

function Assert-PlainPath {
    param([string]$Path, [string]$What)
    if ($Path -match '[\s%]') { throw ('{0} ({1}) must not contain whitespace or %: ssh options and several tools cannot carry them.' -f $What, $Path) }
}

function Assert-Administrator {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run this from an elevated (Run as administrator) 64-bit PowerShell.' }
    if (-not [Environment]::Is64BitProcess) { throw 'Run this in 64-bit PowerShell, not the x86 shell.' }
}
#endregion shared helpers

# ---------------------------------------------------------------------------
# The job-completed hook, installed into every instance as hooks\job-completed.ps1.
# ASCII only: the runner starts it with Windows PowerShell 5.1.
# ---------------------------------------------------------------------------
$HookScript = @'
# Runs after every job on this runner instance (ACTIONS_RUNNER_HOOK_JOB_COMPLETED).
# Installed by .github/runner-setup/03-runners.ps1; edits are overwritten on its next run.
#
# Clean workspace per job: stops what the job left running (the Playwright web server holds the
# E2E ports), then deletes every job workspace folder under _work. It keeps _tool (Node and the
# Playwright browsers: expensive to download), _actions, _temp and the runner's own bookkeeping.
# It must never fail a job: problems go to _diag\job-completed-hook.log and the exit code is 0.
$ErrorActionPreference = 'Continue'
$instance = Split-Path -Parent $PSScriptRoot
$work = Join-Path $instance '_work'
$log = Join-Path $instance '_diag\job-completed-hook.log'
$keep = @('_tool', '_actions', '_temp', '_PipelineMapping', '_update', '_diag')

function Write-HookLog {
    param([string]$Message)
    try {
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $log) | Out-Null
        if ((Test-Path -LiteralPath $log) -and ((Get-Item -LiteralPath $log).Length -gt 1MB)) { Move-Item -LiteralPath $log -Destination ($log + '.old') -Force }
        Add-Content -LiteralPath $log -Value ('{0} {1}' -f (Get-Date -Format 's'), $Message)
    }
    catch { }
}

function Remove-Tree {
    param([string]$Path)
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        if (-not (Test-Path -LiteralPath $Path)) { return $true }
        # Mirroring an empty folder over it deletes read-only files and paths past 260 characters.
        $empty = Join-Path $env:TEMP ('empty-' + [guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $empty -Force | Out-Null
        & robocopy.exe $empty $Path /MIR /NFL /NDL /NJH /NJS /NP /NC /NS /R:1 /W:1 | Out-Null
        Remove-Item -LiteralPath $empty -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath $Path -Recurse -Force -ErrorAction SilentlyContinue
        if (Test-Path -LiteralPath $Path) { & cmd.exe /c rd /s /q $Path | Out-Null }
        if (-not (Test-Path -LiteralPath $Path)) { return $true }
        Start-Sleep -Seconds (2 * $attempt)
    }
    return (-not (Test-Path -LiteralPath $Path))
}

try {
    Set-Location -LiteralPath $instance

    # 1. Whatever still listens on this instance's E2E ports, and anything running out of _work.
    $ports = @()
    $envFile = Join-Path $instance '.env'
    if (Test-Path -LiteralPath $envFile) {
        foreach ($line in (Get-Content -LiteralPath $envFile)) {
            if ($line -match '^(E2E_PORT|E2E_BACKEND_PORT)=(\d+)\s*$') { $ports += [int]$Matches[2] }
        }
    }
    $victims = @{}
    foreach ($port in $ports) {
        foreach ($connection in @(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue)) { $victims[[int]$connection.OwningProcess] = ('port ' + $port) }
    }
    $prefix = $work.TrimEnd('\') + '\'
    foreach ($process in @(Get-CimInstance Win32_Process -ErrorAction SilentlyContinue)) {
        $inWork = $false
        if ($process.ExecutablePath -and $process.ExecutablePath.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) { $inWork = $true }
        if ($process.CommandLine -and ($process.CommandLine.IndexOf($prefix, [StringComparison]::OrdinalIgnoreCase) -ge 0)) { $inWork = $true }
        if ($inWork) { $victims[[int]$process.ProcessId] = ('runs from _work: ' + $process.Name) }
    }
    foreach ($id in $victims.Keys) {
        if (($id -eq $PID) -or ($id -le 4)) { continue }
        try { Stop-Process -Id $id -Force -ErrorAction Stop; Write-HookLog ('stopped process {0} ({1})' -f $id, $victims[$id]) }
        catch { Write-HookLog ('could not stop process {0} ({1}): {2}' -f $id, $victims[$id], $_.Exception.Message) }
    }
    if ($victims.Count -gt 0) { Start-Sleep -Seconds 2 }

    # 2. The job workspaces.
    $removed = 0
    $failed = 0
    if (Test-Path -LiteralPath $work) {
        foreach ($item in @(Get-ChildItem -LiteralPath $work -Force | Where-Object { $_.PSIsContainer -and ($keep -notcontains $_.Name) })) {
            if (Remove-Tree -Path $item.FullName) { $removed++ } else { $failed++; Write-HookLog ('could not delete ' + $item.FullName) }
        }
    }
    Write-HookLog ('job workspace wiped: {0} folder(s) removed, {1} left' -f $removed, $failed)
}
catch {
    Write-HookLog ('hook error: ' + $_.Exception.Message)
}
exit 0
'@

function Get-AccountName {
    param([int]$Number)
    if ($PerInstanceAccounts) { return ('{0}-{1}' -f $ServiceAccount, $Number) }
    return $ServiceAccount
}

function Read-EnvFile {
    param([string]$Path)
    if (Test-Path -LiteralPath $Path) { return @(Get-Content -LiteralPath $Path) }
    return @()
}

function Get-EnvValue {
    param([string[]]$Lines, [string]$Key)
    foreach ($line in $Lines) { if ($line -match ('^' + [regex]::Escape($Key) + '=(.*)$')) { return $Matches[1] } }
    return $null
}

# Sets managed keys in place, keeps every other line, appends missing keys.
function Merge-EnvLines {
    param([string[]]$Lines, [System.Collections.Specialized.OrderedDictionary]$Managed)
    $result = New-Object System.Collections.Generic.List[string]
    $seen = @{}
    foreach ($line in $Lines) {
        $replaced = $false
        foreach ($key in $Managed.Keys) {
            if ($line -match ('^' + [regex]::Escape($key) + '=')) { $result.Add(('{0}={1}' -f $key, $Managed[$key])); $seen[$key] = $true; $replaced = $true; break }
        }
        if (-not $replaced) { $result.Add($line) }
    }
    foreach ($key in $Managed.Keys) { if (-not $seen.ContainsKey($key)) { $result.Add(('{0}={1}' -f $key, $Managed[$key])) } }
    return @($result)
}

function Get-RunnerServiceName {
    param([string]$Directory)
    $file = Join-Path $Directory '.service'
    if (-not (Test-Path -LiteralPath $file)) { return $null }
    return ([IO.File]::ReadAllText($file)).Trim()
}

function Test-RunnerBusy {
    param([string]$Directory)
    $prefix = $Directory.TrimEnd('\') + '\'
    $workers = @(Get-CimInstance Win32_Process -Filter "Name='Runner.Worker.exe'" -ErrorAction SilentlyContinue | Where-Object { $_.ExecutablePath -and $_.ExecutablePath.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) })
    return ($workers.Count -gt 0)
}

function Test-AnyRunnerBusy {
    $workers = @(Get-CimInstance Win32_Process -Filter "Name='Runner.Worker.exe'" -ErrorAction SilentlyContinue)
    return ($workers.Count -gt 0)
}

function Remove-Tree {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) { return }
    if ($Path.TrimEnd('\').Split('\').Count -lt 3) { throw ('Refusing to delete {0}: too close to a drive root.' -f $Path) }
    $empty = Join-Path $env:TEMP ('empty-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $empty -Force | Out-Null
    Invoke-Native -FilePath 'robocopy.exe' -Arguments @($empty, $Path, '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1') -IgnoreExitCode | Out-Null
    Remove-Item -LiteralPath $empty -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $Path -Recurse -Force
}

function Wait-RunnerListening {
    param([string]$Directory, [int]$TimeoutSeconds = 90)
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $diag = Join-Path $Directory '_diag'
        if (Test-Path -LiteralPath $diag) {
            $logs = @(Get-ChildItem -LiteralPath $diag -Filter 'Runner_*.log' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1)
            if (($logs.Count -gt 0) -and (Select-String -LiteralPath $logs[0].FullName -Pattern 'Listening for Jobs' -SimpleMatch -Quiet)) { return $true }
        }
        Start-Sleep -Seconds 3
    }
    return $false
}

function Test-ComplexPassword {
    param([string]$Text)
    return (($Text -cmatch '[a-z]') -and ($Text -cmatch '[A-Z]') -and ($Text -match '[0-9]') -and ($Text -match '[-_.+=@#]'))
}

function Test-MysqlLogin {
    param([string]$Password)
    $code = 'try { new PDO(''mysql:host='' . getenv(''CI_MYSQL_HOST'') . '';port='' . getenv(''CI_MYSQL_PORT''), getenv(''CI_MYSQL_USER''), getenv(''CI_MYSQL_PASSWORD''), [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo ''OK''; } catch (Throwable $e) { echo ''FAIL '' . $e->getMessage(); }'
    $names = @('CI_MYSQL_HOST', 'CI_MYSQL_PORT', 'CI_MYSQL_USER', 'CI_MYSQL_PASSWORD')
    try {
        Set-Item Env:\CI_MYSQL_HOST $MysqlHost
        Set-Item Env:\CI_MYSQL_PORT ([string]$MysqlPort)
        Set-Item Env:\CI_MYSQL_USER $MysqlUser
        Set-Item Env:\CI_MYSQL_PASSWORD $Password
        $result = Invoke-Native -FilePath 'php' -Arguments @('-r', $code) -IgnoreExitCode
    }
    finally { foreach ($name in $names) { Remove-Item ('Env:\' + $name) -ErrorAction SilentlyContinue } }
    return (($result.Output -join '') -eq 'OK')
}

Assert-Administrator
Update-ProcessPath
if ($DryRun) { Write-Host 'DRY RUN: nothing will be changed; [plan] lines show what a real run would do.' -ForegroundColor Yellow }
Assert-PlainPath -Path $RunnersRoot -What 'RunnersRoot'
$ComputerName = $env:COMPUTERNAME.ToLowerInvariant()
$DownloadDir = Join-Path $RunnersRoot '_downloads'
$AdminAcl = @('*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F')
$script:CreatedAccounts = @{}
$script:AccountPasswords = @{}
$script:ZipPath = $null
$script:Summary = New-Object System.Collections.Generic.List[string]

try {
    # ================================================================ remove mode
    if ($Remove) {
        Write-Section ('Remove instance(s) {0}' -f ($Remove -join ', '))
        $removalToken = ''
        if ((-not $LocalOnly) -and (-not $DryRun)) {
            if (-not $Token) { $Token = Read-Host -Prompt 'Removal token (repo Settings > Actions > Runners > the runner > Remove)' -AsSecureString }
            $removalToken = ConvertTo-PlainText $Token
        }
        foreach ($n in $Remove) {
            $dir = Join-Path $RunnersRoot ('{0}-{1}' -f $NamePrefix, $n)
            if (-not (Test-Path -LiteralPath $dir)) { Write-Ok ('{0} does not exist; nothing to remove.' -f $dir); continue }
            if (Test-RunnerBusy -Directory $dir) { throw ('Instance {0} is running a job; remove it when idle.' -f $n) }
            $serviceName = Get-RunnerServiceName -Directory $dir
            Invoke-Step ('Stop and remove instance {0} ({1}){2}' -f $n, $dir, $(if ($LocalOnly) { ' without telling GitHub' } else { ' and unregister it from GitHub' })) {
                if ($serviceName -and (Get-Service -Name $serviceName -ErrorAction SilentlyContinue)) { Stop-Service -Name $serviceName -Force }
                if (-not $LocalOnly) {
                    $env:ACTIONS_RUNNER_INPUT_TOKEN = $removalToken
                    Push-Location -LiteralPath $dir
                    try { Invoke-Native -FilePath (Join-Path $dir 'config.cmd') -Arguments @('remove', '--unattended') | Out-Null }
                    finally { Pop-Location; Remove-Item Env:\ACTIONS_RUNNER_INPUT_TOKEN -ErrorAction SilentlyContinue }
                }
                if ($serviceName -and (Get-Service -Name $serviceName -ErrorAction SilentlyContinue)) { Invoke-Native -FilePath 'sc.exe' -Arguments @('delete', $serviceName) | Out-Null }
                Remove-Tree -Path $dir
            }
            if ($LocalOnly) { Write-Warn ('Instance {0} still appears (offline) under the repository''s Settings > Actions > Runners; delete it there.' -f $n) }
        }
        Write-Host 'The local account(s) were left in place; remove them with Remove-LocalUser when no instance uses them.'
        return
    }

    # ================================================================ preflight
    Write-Section 'Preflight'
    $firstBash = $null
    foreach ($entry in @([Environment]::GetEnvironmentVariable('Path', 'Machine').Split(';') | Where-Object { $_ })) {
        $candidate = Join-Path ([Environment]::ExpandEnvironmentVariables($entry)) 'bash.exe'
        if (Test-Path -LiteralPath $candidate) { $firstBash = $candidate; break }
    }
    if (-not $firstBash) { throw 'No bash.exe on the machine PATH: run 01-prerequisites.ps1 (jobs use `shell: bash`).' }
    if ($firstBash -notlike '*\Git\bin\bash.exe') { throw ('The first bash.exe on the machine PATH is {0}, not Git for Windows''. Remove WSL''s bash or fix the PATH order (01-prerequisites.ps1 does it).' -f $firstBash) }
    foreach ($tool in @('git', 'php', 'composer', 'gh')) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { throw ('{0} is not on PATH in this shell. Run 01-prerequisites.ps1, then open a NEW elevated PowerShell (or reboot) and re-run this.' -f $tool) }
    }
    $pythonOk = $false
    foreach ($candidate in @(@('python3', @()), @('python', @()), @('py', @('-3')))) {
        $found = Get-Command $candidate[0] -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($found) {
            $probe = Invoke-Native -FilePath $found.Source -Arguments ($candidate[1] + @('-c', 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)')) -IgnoreExitCode
            if ($probe.ExitCode -eq 0) { $pythonOk = $true; break }
        }
    }
    if (-not $pythonOk) { throw 'No working Python 3.10+ (python3, python or py). Run 01-prerequisites.ps1 (and turn off the Store app execution aliases).' }
    Write-Ok 'Git Bash first on PATH; git, php, composer, gh and Python 3.10+ resolve.'

    $mysqlService = Get-Service -Name $MysqlServiceName -ErrorAction SilentlyContinue
    if ($SkipMysqlCheck) { Write-Ok 'MySQL check skipped (-SkipMysqlCheck).' }
    elseif (-not $mysqlService) { throw ('Service {0} does not exist: run 02-mysql.ps1 first (or -SkipMysqlCheck / -MysqlServiceName).' -f $MysqlServiceName) }
    elseif ($mysqlService.Status -ne 'Running') { throw ('Service {0} is {1}; start it first.' -f $MysqlServiceName, $mysqlService.Status) }
    else { Write-Ok ('Service {0} is running.' -f $MysqlServiceName) }

    # What each instance will be.
    $plan = @()
    for ($n = 1; $n -le $Instances; $n++) {
        $plan += [pscustomobject]@{
            Number  = $n
            Dir     = (Join-Path $RunnersRoot ('{0}-{1}' -f $NamePrefix, $n))
            Name    = ('{0}-{1}-{2}' -f $ComputerName, $NamePrefix, $n)
            Account = (Get-AccountName -Number $n)
            E2E     = ($E2EBasePort + 2 * ($n - 1))
        }
    }
    foreach ($item in $plan) {
        if ($item.Name.Length -gt 64) { throw ('Runner name {0} is longer than 64 characters; use a shorter -NamePrefix.' -f $item.Name) }
        if ($item.Account.Length -gt 20) { throw ('Account name {0} is longer than the 20 characters Windows allows; use a shorter -ServiceAccount.' -f $item.Account) }
        Assert-PlainPath -Path $item.Dir -What 'Runner folder'
    }
    $needRegistration = @($plan | Where-Object { -not (Test-Path -LiteralPath (Join-Path $_.Dir '.runner')) })

    # Ports the E2E pairs need.
    foreach ($item in $plan) {
        foreach ($port in @($item.E2E, ($item.E2E + 1))) {
            $holder = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($holder) { Write-Warn ('Port {0} (instance {1}) is listening now (process {2}); the E2E job fails while it stays taken.' -f $port, $item.Number, $holder.OwningProcess) }
        }
    }

    # ================================================================ secrets
    Write-Section 'Secrets and accounts'
    $ciPlain = ''
    if ($CiMysqlPassword) { $ciPlain = ConvertTo-PlainText $CiMysqlPassword }
    $lacking = @($plan | Where-Object { -not (Get-EnvValue -Lines (Read-EnvFile -Path (Join-Path $_.Dir '.env')) -Key 'CI_MYSQL_PASSWORD') })
    if (($ciPlain -eq '') -and ($lacking.Count -gt 0)) {
        if ($DryRun) { Write-Plan ('Ask for the {0} password from step 2 (needed for {1} instance(s))' -f $MysqlUser, $lacking.Count) }
        else {
            $ciPlain = ConvertTo-PlainText (Read-Host -Prompt ('Password of MySQL account {0} (from step 2)' -f $MysqlUser) -AsSecureString)
            if ($ciPlain -eq '') { throw 'An empty MySQL password cannot be written into the .env files.' }
        }
    }
    if ($ciPlain -ne '') {
        if ($SkipMysqlCheck -or $DryRun) { Write-Ok 'MySQL login not tested (dry run or -SkipMysqlCheck).' }
        elseif (Test-MysqlLogin -Password $ciPlain) { Write-Ok ('{0} can log in to {1}:{2}.' -f $MysqlUser, $MysqlHost, $MysqlPort) }
        else { throw ('{0} cannot log in to {1}:{2} with that password; not writing it into any .env.' -f $MysqlUser, $MysqlHost, $MysqlPort) }
    }

    if (($needRegistration.Count -gt 0) -and (-not $Token) -and (-not $DryRun)) {
        $Token = Read-Host -Prompt ('Registration token (repo Settings > Actions > Runners > New self-hosted runner; valid 1 hour; needed for {0} instance(s))' -f $needRegistration.Count) -AsSecureString
    }
    elseif ($needRegistration.Count -gt 0 -and $DryRun) { Write-Plan ('Ask for a registration token ({0} instance(s) to register)' -f $needRegistration.Count) }

    $accountPasswords = @{}
    foreach ($accountName in @($plan | ForEach-Object { $_.Account } | Select-Object -Unique)) {
        $user = Get-LocalUser -Name $accountName -ErrorAction SilentlyContinue
        if (-not $user) {
            Invoke-Step ('Create the local account {0}: not an administrator, password never expires, cannot log on over RDP unless you add it to Remote Desktop Users' -f $accountName) {
                if ($ServicePassword) { $plain = ConvertTo-PlainText $ServicePassword }
                else {
                    do { $plain = New-RandomSecret -Length 28 -Alphabet 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_.+=@#' } until (Test-ComplexPassword $plain)
                    $script:CreatedAccounts[$accountName] = $plain
                }
                New-LocalUser -Name $accountName -Password (ConvertTo-SecureString -String $plain -AsPlainText -Force) -PasswordNeverExpires -UserMayNotChangePassword -AccountNeverExpires -Description 'Runs the warehouse CI runner service' | Out-Null
                try { Add-LocalGroupMember -SID 'S-1-5-32-545' -Member $accountName -ErrorAction Stop }
                catch { Write-Verbose ('Users membership: ' + $_.Exception.Message) }
                $script:AccountPasswords[$accountName] = $plain
            }
            if (-not $DryRun) { $accountPasswords[$accountName] = $script:AccountPasswords[$accountName] }
        }
        else {
            $isAdmin = @(Get-LocalGroupMember -SID 'S-1-5-32-544' -ErrorAction SilentlyContinue | Where-Object { $_.Name -like ('*\' + $accountName) })
            if ($isAdmin.Count -gt 0) { throw ('{0} is a member of Administrators; the runner account must not be.' -f $accountName) }
            Write-Ok ('Account {0} exists and is not an administrator.' -f $accountName)
            $needsPassword = @($needRegistration | Where-Object { $_.Account -eq $accountName }).Count -gt 0
            if ($needsPassword) {
                if ($ServicePassword) { $accountPasswords[$accountName] = ConvertTo-PlainText $ServicePassword }
                elseif (-not $DryRun) { $accountPasswords[$accountName] = ConvertTo-PlainText (Read-Host -Prompt ('Password of the existing local account {0}' -f $accountName) -AsSecureString) }
            }
        }
    }

    # ================================================================ runner package
    Write-Section 'Runner package'
    if (-not (Test-Path -LiteralPath $RunnersRoot)) { Invoke-Step ('Create {0}' -f $RunnersRoot) { New-Item -ItemType Directory -Force -Path $RunnersRoot | Out-Null } }
    Confirm-DirectoryAcl -Path $RunnersRoot -Grants ($AdminAcl + @('*S-1-5-32-545:(OI)(CI)RX')) -RequiredSids @('S-1-5-32-545') -What 'restrict to administrators (write) and users (read and execute)'
    $needZip = @($plan | Where-Object { -not (Test-Path -LiteralPath (Join-Path $_.Dir 'config.cmd')) }).Count -gt 0
    if (-not $needZip) { Write-Ok 'Every instance folder already holds the runner.' }
    elseif ($RunnerZipPath) {
        if (-not (Test-Path -LiteralPath $RunnerZipPath)) { throw ('{0} does not exist.' -f $RunnerZipPath) }
        $leaf = Split-Path -Leaf $RunnerZipPath
        if (-not $ChecksumOverride.ContainsKey($leaf)) { throw ('Give the SHA256 of {0} with -ChecksumOverride @{{ ''{0}'' = ''<sha256>'' }}.' -f $leaf) }
        if (-not (Test-FileChecksum -Path $RunnerZipPath -Expected ([string]$ChecksumOverride[$leaf]))) { throw ('{0} does not match the checksum you gave.' -f $RunnerZipPath) }
        $script:ZipPath = $RunnerZipPath
        Write-Ok ('Using {0} (checksum verified).' -f $RunnerZipPath)
    }
    else {
        Invoke-Step ('Download the newest actions/runner win-x64 zip (SHA256 from the release notes) into {0}' -f $DownloadDir) {
            $asset = Get-GitHubAsset -Repo 'actions/runner' -NamePattern '^actions-runner-win-x64-[\d.]+\.zip$' -BodyRegex '<!-- BEGIN SHA win-x64 -->([0-9a-fA-F]{64})<!-- END SHA win-x64 -->'
            $found = $asset.Sha256
            if ($ChecksumOverride.ContainsKey($asset.Name)) { $found = [string]$ChecksumOverride[$asset.Name] }
            $script:ZipPath = Join-Path $DownloadDir $asset.Name
            Save-Verified -Url $asset.Url -Destination $script:ZipPath -Checksum $found
        }
    }

    # ================================================================ instances
    $labelText = (@($ExtraLabels) | Where-Object { $_ -and (@('self-hosted', 'windows', 'x64') -notcontains $_.ToLowerInvariant()) }) -join ','
    foreach ($item in $plan) {
        $dir = $item.Dir
        $account = $item.Account
        $qualified = ('{0}\{1}' -f $env:COMPUTERNAME, $account)
        Write-Section ('Instance {0}: {1} ({2})' -f $item.Number, $item.Name, $dir)

        if (-not (Test-Path -LiteralPath $dir)) { Invoke-Step ('Create {0}' -f $dir) { New-Item -ItemType Directory -Force -Path $dir | Out-Null } }
        $accountUser = Get-LocalUser -Name $account -ErrorAction SilentlyContinue
        if ($accountUser) {
            Confirm-DirectoryAcl -Path $dir -Grants ($AdminAcl + @(('{0}:(OI)(CI)M' -f $qualified))) -RequiredSids @($accountUser.SID.Value) -What ('restrict to SYSTEM, Administrators and ' + $account)
        }
        else { Write-Plan ('Restrict {0} to SYSTEM, Administrators and {1} once the account exists' -f $dir, $account) }

        if (-not (Test-Path -LiteralPath (Join-Path $dir 'config.cmd'))) {
            Invoke-Step ('Extract the runner into {0}' -f $dir) { Expand-Zip -Zip $script:ZipPath -Destination $dir -IntoExisting }
        }
        else { Write-Ok 'The runner is extracted.' }

        # ---- hook and .env (before registration: the service reads .env when it starts)
        $hookDir = Join-Path $dir 'hooks'
        $hookPath = Join-Path $hookDir 'job-completed.ps1'
        $currentHook = ''
        if (Test-Path -LiteralPath $hookPath) { $currentHook = [IO.File]::ReadAllText($hookPath) }
        if ($currentHook -eq $HookScript) { Write-Ok 'The job-completed hook is current.' }
        else { Invoke-Step ('Write {0} (wipes the job workspace after every job)' -f $hookPath) { New-Item -ItemType Directory -Force -Path $hookDir | Out-Null; Write-Utf8File -Path $hookPath -Text $HookScript } }

        $envFile = Join-Path $dir '.env'
        $existing = Read-EnvFile -Path $envFile
        $password = $ciPlain
        if ($password -eq '') { $password = [string](Get-EnvValue -Lines $existing -Key 'CI_MYSQL_PASSWORD') }
        $managed = [ordered]@{
            'E2E_PORT'                           = [string]$item.E2E
            'E2E_BACKEND_PORT'                   = [string]($item.E2E + 1)
            'CI_MYSQL_HOST'                      = $MysqlHost
            'CI_MYSQL_PORT'                      = [string]$MysqlPort
            'CI_MYSQL_USER'                      = $MysqlUser
            'CI_MYSQL_PASSWORD'                  = $password
            'ACTIONS_RUNNER_HOOK_JOB_COMPLETED'  = $hookPath
        }
        $merged = Merge-EnvLines -Lines $existing -Managed $managed
        $envChanged = ((($merged -join "`n")) -ne (($existing -join "`n")))
        if (-not $envChanged) { Write-Ok ('.env already holds E2E_PORT={0}, E2E_BACKEND_PORT={1}, CI_MYSQL_* and the hook.' -f $item.E2E, ($item.E2E + 1)) }
        else {
            Invoke-Step ('Write {0}: E2E_PORT={1}, E2E_BACKEND_PORT={2}, CI_MYSQL_HOST/PORT/USER/PASSWORD (password not shown), ACTIONS_RUNNER_HOOK_JOB_COMPLETED' -f $envFile, $item.E2E, ($item.E2E + 1)) {
                Write-Utf8File -Path $envFile -Text (($merged -join "`n") + "`n")
            }
        }

        # ---- registration
        $justRegistered = $false
        if (Test-Path -LiteralPath (Join-Path $dir '.runner')) { Write-Ok 'Already registered with GitHub.' }
        else {
            $registerLabels = $labelText
            Invoke-Step ('Register {0} with {1} (labels: self-hosted, Windows, X64{2}), as a service running as {3}' -f $item.Name, $RepoUrl, $(if ($registerLabels) { ', ' + $registerLabels } else { '' }), $account) {
                if (-not $script:ZipPath -and -not (Test-Path -LiteralPath (Join-Path $dir 'config.cmd'))) { throw 'No runner package available.' }
                if (-not $Token) { throw 'A registration token is needed (-Token or the prompt).' }
                if (-not $accountPasswords.ContainsKey($account)) { throw ('No password known for {0}; pass -ServicePassword.' -f $account) }
                $configArguments = @('--unattended', '--url', $RepoUrl, '--name', $item.Name, '--work', '_work', '--replace', '--runasservice', '--windowslogonaccount', $qualified)
                if ($registerLabels) { $configArguments += @('--labels', $registerLabels) }
                # Token and password go through the child's environment, not its command line.
                $env:ACTIONS_RUNNER_INPUT_TOKEN = (ConvertTo-PlainText $Token)
                $env:ACTIONS_RUNNER_INPUT_WINDOWSLOGONPASSWORD = $accountPasswords[$account]
                Push-Location -LiteralPath $dir
                try { Invoke-Native -FilePath (Join-Path $dir 'config.cmd') -Arguments $configArguments | Out-Null }
                finally {
                    Pop-Location
                    Remove-Item Env:\ACTIONS_RUNNER_INPUT_TOKEN -ErrorAction SilentlyContinue
                    Remove-Item Env:\ACTIONS_RUNNER_INPUT_WINDOWSLOGONPASSWORD -ErrorAction SilentlyContinue
                }
            }
            $justRegistered = (-not $DryRun)
        }

        # ---- service settings
        $serviceName = Get-RunnerServiceName -Directory $dir
        if (-not $serviceName) {
            if ($DryRun) { Write-Plan 'After registration: order the service after MySQL, restart on failure, start it, wait for "Listening for Jobs".' }
            else { throw ('{0}\.service is missing: config.cmd did not install the service. Run it by hand in that folder to see why.' -f $dir) }
        }
        else {
            $qc = (Invoke-Native -FilePath 'sc.exe' -Arguments @('qc', $serviceName) -IgnoreExitCode).Output -join ' '
            if ($qc -notmatch 'AUTO_START') { Invoke-Step ('sc config {0}: automatic start' -f $serviceName) { Invoke-Native -FilePath 'sc.exe' -Arguments @('config', $serviceName, 'start=', 'auto') | Out-Null } }
            else { Write-Ok ('Service {0} starts automatically.' -f $serviceName) }
            if ((-not $SkipMysqlCheck) -and ($qc -notmatch [regex]::Escape($MysqlServiceName))) {
                Invoke-Step ('sc config {0}: start after {1}' -f $serviceName, $MysqlServiceName) { Invoke-Native -FilePath 'sc.exe' -Arguments @('config', $serviceName, 'depend=', $MysqlServiceName) | Out-Null }
            }
            elseif (-not $SkipMysqlCheck) { Write-Ok ('Service {0} starts after {1}.' -f $serviceName, $MysqlServiceName) }
            if (-not $DryRun) { Invoke-Native -FilePath 'sc.exe' -Arguments @('failure', $serviceName, 'reset=', '86400', 'actions=', 'restart/60000/restart/60000/restart/60000') | Out-Null; Write-Ok 'Recovery: restart after 1 minute on failure.' }

            $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
            if ($service -and ($service.Status -ne 'Running')) { Invoke-Step ('Start {0}' -f $serviceName) { Start-Service -Name $serviceName } }
            elseif ($service -and $envChanged -and (-not $justRegistered)) {
                if (Test-RunnerBusy -Directory $dir) { Write-Warn ('Instance {0} is running a job: its .env changed but the service was not restarted. Restart {1} when it is idle.' -f $item.Number, $serviceName) }
                else { Invoke-Step ('Restart {0} so the changed .env takes effect (the instance is idle)' -f $serviceName) { Restart-Service -Name $serviceName -Force } }
            }
            elseif ($service) { Write-Ok ('Service {0} is running.' -f $serviceName) }

            if ((-not $DryRun) -and ((Get-Service -Name $serviceName).Status -eq 'Running')) {
                if (Wait-RunnerListening -Directory $dir) { Write-Ok 'The runner log says "Listening for Jobs".' }
                else { Write-Warn ('Instance {0}: no "Listening for Jobs" in {1}\_diag within 90 s; check the log and the runner page on GitHub.' -f $item.Number, $dir) }
            }
        }

        $pathFile = Join-Path $dir '.path'
        if (Test-Path -LiteralPath $pathFile) {
            $pathText = [IO.File]::ReadAllText($pathFile)
            if (($pathText -notmatch '(?i)\\Git\\bin') -or ($pathText -notmatch '(?i)php')) { Write-Warn ('{0} does not list Git\bin and PHP: it was captured from a shell with a stale PATH. Delete it (the runner re-creates it) after fixing the PATH, then restart {1}.' -f $pathFile, $serviceName) }
            else { Write-Ok '.path lists Git Bash and PHP.' }
        }
        $script:Summary.Add(('{0,-3} {1,-26} {2,-30} E2E {3}/{4}  {5}' -f $item.Number, $item.Name, $dir, $item.E2E, ($item.E2E + 1), $account))
    }

    # ================================================================ summary
    Write-Section 'Summary'
    Write-Host ('#   runner                     folder                         ports        account')
    $script:Summary | ForEach-Object { Write-Host ('  ' + $_) }
    if ($script:Warnings.Count -gt 0) { Write-Host ''; Write-Host ('{0} warning(s):' -f $script:Warnings.Count) -ForegroundColor Yellow; $script:Warnings | ForEach-Object { Write-Host ('  - ' + $_) -ForegroundColor Yellow } }
    if ($DryRun) { Write-Host 'Dry run finished; nothing was changed.' -ForegroundColor Yellow }
    if ($script:CreatedAccounts.Count -gt 0) {
        Write-Host ''
        Write-Host '=========== SAVE THESE NOW: shown once, stored nowhere ===========' -ForegroundColor Red
        foreach ($key in $script:CreatedAccounts.Keys) { Write-Host ('Local account {0} password: {1}' -f $key, $script:CreatedAccounts[$key]) }
        Write-Host '===================================================================' -ForegroundColor Red
        Write-Host 'Needed again when you add instances or re-register one. Clear this console (cls) and any scrollback or recording that kept it.'
    }
    if (-not $DryRun) {
        Write-Host ''
        Write-Host 'Next: confirm every runner shows Idle under the repository Settings > Actions > Runners, then run 04-maintenance.ps1. The first pull request run on these runners is the real Windows test (docs/self-hosted-runner.md).' -ForegroundColor Green
    }
}
catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    Write-Host 'Stopped at the first error; nothing after it ran. Fix the cause and re-run: finished steps are skipped.' -ForegroundColor Red
    exit 1
}

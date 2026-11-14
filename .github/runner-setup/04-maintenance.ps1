#Requires -Version 5.1
<#
.SYNOPSIS
    Step 4 of 4: the weekly disk-cleanup task for the runner VPS (and the cleanup itself).

.DESCRIPTION
    -Mode Install (default) copies this script to C:\ProgramData\warehouse-ci (administrator-only,
    because the task runs as SYSTEM) and registers the scheduled task WarehouseCI-Maintenance,
    weekly (Sunday 03:30 by default). Run it on demand with:
        Start-ScheduledTask -TaskName WarehouseCI-Maintenance
    or directly: powershell -File <this script> -Mode Clean [-DryRun]

    -Mode Clean removes, and logs (C:\ProgramData\warehouse-ci\logs\maintenance-*.log):
      - per idle runner instance: leftover job workspaces under _work, old _temp and _diag files,
        _actions folders not used for -ActionsMaxAgeDays, Node versions beyond the newest
        -KeepNodeVersions, Playwright browser revisions beyond the newest -KeepBrowserRevisions
        (instances with a running job are skipped);
      - while no job runs anywhere: Composer and pip cache files older than -CacheMaxAgeDays and the
        npm cache when it is over -NpmCacheMaxGb, and old temp files, in the runner accounts' profiles;
      - old whci_ MySQL databases: every one while no job runs, else those whose newest table is older
        than -DatabaseMaxAgeHours;
      - old installer downloads and php.old-* folders under C:\ci-tools.
    It warns (and exits 2, which the task shows as its last result) when free space stays below
    -MinFreeGb. It never touches the runners' own bin and externals folders, the registration or the
    tool folders in use.

    Safe to re-run; -DryRun (alias -WhatIf) lists what Clean would remove and what Install would
    change, and changes nothing. Not tested on Windows.

.PARAMETER Mode
    Install (register the task) or Clean (do the cleanup now).
.PARAMETER DryRun
    Print what would change; change nothing. Alias: -WhatIf.
#>
[CmdletBinding()]
param(
    [ValidateSet('Install', 'Clean')][string]$Mode = 'Install',
    [string]$RunnersRoot = 'C:\actions-runners',
    [string]$ToolsRoot = 'C:\ci-tools',
    [string]$InstallDir = 'C:\ProgramData\warehouse-ci',
    [string]$TaskName = 'WarehouseCI-Maintenance',
    [DayOfWeek]$Day = 'Sunday',
    [ValidatePattern('^\d{2}:\d{2}$')][string]$At = '03:30',
    [int]$CacheMaxAgeDays = 30,
    [int]$ActionsMaxAgeDays = 30,
    [int]$KeepNodeVersions = 2,
    [int]$KeepBrowserRevisions = 2,
    [int]$NpmCacheMaxGb = 5,
    [int]$DatabaseMaxAgeHours = 6,
    [int]$MinFreeGb = 20,
    [int]$KeepLogs = 12,
    [string[]]$ProfileRoots,
    [switch]$SkipDatabases,
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

$InstalledScript = Join-Path $InstallDir '04-maintenance.ps1'
$LogDir = Join-Path $InstallDir 'logs'
$KeepInWork = @('_tool', '_actions', '_temp', '_PipelineMapping', '_update', '_diag')
$script:FreedBytes = [int64]0
$script:LogFile = $null
$script:ExitCode = 0

function Write-Log {
    param([string]$Text, [string]$Level = 'INFO')
    $line = '{0} [{1}] {2}' -f (Get-Date -Format 's'), $Level, $Text
    if ($Level -eq 'WARN') { Write-Host $line -ForegroundColor Yellow } else { Write-Host $line }
    if ($script:LogFile) { Add-Content -LiteralPath $script:LogFile -Value $line }
}

function Get-DirSize {
    param([string]$Path)
    $sum = (Get-ChildItem -LiteralPath $Path -Recurse -File -Force -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum
    if ($null -eq $sum) { return [int64]0 }
    return [int64]$sum
}

function Format-Size {
    param([int64]$Bytes)
    if ($Bytes -ge 1GB) { return ('{0:N1} GB' -f ($Bytes / 1GB)) }
    return ('{0:N0} MB' -f ($Bytes / 1MB))
}

# Deletes a folder tree (robocopy mirror of an empty folder handles read-only files and deep paths) and logs it.
function Remove-Tree {
    param([string]$Path, [string]$Reason)
    if (-not (Test-Path -LiteralPath $Path)) { return }
    if ($Path.TrimEnd('\').Split('\').Count -lt 3) { throw ('Refusing to delete {0}: too close to a drive root.' -f $Path) }
    $size = Get-DirSize -Path $Path
    if ($DryRun) { Write-Log ('would remove {0} ({1}): {2}' -f $Path, (Format-Size $size), $Reason); return }
    $empty = Join-Path $env:TEMP ('empty-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $empty -Force | Out-Null
    try {
        Invoke-Native -FilePath 'robocopy.exe' -Arguments @($empty, $Path, '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1') -IgnoreExitCode | Out-Null
        Remove-Item -LiteralPath $Path -Recurse -Force -ErrorAction SilentlyContinue
    }
    finally { Remove-Item -LiteralPath $empty -Force -ErrorAction SilentlyContinue }
    if (Test-Path -LiteralPath $Path) { Write-Log ('could not fully remove {0} (in use?)' -f $Path) 'WARN'; return }
    $script:FreedBytes += $size
    Write-Log ('removed {0} ({1}): {2}' -f $Path, (Format-Size $size), $Reason)
}

# Deletes files older than the cutoff under a folder, one summary line.
function Remove-OldFiles {
    param([string]$Path, [datetime]$Cutoff, [string]$Reason)
    if (-not (Test-Path -LiteralPath $Path)) { return }
    $files = @(Get-ChildItem -LiteralPath $Path -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt $Cutoff })
    if ($files.Count -eq 0) { return }
    $bytes = [int64](($files | Measure-Object -Property Length -Sum).Sum)
    if ($DryRun) { Write-Log ('would remove {0} file(s), {1}, from {2}: {3}' -f $files.Count, (Format-Size $bytes), $Path, $Reason); return }
    $removed = 0
    $removedBytes = [int64]0
    foreach ($file in $files) {
        try { Remove-Item -LiteralPath $file.FullName -Force -ErrorAction Stop; $removed++; $removedBytes += $file.Length } catch { }
    }
    $script:FreedBytes += $removedBytes
    Write-Log ('removed {0} of {1} file(s), {2}, from {3}: {4}' -f $removed, $files.Count, (Format-Size $removedBytes), $Path, $Reason)
}

function Test-RunnerBusy {
    param([string]$Directory)
    $prefix = $Directory.TrimEnd('\') + '\'
    $workers = @(Get-CimInstance Win32_Process -Filter "Name='Runner.Worker.exe'" -ErrorAction SilentlyContinue | Where-Object { $_.ExecutablePath -and $_.ExecutablePath.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) })
    return ($workers.Count -gt 0)
}

function Get-RunnerProfiles {
    if ($ProfileRoots) { return @($ProfileRoots) }
    $roots = @()
    foreach ($service in @(Get-CimInstance Win32_Service -Filter "Name LIKE 'actions.runner.%'" -ErrorAction SilentlyContinue)) {
        $account = [string]$service.StartName
        if ($account -like '.\*') { $account = $env:COMPUTERNAME + '\' + $account.Substring(2) }
        try {
            $sid = (New-Object System.Security.Principal.NTAccount($account)).Translate([System.Security.Principal.SecurityIdentifier]).Value
            $userProfile = Get-CimInstance Win32_UserProfile -Filter ("SID='{0}'" -f $sid) -ErrorAction SilentlyContinue
            if ($userProfile -and $userProfile.LocalPath) { $roots += $userProfile.LocalPath }
        }
        catch { Write-Log ('could not find the profile of {0}: {1}' -f $account, $_.Exception.Message) 'WARN' }
    }
    return @($roots | Select-Object -Unique)
}

$PhpHelper = @'
<?php
// Written by .github/runner-setup/04-maintenance.ps1 and deleted after use. Secrets come from the environment.
$dsn = 'mysql:host='.getenv('CI_MYSQL_HOST').';port='.getenv('CI_MYSQL_PORT').';charset=utf8mb4';
$dry = getenv('WCI_DRY') === '1';
$all = getenv('WCI_ALL') === '1';
$cutoff = time() - ((int) getenv('WCI_MAX_AGE_HOURS')) * 3600;
try {
    $pdo = new PDO($dsn, (string) getenv('CI_MYSQL_USER'), (string) getenv('CI_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
    $pattern = str_replace('_', '\_', 'whci_').'%';
    foreach ($pdo->query('SHOW DATABASES LIKE '.$pdo->quote($pattern))->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if (preg_match('/^whci_[a-z0-9_]+$/', $name) !== 1) {
            continue;
        }
        if (! $all) {
            $stmt = $pdo->prepare('SELECT MAX(CREATE_TIME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
            $stmt->execute([$name]);
            $newest = $stmt->fetchColumn();
            if ($newest === null || $newest === false || strtotime((string) $newest) > $cutoff) {
                echo "KEPT {$name}\n";
                continue;
            }
        }
        if ($dry) {
            echo "WOULD_DROP {$name}\n";
        } else {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
            echo "DROPPED {$name}\n";
        }
    }
} catch (Throwable $e) {
    echo 'ERROR '.$e->getMessage()."\n";
}
'@

function Invoke-DatabaseCleanup {
    param([bool]$AnyJobRunning)
    $phpExe = Join-Path $ToolsRoot 'php\php.exe'
    if (-not (Test-Path -LiteralPath $phpExe)) { Write-Log ('databases: {0} is missing; skipped' -f $phpExe) 'WARN'; return }
    $settings = $null
    foreach ($envFile in @(Get-ChildItem -LiteralPath $RunnersRoot -Directory -ErrorAction SilentlyContinue | ForEach-Object { Join-Path $_.FullName '.env' })) {
        if (-not (Test-Path -LiteralPath $envFile)) { continue }
        $map = @{}
        foreach ($line in (Get-Content -LiteralPath $envFile)) { if ($line -match '^(CI_MYSQL_[A-Z]+)=(.*)$') { $map[$Matches[1]] = $Matches[2] } }
        if ($map.ContainsKey('CI_MYSQL_PASSWORD') -and $map.ContainsKey('CI_MYSQL_PORT') -and $map.ContainsKey('CI_MYSQL_USER')) { $settings = $map; break }
    }
    if (-not $settings) { Write-Log 'databases: no instance .env carries CI_MYSQL_*; skipped' 'WARN'; return }
    if (-not $settings.ContainsKey('CI_MYSQL_HOST')) { $settings['CI_MYSQL_HOST'] = '127.0.0.1' }
    $file = Join-Path $env:TEMP ('wci-clean-' + [guid]::NewGuid().ToString('N') + '.php')
    Write-Utf8File -Path $file -Text $PhpHelper
    $names = @('CI_MYSQL_HOST', 'CI_MYSQL_PORT', 'CI_MYSQL_USER', 'CI_MYSQL_PASSWORD', 'WCI_DRY', 'WCI_ALL', 'WCI_MAX_AGE_HOURS')
    try {
        foreach ($key in @('CI_MYSQL_HOST', 'CI_MYSQL_PORT', 'CI_MYSQL_USER', 'CI_MYSQL_PASSWORD')) { Set-Item ('Env:\' + $key) $settings[$key] }
        Set-Item Env:\WCI_DRY $(if ($DryRun) { '1' } else { '0' })
        Set-Item Env:\WCI_ALL $(if ($AnyJobRunning) { '0' } else { '1' })
        Set-Item Env:\WCI_MAX_AGE_HOURS ([string]$DatabaseMaxAgeHours)
        $result = Invoke-Native -FilePath $phpExe -Arguments @($file) -IgnoreExitCode
    }
    finally {
        foreach ($name in $names) { Remove-Item ('Env:\' + $name) -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $file -Force -ErrorAction SilentlyContinue
    }
    $dropped = 0
    foreach ($line in $result.Output) {
        if ($line -like 'ERROR*') { Write-Log ('databases: ' + $line) 'WARN' }
        elseif ($line -like 'DROPPED *') { $dropped++; Write-Log ('dropped database ' + $line.Substring(8)) }
        elseif ($line -like 'WOULD_DROP *') { $dropped++; Write-Log ('would drop database ' + $line.Substring(11)) }
    }
    if ($dropped -eq 0) { Write-Log 'databases: no stale whci_ database' }
}

function Invoke-Clean {
    if (-not $DryRun) {
        New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
        $script:LogFile = Join-Path $LogDir ('maintenance-{0}.log' -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
        Get-ChildItem -LiteralPath $LogDir -Filter 'maintenance-*.log' | Sort-Object LastWriteTime -Descending | Select-Object -Skip $KeepLogs | Remove-Item -Force -ErrorAction SilentlyContinue
    }
    $drive = (Split-Path -Qualifier $RunnersRoot)
    $diskBefore = Get-CimInstance Win32_LogicalDisk -Filter ("DeviceID='{0}'" -f $drive)
    Write-Log ('maintenance starts{0}; {1} free on {2}' -f $(if ($DryRun) { ' (dry run: nothing is removed)' } else { '' }), (Format-Size ([int64]$diskBefore.FreeSpace)), $drive)

    $anyJob = (@(Get-CimInstance Win32_Process -Filter "Name='Runner.Worker.exe'" -ErrorAction SilentlyContinue)).Count -gt 0
    if ($anyJob) { Write-Log 'a job is running somewhere: caches and the busy instance are left alone this time' }

    # ---- per instance
    $instances = @()
    if (Test-Path -LiteralPath $RunnersRoot) { $instances = @(Get-ChildItem -LiteralPath $RunnersRoot -Directory | Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'config.cmd') }) }
    foreach ($instance in $instances) {
        $dir = $instance.FullName
        if (Test-RunnerBusy -Directory $dir) { Write-Log ('{0}: running a job, skipped' -f $dir); continue }
        $work = Join-Path $dir '_work'
        if (Test-Path -LiteralPath $work) {
            foreach ($item in @(Get-ChildItem -LiteralPath $work -Directory -Force | Where-Object { $KeepInWork -notcontains $_.Name })) { Remove-Tree -Path $item.FullName -Reason 'job workspace left behind (the job-completed hook normally removes it)' }
            Remove-OldFiles -Path (Join-Path $work '_temp') -Cutoff (Get-Date).AddDays(-2) -Reason '_temp older than 2 days'
            $actions = Join-Path $work '_actions'
            if (Test-Path -LiteralPath $actions) {
                # _actions\<owner>\<repo>\<ref>: re-downloaded by the runner when a job needs it again.
                foreach ($owner in @(Get-ChildItem -LiteralPath $actions -Directory -ErrorAction SilentlyContinue)) {
                    foreach ($repo in @(Get-ChildItem -LiteralPath $owner.FullName -Directory -ErrorAction SilentlyContinue)) {
                        foreach ($ref in @(Get-ChildItem -LiteralPath $repo.FullName -Directory -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$ActionsMaxAgeDays) })) {
                            Remove-Tree -Path $ref.FullName -Reason ('action checkout unused for {0} days' -f $ActionsMaxAgeDays)
                        }
                    }
                }
            }
            $node = Join-Path $work '_tool\node'
            if (Test-Path -LiteralPath $node) {
                $versions = @(Get-ChildItem -LiteralPath $node -Directory | Where-Object { $_.Name -match '^\d+\.\d+\.\d+$' } | Sort-Object { [version]$_.Name } -Descending)
                foreach ($old in @($versions | Select-Object -Skip $KeepNodeVersions)) { Remove-Tree -Path $old.FullName -Reason ('Node version beyond the newest {0}' -f $KeepNodeVersions) }
            }
            $browsers = Join-Path $work '_tool\ms-playwright'
            if (Test-Path -LiteralPath $browsers) {
                $entries = @(Get-ChildItem -LiteralPath $browsers -Directory | Where-Object { $_.Name -match '^(.+)-(\d+)$' } | ForEach-Object {
                        $null = $_.Name -match '^(.+)-(\d+)$'
                        [pscustomobject]@{ Item = $_; Family = $Matches[1]; Revision = [int]$Matches[2] }
                    })
                foreach ($group in @($entries | Group-Object -Property Family)) {
                    foreach ($old in @($group.Group | Sort-Object -Property Revision -Descending | Select-Object -Skip $KeepBrowserRevisions)) {
                        Remove-Tree -Path $old.Item.FullName -Reason ('Playwright {0} revision {1}, beyond the newest {2}' -f $old.Family, $old.Revision, $KeepBrowserRevisions)
                    }
                }
            }
        }
        Remove-OldFiles -Path (Join-Path $dir '_diag') -Cutoff (Get-Date).AddDays(-14) -Reason 'runner logs older than 14 days'
    }
    if ($instances.Count -eq 0) { Write-Log ('no runner instance found under {0}' -f $RunnersRoot) }

    # ---- old toolchain leftovers
    Remove-OldFiles -Path (Join-Path $ToolsRoot 'downloads') -Cutoff (Get-Date).AddDays(-90) -Reason 'installer downloads older than 90 days'
    if (Test-Path -LiteralPath $ToolsRoot) {
        foreach ($old in @(Get-ChildItem -LiteralPath $ToolsRoot -Directory -Filter 'php.old-*' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) })) { Remove-Tree -Path $old.FullName -Reason 'replaced PHP, older than 30 days' }
    }

    # ---- caches of the runner accounts (only while nothing runs)
    if ($anyJob) { Write-Log 'caches: skipped, a job is running' }
    else {
        foreach ($profileRoot in (Get-RunnerProfiles)) {
            if (-not (Test-Path -LiteralPath $profileRoot)) { continue }
            $local = Join-Path $profileRoot 'AppData\Local'
            $cutoff = (Get-Date).AddDays(-$CacheMaxAgeDays)
            Remove-OldFiles -Path (Join-Path $local 'Composer\cache') -Cutoff $cutoff -Reason ('Composer cache older than {0} days' -f $CacheMaxAgeDays)
            Remove-OldFiles -Path (Join-Path $local 'pip\Cache') -Cutoff $cutoff -Reason ('pip cache older than {0} days' -f $CacheMaxAgeDays)
            $npm = Join-Path $local 'npm-cache'
            if (Test-Path -LiteralPath $npm) {
                $npmSize = Get-DirSize -Path $npm
                if ($npmSize -gt ([int64]$NpmCacheMaxGb * 1GB)) { Remove-Tree -Path (Join-Path $npm '_cacache') -Reason ('npm cache is {0}, over {1} GB' -f (Format-Size $npmSize), $NpmCacheMaxGb) }
                Remove-OldFiles -Path (Join-Path $npm '_logs') -Cutoff (Get-Date).AddDays(-14) -Reason 'npm logs older than 14 days'
            }
            Remove-OldFiles -Path (Join-Path $local 'Temp') -Cutoff (Get-Date).AddDays(-2) -Reason 'temp files older than 2 days'
        }
    }

    # ---- databases
    if ($SkipDatabases) { Write-Log 'databases: skipped (-SkipDatabases)' }
    else { Invoke-DatabaseCleanup -AnyJobRunning $anyJob }

    # ---- result
    $diskAfter = Get-CimInstance Win32_LogicalDisk -Filter ("DeviceID='{0}'" -f $drive)
    $freeAfter = [int64]$diskAfter.FreeSpace
    Write-Log ('maintenance finished; removed about {0}; {1} free on {2}' -f (Format-Size $script:FreedBytes), (Format-Size $freeAfter), $drive)
    foreach ($instance in $instances) { Write-Log ('size {0}: {1}' -f $instance.FullName, (Format-Size (Get-DirSize -Path $instance.FullName))) }
    if ($freeAfter -lt ([int64]$MinFreeGb * 1GB)) {
        Write-Log ('only {0} free on {1} after cleanup, below {2} GB: add disk, lower the instance count, or look at the sizes above' -f (Format-Size $freeAfter), $drive, $MinFreeGb) 'WARN'
        $script:ExitCode = 2
    }
}

function Get-TaskArguments {
    # Every tunable is written out, so changing one here and re-running Install updates the task.
    $parts = @(
        '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
        '-File', ('"{0}"' -f $InstalledScript), '-Mode', 'Clean',
        '-RunnersRoot', ('"{0}"' -f $RunnersRoot), '-ToolsRoot', ('"{0}"' -f $ToolsRoot), '-InstallDir', ('"{0}"' -f $InstallDir),
        '-CacheMaxAgeDays', $CacheMaxAgeDays, '-ActionsMaxAgeDays', $ActionsMaxAgeDays, '-KeepNodeVersions', $KeepNodeVersions,
        '-KeepBrowserRevisions', $KeepBrowserRevisions, '-NpmCacheMaxGb', $NpmCacheMaxGb, '-DatabaseMaxAgeHours', $DatabaseMaxAgeHours,
        '-MinFreeGb', $MinFreeGb, '-KeepLogs', $KeepLogs
    )
    if ($SkipDatabases) { $parts += '-SkipDatabases' }
    return ($parts -join ' ')
}

function Invoke-Install {
    Assert-Administrator
    Assert-PlainPath -Path $InstallDir -What 'InstallDir'
    Assert-PlainPath -Path $RunnersRoot -What 'RunnersRoot'
    Assert-PlainPath -Path $ToolsRoot -What 'ToolsRoot'
    if (-not (Get-Command Register-ScheduledTask -ErrorAction SilentlyContinue)) { throw 'The ScheduledTasks module is missing (needs Windows Server 2012 or later).' }

    Write-Section 'Install folder (the task runs as SYSTEM, so nobody else may write here)'
    if (-not (Test-Path -LiteralPath $InstallDir)) { Invoke-Step ('Create {0}' -f $InstallDir) { New-Item -ItemType Directory -Force -Path $LogDir | Out-Null } }
    Confirm-DirectoryAcl -Path $InstallDir -Grants @('*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F', '*S-1-5-32-545:(OI)(CI)RX') -RequiredSids @('S-1-5-32-545') -What 'restrict to administrators (write) and users (read)'

    Write-Section 'Installed copy of this script'
    $installedHash = ''
    if (Test-Path -LiteralPath $InstalledScript) { $installedHash = (Get-FileHash -LiteralPath $InstalledScript -Algorithm SHA256).Hash }
    $thisHash = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash
    if ($installedHash -eq $thisHash) { Write-Ok ('{0} is current.' -f $InstalledScript) }
    else { Invoke-Step ('Copy this script to {0}' -f $InstalledScript) { New-Item -ItemType Directory -Force -Path $LogDir | Out-Null; Copy-Item -LiteralPath $PSCommandPath -Destination $InstalledScript -Force } }

    Write-Section ('Scheduled task {0}' -f $TaskName)
    $arguments = Get-TaskArguments
    $existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    $dayMask = [int][math]::Pow(2, [int]$Day)
    $current = $false
    if ($existing) {
        $action = @($existing.Actions)[0]
        $trigger = @($existing.Triggers)[0]
        $current = ($action.Execute -ieq 'powershell.exe') -and ($action.Arguments -ceq $arguments) -and $trigger -and ($trigger.DaysOfWeek -eq $dayMask) -and ([string]$trigger.StartBoundary -like ('*T{0}:00*' -f $At)) -and ($existing.Principal.UserId -match 'SYSTEM')
    }
    if ($current) { Write-Ok ('{0} is registered: weekly {1} {2}, as SYSTEM.' -f $TaskName, $Day, $At) }
    else {
        Invoke-Step ('Register {0}: weekly on {1} at {2} as SYSTEM, one run at a time, started late if the VPS was off' -f $TaskName, $Day, $At) {
            $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments
            $trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek $Day -At $At
            $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
            $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 3) -MultipleInstances IgnoreNew -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
            Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Weekly disk cleanup of the warehouse CI runner VPS (.github/runner-setup/04-maintenance.ps1).' -Force | Out-Null
        }
    }

    Write-Section 'Summary'
    if ($script:Warnings.Count -gt 0) { $script:Warnings | ForEach-Object { Write-Host ('  - ' + $_) -ForegroundColor Yellow } }
    if ($DryRun) { Write-Host 'Dry run finished; nothing was changed. To see what a cleanup would remove now:  powershell -File <this script> -Mode Clean -DryRun' -ForegroundColor Yellow }
    else {
        Write-Host ('Done. Run it now with:  Start-ScheduledTask -TaskName {0}   (logs: {1})' -f $TaskName, $LogDir) -ForegroundColor Green
        Write-Host ('Preview first:  powershell -File "{0}" -Mode Clean -DryRun' -f $InstalledScript)
    }
}

try {
    if ($Mode -eq 'Install') { Invoke-Install }
    else {
        Assert-Administrator
        Invoke-Clean
        exit $script:ExitCode
    }
}
catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    if ($script:LogFile) { Add-Content -LiteralPath $script:LogFile -Value ('{0} [ERROR] {1}' -f (Get-Date -Format 's'), $_.Exception.Message) }
    exit 1
}

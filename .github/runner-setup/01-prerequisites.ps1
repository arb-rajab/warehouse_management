#Requires -Version 5.1
<#
.SYNOPSIS
    Step 1 of 4: installs and checks what the CI runners on the Windows VPS need.

.DESCRIPTION
    Git for Windows, PHP (NTS x64) with the extensions and php.ini settings that
    .github/scripts/require-php.sh checks, Composer 2 (callable from bash), Python 3.10+,
    the gh CLI, the Visual C++ runtime, the Server-Media-Foundation Windows feature
    (Playwright's Chromium), long path support, Defender exclusions, and a check that
    the ports and outbound hosts the jobs need are available.

    Node is not installed here: actions/setup-node puts it in each runner's tool cache.
    MySQL is step 2, the runners step 3, disk maintenance step 4.

    Safe to re-run: what is already right is left alone and reported as [ok]. Nothing
    is upgraded unless -Update is given. -DryRun (alias -WhatIf) prints what it would
    change and changes nothing. It stops at the first error. Never run on a real VPS
    before reading the plan from a -DryRun.

    Not tested on Windows by the author: see docs/self-hosted-runner.md.

.PARAMETER ToolsRoot
    Where PHP, Composer and the downloads live. No spaces. Locked down to administrators.
.PARAMETER PhpMinor
    PHP major.minor to install, as the workflows ask (8.5). The newest patch release is used.
.PARAMETER ChecksumOverride
    File name -> SHA256 (or MD5) for a download whose vendor checksum cannot be found
    automatically, e.g. @{ 'python-3.13.1-amd64.exe' = '<md5 from python.org>' }.
    Put the file into <ToolsRoot>\downloads first when the VPS cannot reach its host.
.PARAMETER PythonVersion
    Needed only when no Python 3.10+ is installed yet (for example 3.13.1). The python.org
    installer is checked with its published MD5 (from -ChecksumOverride) and its
    Authenticode signature.
.PARAMETER Instances
    How many runner instances step 3 will create; only used to size the port check and the
    Defender exclusions.
.PARAMETER ServiceAccount
    Base name of the local account(s) the runner services will use (step 3); only used for
    the Defender exclusion of their profile folders.
.PARAMETER Update
    Replace the installed PHP, Composer, Git and gh with the newest release.
.PARAMETER SkipNetworkCheck
    Skip the outbound-host check.
.PARAMETER SkipDefender
    Do not touch Windows Defender.
.PARAMETER DryRun
    Print what would change; change nothing. Alias: -WhatIf.
#>
[CmdletBinding()]
param(
    [string]$ToolsRoot = 'C:\ci-tools',
    [string]$PhpMinor = '8.5',
    [hashtable]$ChecksumOverride = @{},
    [string]$PythonVersion,
    [ValidateRange(1, 16)][int]$Instances = 4,
    [string]$RunnersRoot = 'C:\actions-runners',
    [string]$MysqlDataRoot = 'C:\mysql-data',
    [int]$MysqlPort = 3307,
    [string]$ServiceAccount = 'ci-runner',
    [string[]]$DefenderExclusions,
    [switch]$Update,
    [switch]$SkipNetworkCheck,
    [switch]$SkipDefender,
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

# Keep in sync with the list in .github/scripts/require-php.sh.
$RequiredExtensions = @(
    'calendar', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv',
    'intl', 'json', 'libxml', 'mbstring', 'openssl', 'pcre', 'pdo_mysql', 'pdo_sqlite', 'phar',
    'reflection', 'session', 'simplexml', 'sqlite3', 'tokenizer', 'xml', 'xmlreader',
    'xmlwriter', 'zip', 'zlib'
)

function Get-ProgramFiles64 {
    if ($env:ProgramW6432) { return $env:ProgramW6432 }
    return $env:ProgramFiles
}

# Machine PATH edits go through the registry so %SystemRoot% style entries stay REG_EXPAND_SZ.
function Get-MachinePathEntries {
    $key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Session Manager\Environment')
    try { $raw = [string]$key.GetValue('Path', '', [Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames) }
    finally { $key.Close() }
    return @($raw.Split(';') | Where-Object { $_ -ne '' })
}

function Test-SamePath {
    param([string]$A, [string]$B)
    $left = [Environment]::ExpandEnvironmentVariables($A).TrimEnd('\')
    $right = [Environment]::ExpandEnvironmentVariables($B).TrimEnd('\')
    return ($left -ieq $right)
}

# $true when the folder is on the machine PATH ahead of System32, so it wins over anything there
# (notably WSL's bash.exe).
function Test-MachinePathFront {
    param([string]$Directory)
    $entries = Get-MachinePathEntries
    $position = -1
    $system32 = -1
    for ($i = 0; $i -lt $entries.Count; $i++) {
        if (($position -lt 0) -and (Test-SamePath $entries[$i] $Directory)) { $position = $i }
        if (($system32 -lt 0) -and (Test-SamePath $entries[$i] (Join-Path $env:SystemRoot 'System32'))) { $system32 = $i }
    }
    if ($position -lt 0) { return $false }
    return (($system32 -lt 0) -or ($position -lt $system32))
}

function Set-MachinePathEntries {
    param([string[]]$Entries)
    $new = $Entries -join ';'
    $key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Session Manager\Environment', $true)
    try { $key.SetValue('Path', $new, [Microsoft.Win32.RegistryValueKind]::ExpandString) }
    finally { $key.Close() }
    # Setting and clearing a variable broadcasts the change to running programs.
    [Environment]::SetEnvironmentVariable('WAREHOUSE_CI_PATH_REFRESH', [guid]::NewGuid().ToString(), 'Machine')
    [Environment]::SetEnvironmentVariable('WAREHOUSE_CI_PATH_REFRESH', $null, 'Machine')
    Update-ProcessPath
}

function Set-MachinePathFront {
    param([string]$Directory)
    Set-MachinePathEntries -Entries (@($Directory) + @(Get-MachinePathEntries | Where-Object { -not (Test-SamePath $_ $Directory) }))
}

function Add-MachinePathEnd {
    param([string]$Directory)
    Set-MachinePathEntries -Entries (@(Get-MachinePathEntries) + @($Directory))
}

# For a tool that only has to be found, not to win: appended, so it shadows nothing in System32.
function Ensure-PathEnd {
    param([string]$Directory)
    foreach ($entry in (Get-MachinePathEntries)) {
        if (Test-SamePath $entry $Directory) { Write-Ok ('{0} is on the machine PATH.' -f $Directory); return }
    }
    Invoke-Step ('Append {0} to the machine PATH' -f $Directory) { Add-MachinePathEnd -Directory $Directory }
}

function Ensure-PathFront {
    param([string]$Directory)
    if (Test-MachinePathFront -Directory $Directory) { Write-Ok ('{0} is on the machine PATH ahead of System32.' -f $Directory); return }
    Invoke-Step ('Put {0} at the front of the machine PATH' -f $Directory) { Set-MachinePathFront -Directory $Directory }
}

function Get-MachineFirstOnPath {
    param([string]$FileName)
    foreach ($entry in (Get-MachinePathEntries)) {
        $candidate = Join-Path ([Environment]::ExpandEnvironmentVariables($entry)) $FileName
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }
    return $null
}

function Assert-Signature {
    param([string]$Path, [string]$SubjectPattern)
    $signature = Get-AuthenticodeSignature -FilePath $Path
    if ($signature.Status -ne 'Valid') { throw ('{0} has no valid Authenticode signature ({1}); not running it.' -f (Split-Path -Leaf $Path), $signature.Status) }
    if ($signature.SignerCertificate.Subject -notmatch $SubjectPattern) { throw ('{0} is signed by "{1}", expected {2}.' -f (Split-Path -Leaf $Path), $signature.SignerCertificate.Subject, $SubjectPattern) }
}

# Runs an installer; 3010 means installed but a reboot is pending. 1638 (a newer version is already there) is fine.
function Invoke-Installer {
    param([string]$FilePath, [string]$InstallerArguments, [int[]]$AcceptCodes = @(0, 3010))
    $process = Start-Process -FilePath $FilePath -ArgumentList $InstallerArguments -Wait -PassThru
    if ($AcceptCodes -notcontains $process.ExitCode) { throw ('{0} exited with code {1}.' -f (Split-Path -Leaf $FilePath), $process.ExitCode) }
    if ($process.ExitCode -eq 3010) { $script:RebootNeeded = $true }
}

function Get-ExpectedChecksum {
    param([string]$FileName, [string]$Found)
    if ($ChecksumOverride.ContainsKey($FileName)) { return [string]$ChecksumOverride[$FileName] }
    return $Found
}

function Test-HttpsReachable {
    param([string]$Url)
    try {
        Invoke-WebRequest -Uri $Url -Method Head -UseBasicParsing -TimeoutSec 20 | Out-Null
        return $true
    }
    catch [System.Net.WebException] {
        # Any HTTP answer, even 403 or 405, proves the host is reachable.
        return ($null -ne $_.Exception.Response)
    }
    catch {
        return $false
    }
}

function Get-ListeningOwner {
    param([int]$Port)
    $connection = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $connection) { return $null }
    $owner = Get-Process -Id $connection.OwningProcess -ErrorAction SilentlyContinue
    if ($owner) { return $owner.ProcessName }
    return ('process ' + $connection.OwningProcess)
}

# Builds php.ini from php.ini-production: the CI settings (no memory limit, UTC, the two
# php-security.ini safeguards), the required extensions switched on (one with a DLL but no line
# in the template is appended; one with neither is built into php.exe), the CA bundle.
function New-PhpIniText {
    param([string[]]$TemplateLines, [string]$PhpDir, [string[]]$Extensions, [string[]]$DllNames, [string]$CaBundle)
    $lines = New-Object System.Collections.Generic.List[string]
    $lines.AddRange([string[]]$TemplateLines)
    $setValue = {
        param([string]$Key, [string]$Value)
        $pattern = '^\s*;?\s*' + [regex]::Escape($Key) + '\s*='
        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match $pattern) { $lines[$i] = ('{0} = {1}' -f $Key, $Value); return }
        }
        $lines.Add(('{0} = {1}' -f $Key, $Value))
    }
    & $setValue 'extension_dir' ('"{0}"' -f (Join-Path $PhpDir 'ext'))
    & $setValue 'memory_limit' '-1'
    & $setValue 'date.timezone' 'UTC'
    # The same two safeguards as .github/php-security.ini (php artisan serve is a web SAPI).
    & $setValue 'session.upload_progress.enabled' 'Off'
    & $setValue 'fatal_error_backtraces' 'Off'
    if ($CaBundle) {
        & $setValue 'curl.cainfo' ('"{0}"' -f $CaBundle)
        & $setValue 'openssl.cafile' ('"{0}"' -f $CaBundle)
    }
    foreach ($extension in $Extensions) {
        $active = '^\s*extension\s*=\s*(php_)?' + [regex]::Escape($extension) + '(\.dll)?\s*$'
        $commented = '^\s*;\s*extension\s*=\s*(php_)?' + [regex]::Escape($extension) + '(\.dll)?\s*$'
        $isActive = $false
        for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i] -match $active) { $isActive = $true; break } }
        if ($isActive) { continue }
        $done = $false
        for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i] -match $commented) { $lines[$i] = ('extension={0}' -f $extension); $done = $true; break } }
        if ((-not $done) -and ($DllNames -contains $extension)) { $lines.Add(('extension={0}' -f $extension)) }
    }
    $lines.Add('; Managed by .github/runner-setup/01-prerequisites.ps1: edits are overwritten on the next run.')
    return (($lines -join "`r`n") + "`r`n")
}

function Test-PythonCandidate {
    param([string]$Command, [string[]]$Prefix = @())
    $found = Get-Command $Command -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $found) { return $false }
    $result = Invoke-Native -FilePath $found.Source -Arguments ($Prefix + @('-c', 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)')) -IgnoreExitCode
    return ($result.ExitCode -eq 0)
}

Assert-Administrator
Update-ProcessPath
if ($DryRun) { Write-Host 'DRY RUN: nothing will be changed; [plan] lines show what a real run would do.' -ForegroundColor Yellow }

$ProgramFiles64 = Get-ProgramFiles64
$GitDir = Join-Path $ProgramFiles64 'Git'
$PhpDir = Join-Path $ToolsRoot 'php'
$ComposerDir = Join-Path $ToolsRoot 'composer'
$Downloads = Join-Path $ToolsRoot 'downloads'
Assert-PlainPath -Path $ToolsRoot -What 'ToolsRoot'
Assert-PlainPath -Path $RunnersRoot -What 'RunnersRoot'
Assert-PlainPath -Path $MysqlDataRoot -What 'MysqlDataRoot'
$AdminAcl = @('*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F')
$ReadAcl = $AdminAcl + @('*S-1-5-32-545:(OI)(CI)RX')

try {
    # ---------------------------------------------------------------- machine
    Write-Section 'Machine'
    $os = Get-CimInstance Win32_OperatingSystem
    $cores = (Get-CimInstance Win32_ComputerSystem).NumberOfLogicalProcessors
    $ramGb = [math]::Round($os.TotalVisibleMemorySize / 1MB, 1)
    $systemDrive = Get-CimInstance Win32_LogicalDisk -Filter ("DeviceID='{0}'" -f $env:SystemDrive)
    $freeGb = [math]::Round($systemDrive.FreeSpace / 1GB, 0)
    Write-Host ('  {0} (build {1}), {2} cores, {3} GB RAM, {4} GB free on {5}' -f $os.Caption, $os.BuildNumber, $cores, $ramGb, $freeGb, $env:SystemDrive)
    if ($cores -lt 4) { Write-Warn ('{0} cores: below the 4 the plan assumes; fewer instances will serve better.' -f $cores) }
    if ($ramGb -lt 7.5) { Write-Warn ('{0} GB RAM: below the 8 GB the plan assumes; Pest, Playwright and MySQL together may swap.' -f $ramGb) }
    if ($freeGb -lt 60) { Write-Warn ('{0} GB free: the runners, caches and browsers need tens of GB; watch the disk.' -f $freeGb) }

    # ---------------------------------------------------------------- folders
    Write-Section 'Folders'
    foreach ($directory in @($ToolsRoot, $Downloads)) {
        if (Test-Path -LiteralPath $directory) { Write-Ok ('{0} exists.' -f $directory) }
        else { Invoke-Step ('Create {0}' -f $directory) { New-Item -ItemType Directory -Force -Path $directory | Out-Null } }
    }
    # C:\ gives Authenticated Users Modify on new folders; a tool folder that every local account
    # can rewrite would let one runner account replace php.exe for all the others.
    Confirm-DirectoryAcl -Path $ToolsRoot -Grants $ReadAcl -RequiredSids @('S-1-5-32-545') -What 'restrict to administrators (write) and users (read and execute)'

    # ---------------------------------------------------------------- long paths
    Write-Section 'Long path support'
    $fsKey = 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem'
    $longPaths = (Get-ItemProperty -Path $fsKey -Name LongPathsEnabled -ErrorAction SilentlyContinue)
    if ($longPaths -and ($longPaths.LongPathsEnabled -eq 1)) { Write-Ok 'Windows LongPathsEnabled is 1.' }
    else { Invoke-Step 'Set HKLM\...\FileSystem\LongPathsEnabled = 1 (node_modules and vendor paths exceed 260 characters)' { New-ItemProperty -Path $fsKey -Name LongPathsEnabled -Value 1 -PropertyType DWord -Force | Out-Null; $script:RebootNeeded = $true } }

    # ---------------------------------------------------------------- VC runtime
    Write-Section 'Visual C++ runtime (PHP, MySQL and Chromium need it)'
    $vcKey = 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64'
    $vc = Get-ItemProperty -Path $vcKey -Name Installed -ErrorAction SilentlyContinue
    if ($vc -and ($vc.Installed -eq 1)) { Write-Ok 'A Visual C++ 2015-2022 x64 runtime is installed.' }
    else {
        Invoke-Step 'Download and install the Visual C++ x64 redistributable (https://aka.ms/vs/17/release/vc_redist.x64.exe, Microsoft-signed)' {
            $file = Join-Path $Downloads 'vc_redist.x64.exe'
            Invoke-WebRequest -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $file -UseBasicParsing
            Assert-Signature -Path $file -SubjectPattern 'Microsoft Corporation'
            Invoke-Installer -FilePath $file -InstallerArguments '/install /quiet /norestart' -AcceptCodes @(0, 1638, 3010)
        }
    }

    # ---------------------------------------------------------------- Git
    Write-Section 'Git for Windows'
    $gitExe = Join-Path $GitDir 'cmd\git.exe'
    if ((Test-Path -LiteralPath $gitExe) -and (-not $Update)) {
        Write-Ok ((Invoke-Native -FilePath $gitExe -Arguments @('--version')).Output -join ' ')
    }
    else {
        Invoke-Step 'Download the newest Git for Windows 64-bit installer (GitHub release, SHA256 from the release) and install it silently into the default path' {
            $asset = Get-GitHubAsset -Repo 'git-for-windows/git' -NamePattern '^Git-[\d.]+-64-bit\.exe$'
            $file = Join-Path $Downloads $asset.Name
            Save-Verified -Url $asset.Url -Destination $file -Checksum (Get-ExpectedChecksum -FileName $asset.Name -Found $asset.Sha256)
            Invoke-Installer -FilePath $file -InstallerArguments ('/VERYSILENT /NORESTART /NOCANCEL /SP- /SUPPRESSMSGBOXES /CLOSEAPPLICATIONS /DIR="{0}"' -f $GitDir)
        }
    }
    # cmd puts git on PATH; bin puts Git's bash ahead of System32 (where WSL's bash.exe would live).
    # usr\bin stays off PATH: it would shadow find.exe and sort.exe for cmd.exe steps. bash finds its own tools.
    Ensure-PathFront -Directory (Join-Path $GitDir 'bin')
    Ensure-PathFront -Directory (Join-Path $GitDir 'cmd')
    # tests/e2e/support/serve-https.mjs runs `openssl` from node, not from bash, so it must be on the
    # Windows PATH: Git's native build in mingw64\bin (curl.exe there stays behind System32's).
    Ensure-PathEnd -Directory (Join-Path $GitDir 'mingw64\bin')
    Update-ProcessPath

    $bashExe = Join-Path $GitDir 'bin\bash.exe'
    if (Test-Path -LiteralPath $gitExe) {
        $longpathsValue = (Invoke-Native -FilePath $gitExe -Arguments @('config', '--system', '--get', 'core.longpaths') -IgnoreExitCode).Output -join ''
        if ($longpathsValue.Trim() -eq 'true') { Write-Ok 'git config --system core.longpaths is true.' }
        else { Invoke-Step 'git config --system core.longpaths true' { Invoke-Native -FilePath $gitExe -Arguments @('config', '--system', 'core.longpaths', 'true') | Out-Null } }

        $firstBash = Get-MachineFirstOnPath -FileName 'bash.exe'
        if ($firstBash -and ($firstBash -ine $bashExe)) {
            throw ('The first bash.exe on the machine PATH is {0}, not Git for Windows'' {1}. Jobs use `shell: bash`; remove WSL (or that bash) so Git Bash wins.' -f $firstBash, $bashExe)
        }
        if (Test-Path -LiteralPath (Join-Path $env:SystemRoot 'System32\bash.exe')) {
            Write-Warn 'System32\bash.exe (WSL launcher) exists. Git Bash is ahead of it on PATH, but the runbook asks for no WSL bash on the VPS.'
        }
        else { Write-Ok 'No WSL bash.exe in System32.' }

        # What the workflows use from bash: git, openssl, ssh (Git's /usr/bin/ssh), cygpath, tr, wc, grep.
        $probe = Invoke-Native -FilePath $bashExe -Arguments @('--noprofile', '--norc', '-c', 'for t in git openssl ssh cygpath tr wc grep; do command -v $t >/dev/null || echo MISSING:$t; done; test -x /usr/bin/ssh || echo MISSING:/usr/bin/ssh') -IgnoreExitCode
        $missingTools = @($probe.Output | Where-Object { $_ -like 'MISSING:*' })
        if ($missingTools.Count -gt 0) { throw ('Git Bash cannot find: {0}. Reinstall Git for Windows into its default path.' -f ($missingTools -join ', ')) }
        Write-Ok 'bash finds git, openssl, ssh (/usr/bin/ssh), cygpath, tr, wc and grep.'

        # The E2E web server makes its TLS certificate with this exact command, started from node (no bash, no MSYS).
        $opensslProbe = Join-Path ([IO.Path]::GetTempPath()) ('wci-openssl-' + [guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $opensslProbe | Out-Null
        try {
            $tls = Invoke-Native -FilePath 'openssl' -Arguments @('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-keyout', (Join-Path $opensslProbe 'key.pem'), '-out', (Join-Path $opensslProbe 'certificate.pem'), '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost,IP:127.0.0.1') -IgnoreExitCode
            if (($tls.ExitCode -eq 0) -and (Test-Path -LiteralPath (Join-Path $opensslProbe 'certificate.pem'))) { Write-Ok 'openssl on the Windows PATH makes the E2E self-signed certificate.' }
            else { Write-Warn ('openssl from the Windows PATH could not make the E2E certificate (exit {0}: {1}); the Playwright job will fail.' -f $tls.ExitCode, (($tls.Output | Select-Object -First 3) -join ' ')) }
        }
        catch { Write-Warn ('openssl is not runnable from the Windows PATH ({0}); the Playwright job will fail until it is.' -f $_.Exception.Message) }
        finally { Remove-Item -LiteralPath $opensslProbe -Recurse -Force -ErrorAction SilentlyContinue }

        # The deploy-script job needs real, dangling-capable symlinks (MSYS=winsymlinks:sys). Unproven on this OS: warn, do not stop.
        $env:MSYS = 'winsymlinks:sys'
        try {
            $symlink = Invoke-Native -FilePath $bashExe -Arguments @('--noprofile', '--norc', '-c', 'd=$(mktemp -d) && cd $d && ln -s $d/missing dangling && test -L dangling && echo SYMLINK_OK; cd /; rm -rf $d') -IgnoreExitCode
        }
        finally { Remove-Item Env:\MSYS -ErrorAction SilentlyContinue }
        if (($symlink.Output -join ' ') -match 'SYMLINK_OK') { Write-Ok 'MSYS=winsymlinks:sys makes a dangling symlink (the deploy-script test needs that).' }
        else { Write-Warn 'MSYS=winsymlinks:sys did not make a dangling symlink; the Deploy script job will fail until that is understood.' }
    }
    else { Write-Plan 'After the install: check Git config, bash precedence, the tools bash needs, openssl on the Windows PATH, and symlink behaviour.' }

    # ---------------------------------------------------------------- PHP
    Write-Section ('PHP {0} (NTS, x64)' -f $PhpMinor)
    $phpExe = Join-Path $PhpDir 'php.exe'
    if ((Test-Path -LiteralPath $phpExe) -and (-not $Update)) { Write-Ok ('{0} exists; not replaced without -Update.' -f $phpExe) }
    else {
        Invoke-Step ('Download the newest PHP {0} NTS x64 zip (windows.php.net releases.json, SHA256 from it) and extract it to {1}' -f $PhpMinor, $PhpDir) {
            $releases = Invoke-RestMethod -Uri 'https://windows.php.net/downloads/releases/releases.json'
            if ($releases -is [string]) { $releases = $releases | ConvertFrom-Json }
            $entry = $releases.PSObject.Properties[$PhpMinor]
            if (-not $entry) { throw ('windows.php.net has no PHP {0} release in releases.json.' -f $PhpMinor) }
            $build = @($entry.Value.PSObject.Properties | Where-Object { $_.Name -match '^nts-vs\d+-x64$' } | Sort-Object { [int]($_.Name -replace '\D', '') } -Descending) | Select-Object -First 1
            if (-not $build) { throw ('No nts-vs*-x64 build listed for PHP {0}.' -f $PhpMinor) }
            $zipInfo = $build.Value.zip
            $zipName = [string]$zipInfo.path
            $zip = Join-Path $Downloads $zipName
            Save-Verified -Url ('https://windows.php.net/downloads/releases/{0}' -f $zipName) -Destination $zip -Checksum (Get-ExpectedChecksum -FileName $zipName -Found ([string]$zipInfo.sha256))
            $staging = Join-Path $ToolsRoot ('php.new-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
            Expand-Zip -Zip $zip -Destination $staging
            if (Test-Path -LiteralPath $PhpDir) { Move-Item -LiteralPath $PhpDir -Destination ($PhpDir + '.old-' + (Get-Date -Format 'yyyyMMddHHmmss')) }
            Move-Item -LiteralPath $staging -Destination $PhpDir
            Write-Host ('         installed {0}; delete {1}.old-* once the new one has proved itself.' -f $zipName, $PhpDir)
        }
    }
    if (Test-Path -LiteralPath $phpExe) {
        $phpProbe = Invoke-Native -FilePath $phpExe -Arguments @('-r', 'echo PHP_VERSION . chr(124) . (PHP_ZTS ? 1 : 0) . chr(124) . PHP_INT_SIZE;') -IgnoreExitCode
        if ($phpProbe.ExitCode -ne 0) {
            Invoke-Step 'PHP did not start (usually a missing Visual C++ runtime): install the newest redistributable' {
                $file = Join-Path $Downloads 'vc_redist.x64.exe'
                Invoke-WebRequest -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $file -UseBasicParsing
                Assert-Signature -Path $file -SubjectPattern 'Microsoft Corporation'
                Invoke-Installer -FilePath $file -InstallerArguments '/install /quiet /norestart' -AcceptCodes @(0, 1638, 3010)
            }
            $phpProbe = Invoke-Native -FilePath $phpExe -Arguments @('-r', 'echo PHP_VERSION . chr(124) . (PHP_ZTS ? 1 : 0) . chr(124) . PHP_INT_SIZE;')
        }
        $phpParts = ($phpProbe.Output -join '').Split('|')
        if (($phpParts.Count -ne 3) -or (-not $phpParts[0].StartsWith($PhpMinor + '.')) -or ($phpParts[1] -ne '0') -or ($phpParts[2] -ne '8')) {
            throw ('{0} is not PHP {1} NTS x64 (it reported "{2}").' -f $phpExe, $PhpMinor, ($phpProbe.Output -join ''))
        }
        Write-Ok ('PHP {0} NTS x64 at {1}.' -f $phpParts[0], $phpExe)

        # CA bundle (curl.se publishes the SHA256 next to the file), for curl and openssl streams.
        $caBundle = Join-Path $PhpDir 'cacert.pem'
        if (Test-Path -LiteralPath $caBundle) { Write-Ok 'cacert.pem exists.' }
        else {
            Invoke-Step 'Download curl.se/ca/cacert.pem and verify it against https://curl.se/ca/cacert.pem.sha256' {
                $expected = ([string](Invoke-RestMethod -Uri 'https://curl.se/ca/cacert.pem.sha256')).Trim().Split(' ')[0]
                Save-Verified -Url 'https://curl.se/ca/cacert.pem' -Destination $caBundle -Checksum $expected
            }
        }

        # php.ini from the shipped production template, plus what setup-php gives CI: no memory limit, UTC, no coverage driver.
        $template = Join-Path $PhpDir 'php.ini-production'
        $iniPath = Join-Path $PhpDir 'php.ini'
        $dllNames = @(Get-ChildItem -LiteralPath (Join-Path $PhpDir 'ext') -Filter 'php_*.dll' | ForEach-Object { $_.BaseName.Substring(4) })
        $desired = New-PhpIniText -TemplateLines @(Get-Content -LiteralPath $template) -PhpDir $PhpDir -Extensions $RequiredExtensions -DllNames $dllNames -CaBundle $caBundle
        $current = ''
        if (Test-Path -LiteralPath $iniPath) { $current = [IO.File]::ReadAllText($iniPath) }
        if ($current -eq $desired) { Write-Ok 'php.ini already holds the CI settings.' }
        else { Invoke-Step ('Write {0} (production template + memory_limit=-1, date.timezone=UTC, required extensions, CA bundle, no Xdebug/PCOV)' -f $iniPath) { Write-Utf8File -Path $iniPath -Text $desired } }

        if (-not $DryRun) {
            $extList = ($RequiredExtensions | ForEach-Object { "'" + $_ + "'" }) -join ','
            $check = Invoke-Native -FilePath $phpExe -Arguments @('-r', ('echo implode(chr(32), array_filter([' + $extList + '], function ($e) { return ! extension_loaded($e); }));'))
            if (($check.Output -join '').Trim() -ne '') { throw ('PHP is missing extensions: {0}. Fix {1} or the PHP build.' -f ($check.Output -join ''), $iniPath) }
            Write-Ok ('All {0} required extensions are loaded.' -f $RequiredExtensions.Count)
            $settings = Invoke-Native -FilePath $phpExe -Arguments @('-r', 'echo ini_get(''memory_limit'') . chr(124) . ini_get(''date.timezone'') . chr(124) . (extension_loaded(''xdebug'') ? ''xdebug'' : '''') . chr(124) . (extension_loaded(''pcov'') ? ''pcov'' : '''');')
            $parts = ($settings.Output -join '').Split('|')
            if ($parts[0] -ne '-1') { throw ('memory_limit is {0}, not -1.' -f $parts[0]) }
            if ($parts[1] -ne 'UTC') { throw ('date.timezone is "{0}", not UTC.' -f $parts[1]) }
            if (($parts[2] -ne '') -or ($parts[3] -ne '')) { throw 'Xdebug or PCOV is loaded (from another ini file?); the CI runs without a coverage driver.' }
            Write-Ok 'memory_limit=-1, date.timezone=UTC, no Xdebug, no PCOV.'
        }
    }
    Ensure-PathFront -Directory $PhpDir

    # ---------------------------------------------------------------- Composer
    Write-Section 'Composer 2'
    $composerPhar = Join-Path $ComposerDir 'composer.phar'
    if ((Test-Path -LiteralPath $composerPhar) -and (-not $Update)) { Write-Ok ('{0} exists; not replaced without -Update.' -f $composerPhar) }
    else {
        Invoke-Step ('Download the newest stable Composer 2 phar from getcomposer.org with its .sha256sum into {0}' -f $ComposerDir) {
            $versions = Invoke-RestMethod -Uri 'https://getcomposer.org/versions'
            if ($versions -is [string]) { $versions = $versions | ConvertFrom-Json }
            $stable = @($versions.stable | Where-Object { $_.version -match '^2\.' }) | Select-Object -First 1
            if (-not $stable) { throw 'getcomposer.org/versions lists no stable 2.x release.' }
            $url = 'https://getcomposer.org' + $stable.path
            $sumText = [string](Invoke-RestMethod -Uri ($url + '.sha256sum'))
            if ($sumText -match '([0-9a-fA-F]{64})') { $composerSha = $Matches[1] }
            else { throw ('Could not read a SHA256 from {0}.sha256sum.' -f $url) }
            New-Item -ItemType Directory -Force -Path $ComposerDir | Out-Null
            Save-Verified -Url $url -Destination $composerPhar -Checksum (Get-ExpectedChecksum -FileName 'composer.phar' -Found $composerSha)
        }
    }
    # composer.bat for cmd, and an extensionless `composer` for bash (the workflows run composer from bash).
    $composerBat = "@echo off`r`nphp ""%~dp0composer.phar"" %*`r`n"
    $composerSh = "#!/usr/bin/env bash`nexec php ""`$(dirname ""`$0"")/composer.phar"" ""`$@""`n"
    foreach ($shim in @(@{ Name = 'composer.bat'; Text = $composerBat }, @{ Name = 'composer'; Text = $composerSh })) {
        $shimPath = Join-Path $ComposerDir $shim.Name
        $existing = ''
        if (Test-Path -LiteralPath $shimPath) { $existing = [IO.File]::ReadAllText($shimPath) }
        if ($existing -eq $shim.Text) { Write-Ok ('{0} is in place.' -f $shimPath) }
        else { Invoke-Step ('Write {0}' -f $shimPath) { New-Item -ItemType Directory -Force -Path $ComposerDir | Out-Null; Write-Utf8File -Path $shimPath -Text $shim.Text } }
    }
    Ensure-PathFront -Directory $ComposerDir
    if ((Test-Path -LiteralPath $composerPhar) -and (Test-Path -LiteralPath $phpExe)) {
        $composerVersion = (Invoke-Native -FilePath $bashExe -Arguments @('--noprofile', '--norc', '-c', 'composer --version --no-ansi')).Output -join ' '
        if ($composerVersion -notmatch 'version 2\.') { throw ('composer from bash reports "{0}", not Composer 2.' -f $composerVersion) }
        Write-Ok ('From bash: {0}' -f $composerVersion)
    }

    # ---------------------------------------------------------------- Python
    Write-Section 'Python 3.10+'
    $storeAlias = Get-Command python -ErrorAction SilentlyContinue | Where-Object { $_.Source -like '*\WindowsApps\*' } | Select-Object -First 1
    if ($storeAlias) { Write-Warn ('python resolves to a Microsoft Store alias ({0}). Turn off Settings > Apps > Advanced app settings > App execution aliases for python.exe and python3.exe, for the runner account(s) too.' -f $storeAlias.Source) }
    if ((Test-PythonCandidate 'python3') -or (Test-PythonCandidate 'python') -or (Test-PythonCandidate 'py' @('-3'))) { Write-Ok 'A working Python 3.10+ answers as python3, python or py.' }
    else {
        if (-not $PythonVersion) { Write-Plan 'No Python 3.10+: re-run with -PythonVersion <x.y.z> (and its published MD5 in -ChecksumOverride) to install it.'; if (-not $DryRun) { throw 'No Python 3.10+ found and -PythonVersion was not given.' } }
        else {
            Invoke-Step ('Download python-{0}-amd64.exe from python.org, verify the published MD5 and the Authenticode signature, install for all users with PATH' -f $PythonVersion) {
                $name = ('python-{0}-amd64.exe' -f $PythonVersion)
                $file = Join-Path $Downloads $name
                Save-Verified -Url ('https://www.python.org/ftp/python/{0}/{1}' -f $PythonVersion, $name) -Destination $file -Checksum (Get-ExpectedChecksum -FileName $name -Found '')
                Assert-Signature -Path $file -SubjectPattern 'Python Software Foundation'
                Invoke-Installer -FilePath $file -InstallerArguments '/quiet InstallAllUsers=1 PrependPath=1 Include_launcher=1 Include_test=0 Include_doc=0 Include_pip=1 Shortcuts=0'
                Update-ProcessPath
            }
        }
    }

    # ---------------------------------------------------------------- gh
    Write-Section 'GitHub CLI (gh)'
    $ghExe = Join-Path $ProgramFiles64 'GitHub CLI\gh.exe'
    if ((Test-Path -LiteralPath $ghExe) -and (-not $Update)) { Write-Ok ((Invoke-Native -FilePath $ghExe -Arguments @('--version')).Output | Select-Object -First 1) }
    else {
        Invoke-Step 'Download the newest gh MSI for windows amd64 (cli/cli release, SHA256 from the release) and install it' {
            $asset = Get-GitHubAsset -Repo 'cli/cli' -NamePattern '^gh_[\d.]+_windows_amd64\.msi$'
            $file = Join-Path $Downloads $asset.Name
            Save-Verified -Url $asset.Url -Destination $file -Checksum (Get-ExpectedChecksum -FileName $asset.Name -Found $asset.Sha256)
            Invoke-Installer -FilePath 'msiexec.exe' -InstallerArguments ('/i "{0}" /qn /norestart' -f $file)
            Update-ProcessPath
        }
    }

    # ---------------------------------------------------------------- Media Foundation
    Write-Section 'Server-Media-Foundation (Playwright Chromium)'
    if (Get-Command Get-WindowsFeature -ErrorAction SilentlyContinue) {
        if ((Get-WindowsFeature -Name Server-Media-Foundation).Installed) { Write-Ok 'Server-Media-Foundation is installed.' }
        else {
            Invoke-Step 'Install-WindowsFeature Server-Media-Foundation (may need a reboot)' {
                $feature = Install-WindowsFeature -Name Server-Media-Foundation
                if (-not $feature.Success) { throw 'Install-WindowsFeature Server-Media-Foundation failed.' }
                if ($feature.RestartNeeded -ne 'No') { $script:RebootNeeded = $true }
            }
        }
    }
    else { Write-Warn 'Get-WindowsFeature is not available (not Windows Server?); Media Foundation is part of client Windows, nothing to install.' }

    # ---------------------------------------------------------------- Defender
    Write-Section 'Windows Defender exclusions'
    if ($SkipDefender) { Write-Ok 'Skipped (-SkipDefender).' }
    elseif (-not (Get-Command Add-MpPreference -ErrorAction SilentlyContinue)) { Write-Warn 'Defender cmdlets are not available; add exclusions for the runner folders in whatever antivirus runs here.' }
    else {
        $wanted = $DefenderExclusions
        if (-not $wanted) {
            $wanted = @($RunnersRoot, $MysqlDataRoot, (Join-Path $env:SystemDrive ('Users\' + $ServiceAccount)))
            for ($n = 1; $n -le $Instances; $n++) { $wanted += (Join-Path $env:SystemDrive ('Users\{0}-{1}' -f $ServiceAccount, $n)) }
        }
        $present = @()
        try { $present = @((Get-MpPreference).ExclusionPath) } catch { Write-Warn ('Could not read Defender settings ({0}); exclusions not checked.' -f $_.Exception.Message) }
        $missing = @($wanted | Where-Object { $present -notcontains $_ })
        if ($missing.Count -eq 0) { Write-Ok 'Defender already excludes the runner, MySQL data and service profile folders.' }
        else { Invoke-Step ('Add Defender path exclusions: {0}' -f ($missing -join ', ')) { Add-MpPreference -ExclusionPath $missing } }
    }

    # ---------------------------------------------------------------- ports
    Write-Section 'Ports'
    $e2ePorts = @(0..(2 * $Instances - 1) | ForEach-Object { 8010 + $_ })
    foreach ($port in (@($MysqlPort) + $e2ePorts)) {
        $owner = Get-ListeningOwner -Port $port
        if (-not $owner) { continue }
        if (($port -eq $MysqlPort) -and ($owner -like 'mysqld*')) { Write-Ok ('Port {0} is MySQL (expected on a re-run).' -f $port); continue }
        Write-Warn ('Port {0} is already listening ({1}); {2}.' -f $port, $owner, $(if ($port -eq $MysqlPort) { 'pick another -MysqlPort in step 2' } else { 'an E2E pair needs it free while a job runs' }))
    }
    Write-Ok ('Checked MySQL port {0} and E2E ports {1}-{2} (runner n uses 8010+2(n-1) and 8011+2(n-1)).' -f $MysqlPort, $e2ePorts[0], $e2ePorts[-1])

    # ---------------------------------------------------------------- network
    Write-Section 'Outbound HTTPS'
    if ($SkipNetworkCheck) { Write-Ok 'Skipped (-SkipNetworkCheck).' }
    else {
        $required = @('https://github.com', 'https://api.github.com', 'https://codeload.github.com', 'https://nodejs.org/dist/index.json', 'https://registry.npmjs.org/', 'https://repo.packagist.org/packages.json', 'https://pypi.org/simple/pip/', 'https://files.pythonhosted.org/', 'https://semgrep.dev', 'https://cdn.playwright.dev')
        $advisory = @('https://playwright.download.prss.microsoft.com', 'https://getcomposer.org/versions')
        $unreachable = @($required | Where-Object { -not (Test-HttpsReachable -Url $_) })
        foreach ($url in $advisory) { if (-not (Test-HttpsReachable -Url $url)) { Write-Warn ('{0} is not reachable (only some Playwright versions / Composer self-update use it).' -f $url) } }
        if ($unreachable.Count -gt 0) { throw ('Not reachable from this VPS: {0}. Open outbound HTTPS to them (or -SkipNetworkCheck if a proxy is configured elsewhere).' -f ($unreachable -join ', ')) }
        Write-Ok 'GitHub, nodejs.org, npm, Packagist, PyPI, Semgrep and the Playwright CDN answer.'
    }

    # ---------------------------------------------------------------- the real check
    $requirePhp = Join-Path $PSScriptRoot '..\scripts\require-php.sh'
    if ((-not $DryRun) -and (Test-Path -LiteralPath $requirePhp)) {
        Write-Section 'The repository''s own PHP check (.github/scripts/require-php.sh)'
        $result = Invoke-Native -FilePath $bashExe -Arguments @('--noprofile', '--norc', ((Resolve-Path -LiteralPath $requirePhp).Path -replace '\\', '/'), $PhpMinor)
        Write-Ok ($result.Output -join ' ')
    }
    else { Write-Host '  (require-php.sh was not next to this script or this is a dry run; the first job runs it.)' }

    # ---------------------------------------------------------------- summary
    Write-Section 'Summary'
    if ($script:Warnings.Count -gt 0) { Write-Host ('{0} warning(s):' -f $script:Warnings.Count) -ForegroundColor Yellow; $script:Warnings | ForEach-Object { Write-Host ('  - ' + $_) -ForegroundColor Yellow } }
    if ($DryRun) { Write-Host 'Dry run finished; nothing was changed.' -ForegroundColor Yellow }
    elseif ($script:RebootNeeded) { Write-Host 'REBOOT REQUIRED (Media Foundation, long paths or an installer asked for it). Reboot, open a NEW elevated PowerShell, then run 02-mysql.ps1.' -ForegroundColor Red }
    else { Write-Host 'Done. Open a NEW elevated PowerShell (so it sees the new PATH), then run 02-mysql.ps1. A reboot now is still the safest way to make every service see the new PATH.' -ForegroundColor Green }
}
catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    Write-Host 'Stopped at the first error; nothing after it ran. Fix the cause and re-run: finished steps are skipped.' -ForegroundColor Red
    exit 1
}

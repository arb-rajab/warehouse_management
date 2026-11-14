#Requires -Version 5.1
<#
.SYNOPSIS
    Step 2 of 4: MySQL Server 8.4 as a Windows service for the CI migration tests.

.DESCRIPTION
    Extracts the official MySQL 8.4 Windows ZIP, writes my.ini (bound to 127.0.0.1 on a private
    port, X protocol off, no binary log, utf8mb4 / utf8mb4_unicode_ci), initializes the data
    directory, registers an automatic service that runs as NetworkService, and creates the
    account the jobs use (default warehouse_ci) with ALL PRIVILEGES on `whci\_%`.* for
    127.0.0.1 and localhost only. Passwords are generated here, shown once at the end, and
    written nowhere. SQL runs through the PHP from step 1 (PDO), with passwords in the child
    process environment, never on a command line.

    The vendor publishes an MD5 for the ZIP on its download page (and a GnuPG signature). There is no
    way to look it up reliably from a script, so you copy it from the page into -MysqlChecksum
    (a SHA256, if you computed one against the signed file, works too).

    Safe to re-run: an initialized data directory, a correct service and my.ini are left alone.
    A first run that stopped half way (root still without a password) is detected and finished.
    -DryRun (alias -WhatIf) changes nothing. Stops at the first error. Not tested on Windows.

.PARAMETER MysqlVersion
    8.4.x, e.g. 8.4.7. Needed with -MysqlChecksum unless -MysqlZipPath is given and the server is not yet installed.
.PARAMETER MysqlChecksum
    The checksum from the MySQL download page for that ZIP (MD5, 32 hex, or SHA256, 64 hex).
.PARAMETER MysqlZipPath
    A ZIP you downloaded yourself (the VPS may block cdn.mysql.com); still checked against -MysqlChecksum.
.PARAMETER MysqlZipUrl
    Override the download URL (default https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-<version>-winx64.zip).
.PARAMETER Port
    The private TCP port (default 3307, so it never meets a default MySQL on 3306).
.PARAMETER RootPassword
    The root password, for re-runs that must verify or repair accounts.
.PARAMETER CiPassword
    Use this password for the CI account instead of a generated one (first run or repair).
.PARAMETER RotateCiPassword
    Generate a new CI password, set it, and show it once. Needs -RootPassword. Then re-run step 3
    with -CiMysqlPassword so every runner's .env gets it.
.PARAMETER Update
    Move the service to the MySQL version in -MysqlVersion (in-place minor upgrade; back up the data first).
.PARAMETER DryRun
    Print what would change; change nothing. Alias: -WhatIf.
#>
[CmdletBinding()]
param(
    [string]$MysqlVersion,
    [string]$MysqlChecksum,
    [string]$MysqlZipPath,
    [string]$MysqlZipUrl,
    [string]$MysqlRoot = 'C:\mysql',
    [string]$DataRoot = 'C:\mysql-data',
    [string]$ToolsRoot = 'C:\ci-tools',
    [ValidateRange(1024, 65535)][int]$Port = 3307,
    [string]$ServiceName = 'MySQL84',
    [string]$CiUser = 'warehouse_ci',
    [securestring]$RootPassword,
    [securestring]$CiPassword,
    [switch]$RotateCiPassword,
    [switch]$Update,
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

$PhpExe = Join-Path $ToolsRoot 'php\php.exe'
$Downloads = Join-Path $ToolsRoot 'downloads'
$DataDir = Join-Path $DataRoot 'data'
$IniPath = Join-Path $DataRoot 'my.ini'
$ErrorLog = Join-Path $DataRoot 'mysql-error.log'
$script:NewRootPassword = $null
$script:NewCiPassword = $null

# One PHP file does every SQL job. Secrets arrive in WCI_* environment variables.
$PhpHelper = @'
<?php
// Written by .github/runner-setup/02-mysql.ps1 and deleted after use.
$host = '127.0.0.1';
$port = (int) getenv('WCI_PORT');
$mode = (string) getenv('WCI_MODE');
$ciUser = (string) getenv('WCI_CI_USER');

function connect_as(string $user, string $password): PDO
{
    global $host, $port;

    return new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function say(string $line): void
{
    fwrite(STDOUT, $line."\n");
}

function ensure_ci_account(PDO $pdo, string $ciUser, string $password, bool $rotate): void
{
    foreach (['127.0.0.1', 'localhost'] as $accountHost) {
        $account = $pdo->quote($ciUser).'@'.$pdo->quote($accountHost);
        if ($password !== '') {
            $pdo->exec("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY ".$pdo->quote($password));
            if ($rotate) {
                $pdo->exec("ALTER USER {$account} IDENTIFIED BY ".$pdo->quote($password));
            }
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ? AND Host = ?');
            $stmt->execute([$ciUser, $accountHost]);
            if ((int) $stmt->fetchColumn() === 0) {
                throw new RuntimeException("The account {$ciUser}@{$accountHost} does not exist; re-run with -RotateCiPassword (or -CiPassword) to create it.");
            }
        }
        // Only schemas named whci_<something>: the migration jobs create and drop theirs.
        $pdo->exec('GRANT ALL PRIVILEGES ON `whci\_%`.* TO '.$account);
    }
}

try {
    if ($mode === 'probe') {
        try {
            connect_as('root', '');
            say('EMPTY_ROOT');
        } catch (PDOException $e) {
            say(str_contains($e->getMessage(), '1045') ? 'ROOT_HAS_PASSWORD' : 'ERROR '.$e->getMessage());
        }
    } elseif ($mode === 'bootstrap') {
        $pdo = connect_as('root', '');
        $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote((string) getenv('WCI_NEW_ROOT_PASSWORD')));
        $pdo = connect_as('root', (string) getenv('WCI_NEW_ROOT_PASSWORD'));
        ensure_ci_account($pdo, $ciUser, (string) getenv('WCI_CI_PASSWORD'), false);
        say('OK bootstrapped root and '.$ciUser);
    } elseif ($mode === 'ensure') {
        $pdo = connect_as('root', (string) getenv('WCI_ROOT_PASSWORD'));
        ensure_ci_account($pdo, $ciUser, (string) getenv('WCI_CI_PASSWORD'), getenv('WCI_ROTATE') === '1');
        say('OK accounts checked for '.$ciUser);
    } elseif ($mode === 'verify') {
        $root = connect_as('root', (string) getenv('WCI_ROOT_PASSWORD'));
        $version = (string) $root->query('SELECT VERSION()')->fetchColumn();
        say(str_starts_with($version, '8.4.') ? "OK server {$version}" : "FAIL server is {$version}, not 8.4");
        $expected = ['bind_address' => '127.0.0.1', 'port' => (string) $port, 'log_bin' => '0', 'character_set_server' => 'utf8mb4', 'collation_server' => 'utf8mb4_unicode_ci'];
        foreach ($expected as $name => $want) {
            $got = (string) $root->query("SELECT @@GLOBAL.{$name}")->fetchColumn();
            say(strtoupper($got) === strtoupper($want) ? "OK {$name}={$got}" : "FAIL {$name} is '{$got}', expected '{$want}'");
        }
        $ciPassword = (string) getenv('WCI_CI_PASSWORD');
        if ($ciPassword !== '') {
            $ci = connect_as($ciUser, $ciPassword);
            $scratch = 'whci_setup_check_'.bin2hex(random_bytes(4));
            $ci->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $ci->exec("DROP DATABASE `{$scratch}`");
            say("OK {$ciUser} can create and drop whci_ databases");
            try {
                $ci->exec('CREATE DATABASE `not_a_ci_database`');
                $root->exec('DROP DATABASE `not_a_ci_database`');
                say("FAIL {$ciUser} could create a database outside whci_%; its grants are too broad");
            } catch (PDOException $e) {
                say("OK {$ciUser} cannot create other databases");
            }
        } else {
            say('SKIP the CI account was not checked (its password is not known to this run)');
        }
    } else {
        say('FAIL unknown mode '.$mode);
    }
} catch (Throwable $e) {
    say('ERROR '.$e->getMessage());
}
'@

function Invoke-MysqlSql {
    param([string]$Mode, [string]$RootPlain, [string]$NewRootPlain, [string]$CiPlain, [switch]$Rotate)
    $file = Join-Path $env:TEMP ('wci-mysql-' + [guid]::NewGuid().ToString('N') + '.php')
    Write-Utf8File -Path $file -Text $PhpHelper
    $names = @('WCI_MODE', 'WCI_PORT', 'WCI_CI_USER', 'WCI_ROOT_PASSWORD', 'WCI_NEW_ROOT_PASSWORD', 'WCI_CI_PASSWORD', 'WCI_ROTATE')
    try {
        Set-Item Env:\WCI_MODE $Mode
        Set-Item Env:\WCI_PORT ([string]$Port)
        Set-Item Env:\WCI_CI_USER $CiUser
        Set-Item Env:\WCI_ROOT_PASSWORD $RootPlain
        Set-Item Env:\WCI_NEW_ROOT_PASSWORD $NewRootPlain
        Set-Item Env:\WCI_CI_PASSWORD $CiPlain
        if ($Rotate) { Set-Item Env:\WCI_ROTATE '1' } else { Set-Item Env:\WCI_ROTATE '0' }
        $result = Invoke-Native -FilePath $PhpExe -Arguments @($file) -IgnoreExitCode
    }
    finally {
        foreach ($name in $names) { Remove-Item ('Env:\' + $name) -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $file -Force -ErrorAction SilentlyContinue
    }
    return $result.Output
}

function Assert-SqlOutput {
    param([string[]]$Output, [string]$What)
    $bad = @($Output | Where-Object { ($_ -like 'ERROR*') -or ($_ -like 'FAIL*') })
    if ($bad.Count -gt 0) { throw ('{0}: {1}' -f $What, ($bad -join ' | ')) }
    foreach ($line in $Output) { if ($line -like 'OK*') { Write-Ok $line.Substring(3) } elseif ($line -like 'SKIP*') { Write-Warn $line.Substring(5) } }
}

function Wait-ForPort {
    param([int]$TimeoutSeconds)
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $client = New-Object System.Net.Sockets.TcpClient
        try { $client.Connect('127.0.0.1', $Port); return $true }
        catch { Start-Sleep -Seconds 2 }
        finally { $client.Close() }
    }
    return $false
}

function Get-InstalledBaseDir {
    if (-not (Test-Path -LiteralPath $MysqlRoot)) { return $null }
    $found = @(Get-ChildItem -LiteralPath $MysqlRoot -Directory | Where-Object { $_.Name -match '^mysql-8\.4\.\d+-winx64$' } |
            Sort-Object { [version]($_.Name -replace '^mysql-(\d+\.\d+\.\d+)-winx64$', '$1') } -Descending)
    if ($found.Count -eq 0) { return $null }
    return $found[0].FullName
}

function Get-ServiceExe {
    $service = Get-CimInstance Win32_Service -Filter ("Name='{0}'" -f $ServiceName)
    if (-not $service) { return $null }
    if ($service.PathName -match '^\s*"?([^"]+?mysqld\.exe)') { return $Matches[1] }
    return [string]$service.PathName
}

function New-MyIni {
    param([string]$BaseDir)
    $base = $BaseDir -replace '\\', '/'
    $data = $DataDir -replace '\\', '/'
    $log = $ErrorLog -replace '\\', '/'
    $text = @(
        '# Managed by .github/runner-setup/02-mysql.ps1: edits are overwritten (a backup is kept).',
        '[mysqld]',
        ('basedir={0}' -f $base),
        ('datadir={0}' -f $data),
        ('log-error={0}' -f $log),
        ('port={0}' -f $Port),
        '# Loopback only: nothing off this machine can reach the CI database.',
        'bind-address=127.0.0.1',
        '# The X protocol would listen on 33060 on every interface.',
        'mysqlx=OFF',
        '# No replication here, so no binary log to fill the disk.',
        'disable-log-bin',
        'character-set-server=utf8mb4',
        'collation-server=utf8mb4_unicode_ci',
        '# Throwaway CI databases: trade durability for speed.',
        'innodb_flush_log_at_trx_commit=2',
        'max_connections=200',
        '',
        '[client]',
        ('port={0}' -f $Port),
        'default-character-set=utf8mb4',
        ''
    ) -join "`r`n"
    return $text
}

Assert-Administrator
Update-ProcessPath
if ($DryRun) { Write-Host 'DRY RUN: nothing will be changed; [plan] lines show what a real run would do.' -ForegroundColor Yellow }
foreach ($pair in @(@('MysqlRoot', $MysqlRoot), @('DataRoot', $DataRoot), @('ToolsRoot', $ToolsRoot))) { Assert-PlainPath -Path $pair[1] -What $pair[0] }
if ($MysqlVersion -and ($MysqlVersion -notmatch '^8\.4\.\d+$')) { throw ('-MysqlVersion must look like 8.4.7, got "{0}".' -f $MysqlVersion) }

try {
    # ---------------------------------------------------------------- preflight
    Write-Section 'Preflight'
    if (-not (Test-Path -LiteralPath $PhpExe)) { throw ('{0} is missing: run 01-prerequisites.ps1 first (the SQL steps use its PHP).' -f $PhpExe) }
    $pdoCheck = Invoke-Native -FilePath $PhpExe -Arguments @('-r', 'echo extension_loaded(''pdo_mysql'') ? ''yes'' : ''no'';')
    if (($pdoCheck.Output -join '') -ne 'yes') { throw 'The step 1 PHP has no pdo_mysql; re-run 01-prerequisites.ps1.' }
    Write-Ok 'PHP with pdo_mysql is available.'

    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    $owner = $null
    if (-not $service) { $owner = $null; $connection = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1; if ($connection) { $owner = (Get-Process -Id $connection.OwningProcess -ErrorAction SilentlyContinue).ProcessName } }
    if ($owner) { throw ('Port {0} is already used by {1}; choose another with -Port (and use the same value in step 3).' -f $Port, $owner) }
    if ($service) { Write-Ok ('Service {0} exists ({1}).' -f $ServiceName, $service.Status) }

    # ---------------------------------------------------------------- binaries
    Write-Section 'MySQL 8.4 binaries'
    $baseDir = Get-InstalledBaseDir
    $needInstall = (-not $baseDir)
    if ($baseDir -and $MysqlVersion) {
        $installedVersion = ((Split-Path -Leaf $baseDir) -replace '^mysql-(.+)-winx64$', '$1')
        if ($installedVersion -ne $MysqlVersion) {
            if ($Update) { $needInstall = $true }
            else { Write-Ok ('MySQL {0} is installed; -MysqlVersion {1} would need -Update (no upgrade without it).' -f $installedVersion, $MysqlVersion) }
        }
    }
    if ($needInstall) {
        $haveInputs = ($MysqlChecksum -and ($MysqlVersion -or $MysqlZipPath))
        if (-not $haveInputs) {
            $text = 'MySQL is not installed: pass -MysqlVersion 8.4.x (or -MysqlZipPath) and -MysqlChecksum (MD5 or SHA256 from https://dev.mysql.com/downloads/mysql/8.4.html).'
            if ($DryRun) { Write-Plan $text } else { throw $text }
        }
        else {
            if ($MysqlZipPath) { $zip = $MysqlZipPath; $zipLabel = $MysqlZipPath }
            else {
                $zipName = ('mysql-{0}-winx64.zip' -f $MysqlVersion)
                $zip = Join-Path $Downloads $zipName
                $zipLabel = $zipName
            }
            Invoke-Step ('Get {0}, verify it against the checksum you gave, and extract it under {1}' -f $zipLabel, $MysqlRoot) {
                if ($MysqlZipPath) {
                    if (-not (Test-Path -LiteralPath $MysqlZipPath)) { throw ('{0} does not exist.' -f $MysqlZipPath) }
                    if (-not (Test-FileChecksum -Path $MysqlZipPath -Expected $MysqlChecksum)) { throw ('{0} does not match the checksum you gave.' -f $MysqlZipPath) }
                }
                else {
                    $url = $MysqlZipUrl
                    if (-not $url) { $url = 'https://cdn.mysql.com/Downloads/MySQL-8.4/' + $zipName }
                    Save-Verified -Url $url -Destination $zip -Checksum $MysqlChecksum
                }
                New-Item -ItemType Directory -Force -Path $MysqlRoot | Out-Null
                $staging = Join-Path $MysqlRoot ('extract-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
                Expand-Zip -Zip $zip -Destination $staging
                $top = @(Get-ChildItem -LiteralPath $staging -Directory)
                if (($top.Count -ne 1) -or ($top[0].Name -notmatch '^mysql-8\.4\.\d+-winx64$')) { throw ('Unexpected ZIP layout in {0}; expected one mysql-8.4.x-winx64 folder.' -f $staging) }
                $target = Join-Path $MysqlRoot $top[0].Name
                if (Test-Path -LiteralPath $target) { throw ('{0} already exists.' -f $target) }
                Move-Item -LiteralPath $top[0].FullName -Destination $target
                Remove-Item -LiteralPath $staging -Recurse -Force
                $script:ExtractedBaseDir = $target
            }
            if (-not $DryRun) { $baseDir = $script:ExtractedBaseDir }
        }
    }
    else { Write-Ok ('Using {0}.' -f $baseDir) }

    if ($baseDir -and (Test-Path -LiteralPath $baseDir)) {
        $mysqld = Join-Path $baseDir 'bin\mysqld.exe'
        $versionText = (Invoke-Native -FilePath $mysqld -Arguments @('--version')).Output -join ' '
        if ($versionText -notmatch 'Ver 8\.4\.') { throw ('{0} reports "{1}", not MySQL 8.4.' -f $mysqld, $versionText) }
        Write-Ok $versionText
        Confirm-DirectoryAcl -Path $MysqlRoot -Grants @('*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F', '*S-1-5-32-545:(OI)(CI)RX', '*S-1-5-20:(OI)(CI)RX') -RequiredSids @('S-1-5-20') -What 'restrict to administrators (write) and users (read and execute)'
    }

    # ---------------------------------------------------------------- data dir and my.ini
    Write-Section 'Data directory and my.ini'
    if (-not (Test-Path -LiteralPath $DataRoot)) { Invoke-Step ('Create {0}' -f $DataRoot) { New-Item -ItemType Directory -Force -Path $DataRoot | Out-Null } }
    # Created before the first initialization so the files inherit it: NetworkService (the service account) may write, other users have no access.
    Confirm-DirectoryAcl -Path $DataRoot -Grants @('*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F', '*S-1-5-20:(OI)(CI)M') -RequiredSids @('S-1-5-20') -What 'restrict to administrators and NetworkService'

    $iniChanged = $false
    if ($baseDir) {
        $desiredIni = New-MyIni -BaseDir $baseDir
        $currentIni = ''
        if (Test-Path -LiteralPath $IniPath) { $currentIni = [IO.File]::ReadAllText($IniPath) }
        if ($currentIni -eq $desiredIni) { Write-Ok ('{0} already holds the CI settings.' -f $IniPath) }
        else {
            Invoke-Step ('Write {0} (bind-address=127.0.0.1, port={1}, mysqlx=OFF, disable-log-bin, utf8mb4_unicode_ci)' -f $IniPath, $Port) {
                if (Test-Path -LiteralPath $IniPath) { Copy-Item -LiteralPath $IniPath -Destination ($IniPath + '.bak-' + (Get-Date -Format 'yyyyMMddHHmmss')) }
                Write-Utf8File -Path $IniPath -Text $desiredIni
            }
            $iniChanged = $true
        }
    }

    $initialized = (Test-Path -LiteralPath (Join-Path $DataDir 'mysql'))
    $justInitialized = $false
    if ($initialized) { Write-Ok 'The data directory is initialized.' }
    elseif (Test-Path -LiteralPath $DataDir) { throw ('{0} exists but is not an initialized data directory (an earlier initialization failed?). Inspect {1}, delete {0} if it is safe, and re-run.' -f $DataDir, $ErrorLog) }
    elseif ($baseDir) {
        Invoke-Step ('mysqld --initialize-insecure into {0} (root gets a password in the bootstrap below, before any job exists)' -f $DataDir) {
            Invoke-Native -FilePath (Join-Path $baseDir 'bin\mysqld.exe') -Arguments @(('--defaults-file=' + $IniPath), '--initialize-insecure', '--console') | Out-Null
            if (-not (Test-Path -LiteralPath (Join-Path $DataDir 'mysql'))) { throw ('Initialization finished but {0}\mysql is missing; see {1}.' -f $DataDir, $ErrorLog) }
            $script:JustInitialized = $true
        }
        if (-not $DryRun) { $justInitialized = [bool]$script:JustInitialized }
    }

    # ---------------------------------------------------------------- service
    Write-Section ('Windows service {0}' -f $ServiceName)
    if ($baseDir) {
        $mysqld = Join-Path $baseDir 'bin\mysqld.exe'
        $currentExe = Get-ServiceExe
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if ($service -and ($currentExe -ine $mysqld)) {
            if ($Update) {
                Invoke-Step ('Move {0} to {1} (stop, remove, install again; the data stays)' -f $ServiceName, $mysqld) {
                    if ((Get-Service -Name $ServiceName).Status -ne 'Stopped') { Stop-Service -Name $ServiceName -Force }
                    Invoke-Native -FilePath $currentExe -Arguments @('--remove', $ServiceName) | Out-Null
                }
                $service = $null
            }
            else { Write-Warn ('Service {0} runs {1}, not {2}; run with -Update to move it.' -f $ServiceName, $currentExe, $mysqld) }
        }
        if (-not $service) {
            Invoke-Step ('mysqld --install {0} --defaults-file={1}' -f $ServiceName, $IniPath) {
                Invoke-Native -FilePath $mysqld -Arguments @('--install', $ServiceName, ('--defaults-file=' + $IniPath)) | Out-Null
            }
        }
        $cim = Get-CimInstance Win32_Service -Filter ("Name='{0}'" -f $ServiceName)
        if ((-not $cim) -and (-not $DryRun)) { throw ('Service {0} was not created.' -f $ServiceName) }
        if ((-not $cim) -or ($cim.StartName -notlike '*NetworkService') -or ($cim.StartMode -ne 'Auto')) {
            Invoke-Step 'sc config: automatic start, run as NT AUTHORITY\NetworkService (no password, not LocalSystem)' {
                Invoke-Native -FilePath 'sc.exe' -Arguments @('config', $ServiceName, 'start=', 'auto', 'obj=', 'NT AUTHORITY\NetworkService') | Out-Null
            }
        }
        else { Write-Ok 'Service starts automatically as NetworkService.' }
        if (-not $DryRun) {
            Invoke-Native -FilePath 'sc.exe' -Arguments @('failure', $ServiceName, 'reset=', '86400', 'actions=', 'restart/60000/restart/60000/restart/60000') | Out-Null
            Write-Ok 'Recovery: restart after 1 minute on failure.'
        }

        $status = $null
        $existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if ($existing) { $status = [string]$existing.Status }
        if ($status -eq 'Running') {
            if ($iniChanged -and (-not $justInitialized)) {
                Invoke-Step ('Restart {0} so the new my.ini takes effect (stops a running job''s MySQL connection: do this when the runners are idle)' -f $ServiceName) { Restart-Service -Name $ServiceName -Force }
            }
            else { Write-Ok 'The service is running.' }
        }
        else {
            Invoke-Step ('Start {0}' -f $ServiceName) { Start-Service -Name $ServiceName }
        }
        if (-not $DryRun) {
            if (-not (Wait-ForPort -TimeoutSeconds 120)) { throw ('MySQL did not answer on 127.0.0.1:{0} within 120 s; read {1}.' -f $Port, $ErrorLog) }
            Write-Ok ('Answering on 127.0.0.1:{0}.' -f $Port)
            $mainListener = Get-NetTCPConnection -State Listen -LocalPort $Port | Select-Object -First 1
            $listeners = @(Get-NetTCPConnection -State Listen -OwningProcess $mainListener.OwningProcess -ErrorAction SilentlyContinue)
            $exposed = @($listeners | Where-Object { $_.LocalAddress -notin @('127.0.0.1', '::1') })
            if ($exposed.Count -gt 0) { throw ('mysqld listens beyond loopback: {0}. Check bind-address and mysqlx in {1}.' -f (($exposed | ForEach-Object { $_.LocalAddress + ':' + $_.LocalPort }) -join ', '), $IniPath) }
            Write-Ok ('mysqld listens on loopback only ({0}).' -f (($listeners | ForEach-Object { $_.LocalAddress + ':' + $_.LocalPort }) -join ', '))
        }
    } else { Write-Plan ('After the install: register {0} (auto start, NetworkService), start it, check it listens on loopback only.' -f $ServiceName) }

    # ---------------------------------------------------------------- accounts
    Write-Section ('Accounts (root and {0})' -f $CiUser)
    $rootPlain = ''
    if ($RootPassword) { $rootPlain = ConvertTo-PlainText $RootPassword }
    $ciPlain = ''
    if ($CiPassword) { $ciPlain = ConvertTo-PlainText $CiPassword }
    if ($DryRun) { Write-Plan 'Probe root; if it still has an empty password, set a generated root password and create the CI account with a generated password (both shown once at the end). Otherwise verify the settings.' }
    else {
        $probe = Invoke-MysqlSql -Mode 'probe' -RootPlain '' -NewRootPlain '' -CiPlain ''
        if ($probe -contains 'EMPTY_ROOT') {
            Write-Do 'root has no password yet: bootstrapping the accounts'
            $script:NewRootPassword = New-RandomSecret -Length 32
            if ($ciPlain -eq '') { $ciPlain = New-RandomSecret -Length 32 }
            $script:NewCiPassword = $ciPlain
            Assert-SqlOutput -Output (Invoke-MysqlSql -Mode 'bootstrap' -RootPlain '' -NewRootPlain $script:NewRootPassword -CiPlain $ciPlain) -What 'Bootstrap'
            $rootPlain = $script:NewRootPassword
        }
        elseif ($probe -contains 'ROOT_HAS_PASSWORD') {
            if ($rootPlain -eq '') { Write-Warn 'The accounts exist; they were not re-verified because -RootPassword was not given. (Their passwords are not stored anywhere.)' }
            else {
                $rotate = [bool]$RotateCiPassword
                if ($rotate -and ($ciPlain -eq '')) { $ciPlain = New-RandomSecret -Length 32 }
                if ($rotate) { $script:NewCiPassword = $ciPlain }
                Assert-SqlOutput -Output (Invoke-MysqlSql -Mode 'ensure' -RootPlain $rootPlain -NewRootPlain '' -CiPlain $ciPlain -Rotate:$rotate) -What 'Account check'
            }
        }
        else { throw ('Could not probe MySQL: {0}' -f ($probe -join ' ')) }

        if ($rootPlain -ne '') {
            Write-Section 'Verification'
            Assert-SqlOutput -Output (Invoke-MysqlSql -Mode 'verify' -RootPlain $rootPlain -NewRootPlain '' -CiPlain $ciPlain) -What 'Verification'
        }
    }

    # ---------------------------------------------------------------- summary
    Write-Section 'Summary'
    if ($script:Warnings.Count -gt 0) { Write-Host ('{0} warning(s):' -f $script:Warnings.Count) -ForegroundColor Yellow; $script:Warnings | ForEach-Object { Write-Host ('  - ' + $_) -ForegroundColor Yellow } }
    if ($DryRun) { Write-Host 'Dry run finished; nothing was changed.' -ForegroundColor Yellow }
    if ($script:NewRootPassword -or $script:NewCiPassword) {
        Write-Host ''
        Write-Host '=========== SAVE THESE NOW: shown once, stored nowhere ===========' -ForegroundColor Red
        if ($script:NewRootPassword) { Write-Host ('MySQL root password        : ' + $script:NewRootPassword) }
        if ($script:NewCiPassword) { Write-Host ('{0} password : {1}' -f $CiUser.PadRight(14), $script:NewCiPassword) }
        Write-Host '===================================================================' -ForegroundColor Red
        Write-Host ('Step 3 asks for the {0} password (it becomes CI_MYSQL_PASSWORD in each runner''s .env). Keep the root password for later repairs, rotation and -Update.' -f $CiUser)
        Write-Host 'Clear this console (cls) and any scrollback or session recording that kept it.'
    }
    elseif (-not $DryRun) { Write-Host ('Done. Port {0}, user {1}. Next: 03-runners.ps1 -MysqlPort {0} (it asks for the {1} password).' -f $Port, $CiUser) -ForegroundColor Green }
}
catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    Write-Host 'Stopped at the first error; nothing after it ran. Fix the cause and re-run: finished steps are skipped.' -ForegroundColor Red
    exit 1
}

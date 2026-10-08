<#
.SYNOPSIS
    Local backup of the Neon production database (MiralDrive Lead Qualification).

.DESCRIPTION
    1. pg_dump (custom format, compressed) of Neon into a local folder (one per day),
       kept $KeepHours hours (24 by default). The most recent dump is never deleted.
    2. Integrity check of the dump (pg_restore --list).
    3. With -Mirror: refreshes the local MySQL copy (connection "local_backup"),
       usable to run the app offline if Neon is unavailable.

    The Neon URL is read from $env:NEON_DB_URL, else from NEON_DB_URL in lead_b/.env.
    Use the DIRECT endpoint (not "-pooler"). pg_dump must be >= the Neon server version
    (create the Neon project with PostgreSQL 16 to match the local tools).

.EXAMPLE
    .\scripts\backup-neon.ps1
    .\scripts\backup-neon.ps1 -Mirror -KeepHours 72
#>
param(
    [string] $BackupDir = (Join-Path $env:USERPROFILE 'MiralDrive-Backups\neon'),
    [int] $KeepHours = 24,
    [switch] $Mirror,
    [string] $PgBin = 'C:\Program Files\PostgreSQL\16\bin',
    [string] $Php = 'php'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$log = Join-Path $BackupDir 'backup.log'

function Write-Log([string] $message) {
    $line = '{0:yyyy-MM-dd HH:mm:ss}  {1}' -f (Get-Date), $message
    Add-Content -Path $log -Value $line -Encoding utf8
    Write-Host $line
}

function Get-NeonUrl {
    if ($env:NEON_DB_URL) { return $env:NEON_DB_URL }
    $envFile = Join-Path $projectRoot '.env'
    if (Test-Path $envFile) {
        $line = Get-Content $envFile | Where-Object { $_ -match '^\s*NEON_DB_URL\s*=' } | Select-Object -First 1
        if ($line) { return ($line -replace '^\s*NEON_DB_URL\s*=\s*', '').Trim('"', "'", ' ') }
    }
    throw 'NEON_DB_URL is not set (environment variable or lead_b/.env).'
}

try {
    $url = Get-NeonUrl
    $pgDump = Join-Path $PgBin 'pg_dump.exe'
    $pgRestore = Join-Path $PgBin 'pg_restore.exe'
    $file = Join-Path $BackupDir ('lead-{0:yyyyMMdd-HHmmss}.dump' -f (Get-Date))

    Write-Log "Dump started -> $file"
    & $pgDump "--dbname=$url" --format=custom --compress=9 --no-owner --no-privileges "--file=$file"
    if ($LASTEXITCODE -ne 0) { throw "pg_dump failed (exit $LASTEXITCODE)" }

    & $pgRestore --list $file | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Dump is not readable (pg_restore exit $LASTEXITCODE)" }
    Write-Log ('Dump OK ({0:N0} KB)' -f ((Get-Item $file).Length / 1KB))

    # Rotation: drop dumps older than $KeepHours, but always keep the newest one.
    $dumps = Get-ChildItem $BackupDir -Filter 'lead-*.dump' | Sort-Object LastWriteTime -Descending
    $old = $dumps | Select-Object -Skip 1 | Where-Object { $_.LastWriteTime -lt (Get-Date).AddHours(-$KeepHours) }
    $old | Remove-Item -Force
    if ($old) { Write-Log "Rotation: $(@($old).Count) dump(s) older than $KeepHours h removed" }

    if ($Mirror) {
        Push-Location $projectRoot
        try {
            $env:NEON_DB_URL = $url
            & $Php artisan migrate --database=local_backup --force --no-interaction | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Local mirror migration failed' }
            & $Php artisan db:copy neon local_backup --force --no-interaction
            if ($LASTEXITCODE -ne 0) { throw 'Local mirror copy failed' }
            Write-Log 'Local MySQL mirror (local_backup) refreshed'
        } finally {
            Pop-Location
        }
    }

    Write-Log 'Backup finished successfully'
    exit 0
} catch {
    Write-Log "ERROR: $($_.Exception.Message)"
    exit 1
}

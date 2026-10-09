<#
.SYNOPSIS
    Schedules the daily local Neon backup (Windows Task Scheduler, current user).

.EXAMPLE
    .\scripts\register-backup-task.ps1              # every day at 06:00, with local MySQL mirror, 7 days kept
    .\scripts\register-backup-task.ps1 -At 13:00    # other time
    .\scripts\register-backup-task.ps1 -KeepHours 72 -BackupDir 'C:\Users\me\OneDrive\Backups\neon'
    Unregister-ScheduledTask -TaskName 'MiralDrive Neon backup'   # remove
#>
param(
    [string] $At = '06:00',
    [switch] $NoMirror,
    [int] $KeepHours = 168,
    [string] $BackupDir = (Join-Path $env:USERPROFILE 'MiralDrive-Backups\neon')
)

$script = Join-Path $PSScriptRoot 'backup-neon.ps1'
$arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$script`" -KeepHours $KeepHours -BackupDir `"$BackupDir`"" + $(if ($NoMirror) { '' } else { ' -Mirror' })

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments -WorkingDirectory (Split-Path -Parent $PSScriptRoot)
$trigger = New-ScheduledTaskTrigger -Daily -At $At
# Runs at the next opportunity if the PC was off at the scheduled time.
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RunOnlyIfNetworkAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask -TaskName 'MiralDrive Neon backup' -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "Task 'MiralDrive Neon backup' scheduled every day at $At (keep $KeepHours h, log: $BackupDir\backup.log)"

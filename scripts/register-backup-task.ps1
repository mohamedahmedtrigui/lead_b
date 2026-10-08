<#
.SYNOPSIS
    Schedules the daily local Neon backup (Windows Task Scheduler, current user).

.EXAMPLE
    .\scripts\register-backup-task.ps1              # every day at 02:00, with local MySQL mirror
    .\scripts\register-backup-task.ps1 -At 13:00    # other time
    Unregister-ScheduledTask -TaskName 'MiralDrive Neon backup'   # remove
#>
param(
    [string] $At = '02:00',
    [switch] $NoMirror
)

$script = Join-Path $PSScriptRoot 'backup-neon.ps1'
$arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$script`"" + $(if ($NoMirror) { '' } else { ' -Mirror' })

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments -WorkingDirectory (Split-Path -Parent $PSScriptRoot)
$trigger = New-ScheduledTaskTrigger -Daily -At $At
# Runs at the next opportunity if the PC was off at the scheduled time.
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RunOnlyIfNetworkAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask -TaskName 'MiralDrive Neon backup' -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "Task 'MiralDrive Neon backup' scheduled every day at $At (log: $env:USERPROFILE\MiralDrive-Backups\neon\backup.log)"

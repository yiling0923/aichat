# Makes the app's automatic jobs run by themselves on this Windows PC: staff reminders, automations, booking reminders,
# the daily summary email and renewals. It adds a Windows scheduled task that runs "php artisan schedule:run" every minute.
# Double-click setup-scheduler.bat (it starts this file). Run it again any time: it replaces the old task.
# This script has not been tested on Windows by its author. If it fails, use the manual steps in README.md.

$project = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) {
    $found = Get-Command php -ErrorAction SilentlyContinue
    if ($found) { $php = $found.Source } else { Write-Host 'Could not find PHP. Install XAMPP, or put php.exe on the PATH, then run this again.' -ForegroundColor Red; exit 1 }
}
$name = 'aichat-scheduler'
$command = "Set-Location -LiteralPath '$project'; & '$php' artisan schedule:run"
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -WindowStyle Hidden -Command `"$command`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

try {
    Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Description 'Runs the AI Messaging Sales Assistant jobs every minute' -Force | Out-Null
    Start-ScheduledTask -TaskName $name
    Write-Host ''
    Write-Host 'Done. The automatic jobs now run every minute while you are signed in to Windows.' -ForegroundColor Green
    Write-Host 'In a minute or two the warning in the staff area should disappear.'
    Write-Host "To stop them later: open Task Scheduler and delete the task called $name."
} catch {
    Write-Host ''
    Write-Host 'Could not create the task. Try right-clicking setup-scheduler.bat and choosing "Run as administrator".' -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

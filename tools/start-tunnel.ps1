# Starts a free public tunnel (localhost.run, built into Windows ssh), writes the new address into .env,
# clears the config cache and saves the webhook address in Meta. If the tunnel drops it starts again
# and repeats. Keep this window open while you test. Close it with Ctrl+C when you are done.
# This script has not been tested on Windows by its author. If it fails, use the manual steps in README.md.

$project = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $project '.env'
Set-Location $project

if (Test-Path 'C:\xampp\php\php.exe') { $env:Path = 'C:\xampp\php;' + $env:Path }

function Set-AppUrl([string]$url) {
    $lines = [System.IO.File]::ReadAllLines($envFile)
    $found = $false
    for ($i = 0; $i -lt $lines.Length; $i++) {
        if ($lines[$i] -match '^APP_URL=') { $lines[$i] = "APP_URL=$url"; $found = $true }
    }
    if (-not $found) { $lines += "APP_URL=$url" }
    [System.IO.File]::WriteAllLines($envFile, $lines, (New-Object System.Text.UTF8Encoding $false))
}

while ($true) {
    Write-Host ''
    Write-Host 'Starting the tunnel... (keep this window open)' -ForegroundColor Cyan
    $script:handled = $false

    ssh -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -o StrictHostKeyChecking=accept-new -R 80:localhost:80 nokey@localhost.run 2>&1 | ForEach-Object {
        $line = "$_"
        Write-Host $line

        if (-not $script:handled -and $line -match '(https://[a-z0-9]+\.lhr\.life)') {
            $script:handled = $true
            $address = $Matches[1]
            Write-Host ''
            Write-Host "Tunnel address: $address" -ForegroundColor Green

            Set-AppUrl "$address/aichat/public"
            & php artisan config:clear | Out-Null
            Start-Sleep -Seconds 3
            & php artisan aichat:meta-webhook
            Write-Host ''
            Write-Host "Ready. Open $address/aichat/public to test, then message your WhatsApp test number." -ForegroundColor Green
        }
    }

    Write-Host ''
    Write-Host 'The tunnel stopped. Starting a new one in 5 seconds (Ctrl+C to quit)...' -ForegroundColor Yellow
    Start-Sleep -Seconds 5
}

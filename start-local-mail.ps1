$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$documentRoot = Split-Path -Parent (Split-Path -Parent $projectRoot)
$mailpitPath = Join-Path $env:LOCALAPPDATA 'Mailpit\mailpit.exe'
$settings = @{
    SMTP_HOST = '127.0.0.1'
    SMTP_PORT = '1025'
    SMTP_AUTH = 'false'
    SMTP_ENCRYPTION = 'none'
    SMTP_FROM_EMAIL = 'no-reply@localhost.test'
    SMTP_FROM_NAME = 'Academic Portal Local Test'
}
$previousSettings = @{}
$mailpitProcess = $null

if (-not (Test-Path $mailpitPath)) {
    throw "Mailpit was not found at $mailpitPath. See EMAIL_SETUP.md for installation instructions."
}

foreach ($port in @(1025, 8025, 8769)) {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $port)
    try {
        $listener.Start()
    } catch {
        throw "Port $port is already in use. Stop the existing service or change the local ports before starting the mock mail environment."
    } finally {
        $listener.Stop()
    }
}

foreach ($name in $settings.Keys) {
    $previousSettings[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
    [Environment]::SetEnvironmentVariable($name, $settings[$name], 'Process')
}

try {
    $mailpitProcess = Start-Process -FilePath $mailpitPath -ArgumentList @('--disable-version-check', '--listen', '127.0.0.1:8025', '--smtp', '127.0.0.1:1025') -PassThru -WindowStyle Hidden
    Write-Host 'Local SMTP capture is running at 127.0.0.1:1025.'
    Write-Host 'Mailpit inbox: http://127.0.0.1:8025'
    Write-Host 'Portal: http://127.0.0.1:8769/grading_app/grading_app/login.php'
    Write-Host 'Stop the PHP server with Ctrl+C; the mock SMTP server will stop with it.'
    Push-Location $projectRoot
    php -S 127.0.0.1:8769 -t $documentRoot
    if ($LASTEXITCODE -ne 0) {
        throw "PHP development server exited with code $LASTEXITCODE."
    }
} finally {
    if (Get-Location | Where-Object { $_.Path -eq $projectRoot }) {
        Pop-Location
    }
    if ($mailpitProcess -and -not $mailpitProcess.HasExited) {
        Stop-Process -Id $mailpitProcess.Id -Force -ErrorAction SilentlyContinue
    }
    foreach ($name in $settings.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousSettings[$name], 'Process')
    }
}

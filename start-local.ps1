$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

$PhpCommand = Get-Command php -ErrorAction SilentlyContinue
$PhpPath = if ($PhpCommand) { $PhpCommand.Source } else { $null }

if (-not $PhpPath) {
    $CandidatePaths = @(
        "C:\php\php.exe",
        "C:\tools\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php.exe",
        "C:\Program Files\PHP\php.exe",
        "C:\Program Files\php\php.exe",
        "C:\Program Files (x86)\PHP\php.exe"
    )

    $PhpPath = $CandidatePaths | Where-Object { Test-Path $_ } | Select-Object -First 1
}

if (-not $PhpPath) {
    Write-Host "PHP not found. Install PHP 8+ and run this script again." -ForegroundColor Red
    Write-Host "Public:"
    Write-Host "http://127.0.0.1:8765/"
    Write-Host ""
    Write-Host "Admin:"
    Write-Host "http://127.0.0.1:8765/admin/"
    exit 1
}

Write-Host "PHP: $PhpPath"
Write-Host ""
Write-Host "Public:"
Write-Host "http://127.0.0.1:8765/"
Write-Host ""
Write-Host "Admin:"
Write-Host "http://127.0.0.1:8765/admin/"
Write-Host ""
Write-Host "Press Ctrl+C to stop the server."

& $PhpPath -S 127.0.0.1:8765 -t $ProjectRoot

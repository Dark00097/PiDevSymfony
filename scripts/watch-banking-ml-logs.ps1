$ErrorActionPreference = 'Stop'

Set-Location (Split-Path -Parent $PSScriptRoot)

$logPath = "var\log\banking_ml.log"

if (-not (Test-Path $logPath)) {
    $logDirectory = Split-Path -Parent $logPath
    if (-not (Test-Path $logDirectory)) {
        New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
    }

    New-Item -ItemType File -Path $logPath -Force | Out-Null
}

Write-Host 'Surveillance des logs Banking ML en direct...' -ForegroundColor Cyan
Write-Host 'Ouvre ensuite /admin?tab=accounts&panel=ia pour declencher la prediction.' -ForegroundColor Cyan
Write-Host "Fichier surveille: $logPath" -ForegroundColor DarkCyan
Write-Host ''

Get-Content $logPath -Tail 50 -Wait

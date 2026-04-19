$ErrorActionPreference = 'Stop'

Set-Location (Split-Path -Parent $PSScriptRoot)

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Assert-Contains {
    param(
        [string]$Text,
        [string]$Needle,
        [string]$ErrorMessage
    )

    if ($Text -notmatch [regex]::Escape($Needle)) {
        throw $ErrorMessage
    }
}

function Show-CommandOutput {
    param([string]$Output)

    if ([string]::IsNullOrWhiteSpace($Output)) {
        Write-Host "(aucune sortie)"
        return
    }

    Write-Host $Output.Trim()
}

Write-Step "Verification Python"
$pythonVersion = python --version 2>&1 | Out-String
Show-CommandOutput $pythonVersion

$pythonDeps = python -c "import pandas, joblib, sklearn; print('OK')" 2>&1 | Out-String
Show-CommandOutput $pythonDeps
Assert-Contains $pythonDeps "OK" "Les dependances Python ML ne sont pas disponibles."

Write-Step "Test des scripts Python individuels"
$kmeansOutput = python vendor\Model\kmeans\train_kmeans.py 2>&1 | Out-String
Show-CommandOutput $kmeansOutput
Assert-Contains $kmeansOutput "PYTHON ML EXECUTED" "Le script K-Means n'a pas execute Python correctement."

$isolationOutput = python vendor\Model\isolation\train_isolation.py 2>&1 | Out-String
Show-CommandOutput $isolationOutput
Assert-Contains $isolationOutput "PYTHON ML EXECUTED" "Le script Isolation Forest n'a pas execute Python correctement."

$rfOutput = python vendor\Model\randomforest\train_rf.py 2>&1 | Out-String
Show-CommandOutput $rfOutput
Assert-Contains $rfOutput "PYTHON ML EXECUTED" "Le script Random Forest n'a pas execute Python correctement."

Write-Step "Test de la commande Symfony"
$symfonyOutput = php bin\console app:banking-ml:train 2>&1 | Out-String
Show-CommandOutput $symfonyOutput
Assert-Contains $symfonyOutput "Entrainement ML termine avec succes" "La commande Symfony ML a echoue."
Assert-Contains $symfonyOutput "PYTHON ML EXECUTED" "La commande Symfony n'a pas declenche Python."

Write-Step "Verification des modeles .pkl"
$modelPaths = @(
    "vendor\Model\kmeans\kmeans_model.pkl",
    "vendor\Model\isolation\isolation_model.pkl",
    "vendor\Model\randomforest\rf_model.pkl"
)

foreach ($modelPath in $modelPaths) {
    $item = Get-Item $modelPath
    if ($item.Length -le 0) {
        throw "Le modele $modelPath est vide."
    }

    Write-Host "$modelPath -> $($item.Length) bytes"
}

Write-Step "Dernieres lignes Banking ML dans banking_ml.log"
if (Test-Path "var\log\banking_ml.log") {
    $logLines = Get-Content "var\log\banking_ml.log" -Tail 50
    if ($null -ne $logLines -and $logLines.Count -gt 0) {
        $logLines | ForEach-Object { Write-Host $_ }
    } else {
        Write-Host "Aucune ligne Banking ML n'a ete trouvee pour l'instant."
    }
} else {
    Write-Host "Le fichier var\log\banking_ml.log n'existe pas encore."
}

Write-Step "Resume"
Write-Host "Le pipeline ML d'entrainement fonctionne."
Write-Host "Pour tester la prediction live, ouvre /admin?tab=accounts&panel=ia"
Write-Host 'et lance: Get-Content var\log\banking_ml.log -Tail 50 -Wait'

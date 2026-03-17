# One-command retrain script for ML models
# Usage: .\retrain_models.ps1

Write-Host "======================================================" -ForegroundColor Green
Write-Host "Retraining ML Models (135-feature pipeline)..." -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor Green

# Prefer local venv, fallback to system python
$pythonExePath = Join-Path (Get-Location).Path "venv\Scripts\python.exe"
if (Test-Path $pythonExePath) {
    Write-Host "[OK] Using virtual environment Python at: $pythonExePath" -ForegroundColor Green
} else {
    $pythonExePath = Join-Path (Get-Location).Path ".venv\Scripts\python.exe"
    if (Test-Path $pythonExePath) {
        Write-Host "[OK] Using virtual environment Python at: $pythonExePath" -ForegroundColor Green
    } else {
        $pythonExePath = "python"
        Write-Host "[INFO] No local venv found - using system Python" -ForegroundColor Yellow
    }
}

# Check if Python is available
try {
    $pythonVersion = & "$pythonExePath" --version 2>&1
    Write-Host "[OK] Python found: $pythonVersion" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Python not found. Please install Python 3.8+ or create .venv" -ForegroundColor Red
    exit 1
}

# Check dependencies
Write-Host ""
Write-Host "Checking dependencies..." -ForegroundColor Yellow
& "$pythonExePath" -c "import flask, flask_cors, numpy, pandas, sklearn, requests, pymysql"
if ($LASTEXITCODE -ne 0) {
    Write-Host "[WARNING] Missing dependencies detected. Installing..." -ForegroundColor Yellow
    & "$pythonExePath" -m pip install --upgrade pip
    & "$pythonExePath" -m pip install -r requirements.txt
} else {
    Write-Host "[OK] Core ML dependencies available" -ForegroundColor Green
}

# Optional: use remote DB if credentials exist in environment
if ($env:DB_HOST -or $env:DB_USER -or $env:DB_PASSWORD -or $env:DB_NAME) {
    Write-Host "[INFO] Using DB_* environment variables for training data" -ForegroundColor Cyan
} else {
    Write-Host "[INFO] No DB_* environment variables set; training will use synthetic data" -ForegroundColor Yellow
}

# Run training
Write-Host ""
Write-Host "Starting training..." -ForegroundColor Green
@'
import service

print("Starting training...")
ok = service.train_ml_models()
print("Training result:", ok)
'@ | & "$pythonExePath" -

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "[OK] Training finished. Models saved to current folder." -ForegroundColor Green
    Write-Host "Files: risk_model.pkl, category_model.pkl, risk_encoder.pkl" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "[ERROR] Training failed. Check the log output above." -ForegroundColor Red
    exit $LASTEXITCODE
}

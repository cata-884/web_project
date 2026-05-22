# .\init_backend_windows.ps1
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Write-Host "EROARE: Nu s-a putut porni mediul Docker." -ForegroundColor Red
    Exit 1
}

$attempts = 0
$max_attempts = 30
$dbReady = $false

while (-not $dbReady) {
    docker compose exec -T db pg_isready -U postgres -d web_project_db > $null 2>&1
    if ($LASTEXITCODE -eq 0) {
        $dbReady = $true
    } else {
        $attempts++
        if ($attempts -ge $max_attempts) {
            Write-Host "EROARE: Baza de date nu a devenit disponibila la timp." -ForegroundColor Red
            Exit 1
        }
        Start-Sleep -Seconds 2
    }
}

docker compose exec -T backend php script/migrate.php
if ($LASTEXITCODE -ne 0) {
    Write-Host "EROARE: Rularea migratiilor a esuat." -ForegroundColor Red
    Exit 1
}

docker compose ps
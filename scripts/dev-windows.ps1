<#
  Menjalankan lingkungan pengembangan E-Hibah di Windows (PostgreSQL portabel + php artisan serve).
  Prasyarat: PHP 8.3 & Composer di PATH, PostgreSQL portabel di $PgHome (lihat README).
  Pemakaian:  powershell -ExecutionPolicy Bypass -File scripts\dev-windows.ps1 [-Stop]
#>
param(
    [string]$PgHome = "$env:USERPROFILE\ehibah-pg",
    [int]$PgPort = 5433,
    [int]$WebPort = 8000,
    [switch]$Stop,
    [switch]$Open   # buka peramban otomatis setelah aplikasi siap
)

# PHP hasil instalasi winget belum tentu ada di PATH; cari otomatis.
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $wg = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP*' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($wg) { $env:Path = "$($wg.FullName);$env:Path" }
}

$pgctl = Join-Path $PgHome 'pgsql\bin\pg_ctl.exe'
$data = Join-Path $PgHome 'data'

if ($Stop) {
    & $pgctl -D $data stop -m fast
    Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -like '*php*' } | Stop-Process -Force -ErrorAction SilentlyContinue
    return
}

if (-not (Test-Path $pgctl)) { throw "pg_ctl tidak ditemukan di $pgctl. Ekstrak PostgreSQL portabel ke $PgHome\pgsql lalu initdb (lihat README)." }

# start PostgreSQL bila belum berjalan (-W agar tidak menunggu/menggantung)
& $pgctl -D $data status | Out-Null
if ($LASTEXITCODE -ne 0) {
    Start-Process -FilePath $pgctl -ArgumentList @('-D', "`"$data`"", '-o', "`"-p $PgPort -c listen_addresses=127.0.0.1`"", '-l', "`"$PgHome\pg.log`"", 'start', '-W') -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

Set-Location (Split-Path $PSScriptRoot -Parent)
php artisan migrate --force
if (Get-NetTCPConnection -State Listen -LocalPort $WebPort -ErrorAction SilentlyContinue) {
    Write-Host "Aplikasi sudah berjalan di http://127.0.0.1:$WebPort" -ForegroundColor Yellow
    if ($Open) { Start-Process "http://localhost:$WebPort" }
    return
}
if ($Open) { Start-Job { Start-Sleep 4; Start-Process "http://localhost:$using:WebPort" } | Out-Null }
Write-Host "Aplikasi: http://127.0.0.1:$WebPort  (Ctrl+C untuk berhenti)" -ForegroundColor Green
php artisan serve --host=127.0.0.1 --port=$WebPort

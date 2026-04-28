# ============================================================
# Kankio Desktop — Windows .exe Build Script
# Çalıştır: powershell -ExecutionPolicy Bypass -File build-desktop.ps1
# ============================================================

$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot
$TauriDir    = "$ProjectRoot\src-tauri"
$IconDir     = "$TauriDir\icons"

Write-Host "`n=== Kankio Desktop Build ===" -ForegroundColor Cyan

# ── 1. Rust kontrolü ─────────────────────────────────────────
Write-Host "`n[1/5] Rust kontrol ediliyor..." -ForegroundColor Yellow
try {
    $rustVer = & rustc --version 2>&1
    Write-Host "  ✓ $rustVer" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Rust bulunamadi! Yukleniyor..." -ForegroundColor Red
    Write-Host "  → https://rustup.rs adresinden yukleyin veya:" -ForegroundColor White
    Write-Host "    winget install Rustlang.Rustup" -ForegroundColor White
    Write-Host "  Yukledikten sonra bu scripti tekrar calistirin." -ForegroundColor White
    exit 1
}

# ── 2. Tauri CLI kontrolü ────────────────────────────────────
Write-Host "`n[2/5] Tauri CLI kontrol ediliyor..." -ForegroundColor Yellow
try {
    $tauriVer = & cargo tauri --version 2>&1
    Write-Host "  ✓ $tauriVer" -ForegroundColor Green
} catch {
    Write-Host "  → Tauri CLI yukleniyor (bir kez ~2 dk)..." -ForegroundColor White
    & cargo install tauri-cli --version "^2" --locked
}

# ── 3. İkon dosyaları ────────────────────────────────────────
Write-Host "`n[3/5] Ikonlar kontrol ediliyor..." -ForegroundColor Yellow
if (-not (Test-Path "$IconDir\icon.ico")) {
    Write-Host "  → Ikonlar olusturuluyor..." -ForegroundColor White
    New-Item -ItemType Directory -Force -Path $IconDir | Out-Null
    & cargo tauri icon "$ProjectRoot\app-icon.png" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  ! app-icon.png bulunamadi — placeholder ikonlar olusturuluyor..." -ForegroundColor Yellow
        # .NET ile 512x512 basit ikon olustur
        Add-Type -AssemblyName System.Drawing
        $bmp = New-Object System.Drawing.Bitmap(512, 512)
        $g   = [System.Drawing.Graphics]::FromImage($bmp)
        $g.Clear([System.Drawing.Color]::FromArgb(30, 30, 46))  # dark bg
        $brush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(137, 180, 250))
        $font  = New-Object System.Drawing.Font("Arial", 200, [System.Drawing.FontStyle]::Bold)
        $sf    = New-Object System.Drawing.StringFormat
        $sf.Alignment = [System.Drawing.StringAlignment]::Center
        $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
        $g.DrawString("K", $font, $brush, [System.Drawing.RectangleF]::new(0, 0, 512, 512), $sf)
        $g.Dispose()
        $tmpPng = "$env:TEMP\kankio-icon.png"
        $bmp.Save($tmpPng, [System.Drawing.Imaging.ImageFormat]::Png)
        $bmp.Dispose()
        & cargo tauri icon $tmpPng 2>&1
        Remove-Item $tmpPng -Force
    }
    Write-Host "  ✓ Ikonlar olusturuldu" -ForegroundColor Green
} else {
    Write-Host "  ✓ Ikonlar mevcut" -ForegroundColor Green
}

# ── 4. Frontend placeholder ──────────────────────────────────
Write-Host "`n[4/5] Frontend klasoru kontrol ediliyor..." -ForegroundColor Yellow
$frontendDir = "$ProjectRoot\frontend"
New-Item -ItemType Directory -Force -Path $frontendDir | Out-Null
if (-not (Test-Path "$frontendDir\index.html")) {
    $lines = @(
        "<!DOCTYPE html>",
        "<html><head><meta charset=utf-8>",
        "<meta http-equiv=refresh content='0;url=https://kank.com.tr'>",
        "</head><body>Yukleniyor...</body></html>"
    )
    $lines | Out-File "$frontendDir\index.html" -Encoding UTF8
}
Write-Host "  ✓ Frontend placeholder hazir" -ForegroundColor Green

# ── 5. Build ─────────────────────────────────────────────────
Write-Host "`n[5/5] .exe build basliyor (ilk build ~10-15 dk)..." -ForegroundColor Yellow
Set-Location $TauriDir
& cargo tauri build 2>&1
$exitCode = $LASTEXITCODE
Set-Location $ProjectRoot

if ($exitCode -eq 0) {
    $installer = Get-ChildItem "$TauriDir\target\release\bundle\nsis\*.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $installer) {
        $installer = Get-ChildItem "$TauriDir\target\release\bundle\msi\*.msi" -ErrorAction SilentlyContinue | Select-Object -First 1
    }
    Write-Host "`n=== BUILD BASARILI ===" -ForegroundColor Green
    if ($installer) {
        Write-Host "Installer: $($installer.FullName)" -ForegroundColor Cyan
        Write-Host "Boyut: $([math]::Round($installer.Length/1MB, 1)) MB" -ForegroundColor Cyan
    }
} else {
    Write-Host "`n=== BUILD HATASI (exit $exitCode) ===" -ForegroundColor Red
    Write-Host "Hata icin yukardaki ciktiya bakin." -ForegroundColor Yellow
}

# Build script for kurs-txt-package-v4.zip
# Wersja 4 – Kompletny pakiet kursu Koordynatora Reklamy
# Fundacja Werbekoordinator

param(
    [string]$OutputPath = ""
)

# Ustaw encoding na UTF-8
$PSDefaultParameterValues['Out-File:Encoding'] = 'utf8'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# Ustaw lokalizację na folder skryptu
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  BUILD KURS-TXT-PACKAGE-V4.ZIP" -ForegroundColor Cyan
Write-Host "  Fundacja Werbekoordinator" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""

# Określ ścieżkę wyjściową dla ZIP
if ($OutputPath -eq "") {
    $ZipPath = Join-Path $ScriptDir "kurs-txt-package-v4.zip"
} else {
    $ZipPath = $OutputPath
}

Write-Host "[1/5] Sprawdzanie struktury katalogów..." -ForegroundColor Yellow

# Sprawdź czy katalog kurs/ istnieje
$KursDir = Join-Path $ScriptDir "kurs"
if (-not (Test-Path $KursDir)) {
    Write-Host "BŁĄD: Brak katalogu kurs/" -ForegroundColor Red
    exit 1
}

Write-Host "  ✓ Katalog kurs/ istnieje" -ForegroundColor Green

# Lista wymaganych plików
$RequiredFiles = @(
    "kurs/Modul-02_Praktyka-Roli-KR.txt",
    "kurs/Modul-03_Pozyskanie-Partnera.txt",
    "kurs/Modul-04_Success-Fee.txt",
    "kurs/Modul-05_Prowadzenie-Partnera.txt",
    "kurs/Modul-06_Raportowanie-Analiza.txt",
    "kurs/Modul-07_Etyka-Komunikacja.txt",
    "kurs/Modul-08_Narzedzia-Techniki.txt",
    "kurs/Modul-09_Rozwoj-Kariery.txt",
    "kurs/Modul-10_Certyfikacja.txt",
    "kurs/Modul-TEST.txt",
    "kurs/Modul-TEST_Klucz.txt",
    "kurs/assets/config.js"
)

Write-Host ""
Write-Host "[2/5] Sprawdzanie wymaganych plików..." -ForegroundColor Yellow

$MissingFiles = @()
foreach ($File in $RequiredFiles) {
    $FullPath = Join-Path $ScriptDir $File
    if (Test-Path $FullPath) {
        Write-Host "  ✓ $File" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $File (BRAK)" -ForegroundColor Red
        $MissingFiles += $File
    }
}

if ($MissingFiles.Count -gt 0) {
    Write-Host ""
    Write-Host "BŁĄD: Brakuje $($MissingFiles.Count) plików!" -ForegroundColor Red
    Write-Host "Upewnij się, że wszystkie moduły zostały utworzone." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "[3/5] Sprawdzanie formularzy i materiałów..." -ForegroundColor Yellow

# Sprawdź formularze
$FormularzDir = Join-Path $KursDir "formularze"
if (-not (Test-Path $FormularzDir)) {
    Write-Host "  ⚠ Tworzę katalog kurs/formularze/" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $FormularzDir -Force | Out-Null
}

$Formularze = @(
    "kurs/formularze/Notatka-5-Pol.txt",
    "kurs/formularze/Rekomendacja-5-Zdan.txt",
    "kurs/formularze/Mini-Raport-3-Linijki.txt"
)

foreach ($Form in $Formularze) {
    $FullPath = Join-Path $ScriptDir $Form
    if (Test-Path $FullPath) {
        Write-Host "  ✓ $Form" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $Form (BRAK)" -ForegroundColor Red
        $MissingFiles += $Form
    }
}

# Sprawdź materiały
$MateriałyDir = Join-Path $KursDir "materialy"
if (-not (Test-Path $MateriałyDir)) {
    Write-Host "  ⚠ Tworzę katalog kurs/materialy/" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $MateriałyDir -Force | Out-Null
}

$Materiały = @(
    "kurs/materialy/Lista-100.csv"
)

foreach ($Mat in $Materiały) {
    $FullPath = Join-Path $ScriptDir $Mat
    if (Test-Path $FullPath) {
        Write-Host "  ✓ $Mat" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $Mat (BRAK)" -ForegroundColor Red
        $MissingFiles += $Mat
    }
}

if ($MissingFiles.Count -gt 0) {
    Write-Host ""
    Write-Host "BŁĄD: Brakuje $($MissingFiles.Count) plików!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "[4/5] Tworzenie archiwum ZIP..." -ForegroundColor Yellow

# Usuń stary ZIP jeśli istnieje
if (Test-Path $ZipPath) {
    Write-Host "  ⚠ Usuwam stary plik: $ZipPath" -ForegroundColor Yellow
    Remove-Item $ZipPath -Force
}

# Stwórz tymczasowy katalog dla pakietu
$TempDir = Join-Path $env:TEMP "kurs-txt-package-v4-$(Get-Date -Format 'yyyyMMddHHmmss')"
Write-Host "  → Tymczasowy katalog: $TempDir" -ForegroundColor Gray

if (Test-Path $TempDir) {
    Remove-Item $TempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

# Kopiuj pliki do tymczasowego katalogu
Write-Host "  → Kopiowanie plików..." -ForegroundColor Gray

# Kopiuj moduły
$ModulesDestDir = Join-Path $TempDir "kurs"
New-Item -ItemType Directory -Path $ModulesDestDir -Force | Out-Null

# Moduły 2-10
for ($i = 2; $i -le 10; $i++) {
    $Pattern = "kurs/Modul-$($i.ToString('00'))_*.txt"
    $Files = Get-ChildItem -Path $ScriptDir -Filter "Modul-$($i.ToString('00'))_*.txt" -Recurse | Where-Object { $_.FullName -like "*kurs*" }
    foreach ($File in $Files) {
        Copy-Item $File.FullName -Destination $ModulesDestDir
        Write-Host "    ✓ $($File.Name)" -ForegroundColor Green
    }
}

# Test i klucz
Copy-Item (Join-Path $KursDir "Modul-TEST.txt") -Destination $ModulesDestDir
Copy-Item (Join-Path $KursDir "Modul-TEST_Klucz.txt") -Destination $ModulesDestDir
Write-Host "    ✓ Modul-TEST.txt" -ForegroundColor Green
Write-Host "    ✓ Modul-TEST_Klucz.txt" -ForegroundColor Green

# Config.js
$AssetsDestDir = Join-Path $ModulesDestDir "assets"
New-Item -ItemType Directory -Path $AssetsDestDir -Force | Out-Null
Copy-Item (Join-Path $KursDir "assets/config.js") -Destination $AssetsDestDir
Write-Host "    ✓ assets/config.js" -ForegroundColor Green

# Formularze
$FormularzDestDir = Join-Path $ModulesDestDir "formularze"
New-Item -ItemType Directory -Path $FormularzDestDir -Force | Out-Null
foreach ($Form in $Formularze) {
    $FileName = Split-Path $Form -Leaf
    $SourcePath = Join-Path $ScriptDir $Form
    Copy-Item $SourcePath -Destination $FormularzDestDir
    Write-Host "    ✓ formularze/$FileName" -ForegroundColor Green
}

# Materiały
$MateriałyDestDir = Join-Path $ModulesDestDir "materialy"
New-Item -ItemType Directory -Path $MateriałyDestDir -Force | Out-Null
foreach ($Mat in $Materiały) {
    $FileName = Split-Path $Mat -Leaf
    $SourcePath = Join-Path $ScriptDir $Mat
    Copy-Item $SourcePath -Destination $MateriałyDestDir
    Write-Host "    ✓ materialy/$FileName" -ForegroundColor Green
}

# Utwórz plik README.txt
$ReadmePath = Join-Path $TempDir "README.txt"
$ReadmeContent = @"
KURS KOORDYNATORA REKLAMY - PAKIET V4
Fundacja Werbekoordinator
=====================================

Ten pakiet zawiera kompletne materiały kursu:

MODUŁY:
  - Modul-02_Praktyka-Roli-KR.txt
  - Modul-03_Pozyskanie-Partnera.txt
  - Modul-04_Success-Fee.txt (oparty na oficjalnym Planie Wynagrodzeń)
  - Modul-05_Prowadzenie-Partnera.txt
  - Modul-06_Raportowanie-Analiza.txt
  - Modul-07_Etyka-Komunikacja.txt
  - Modul-08_Narzedzia-Techniki.txt
  - Modul-09_Rozwoj-Kariery.txt
  - Modul-10_Certyfikacja.txt

TEST:
  - Modul-TEST.txt (40 pytań)
  - Modul-TEST_Klucz.txt (odpowiedzi)

FORMULARZE (kurs/formularze/):
  - Notatka-5-Pol.txt
  - Rekomendacja-5-Zdan.txt
  - Mini-Raport-3-Linijki.txt

MATERIAŁY (kurs/materialy/):
  - Lista-100.csv (szablon bazy Partnerów)

KONFIGURACJA:
  - kurs/assets/config.js (konfiguracja wszystkich modułów)

INSTRUKCJA:
1. Rozpakuj archiwum do folderu serwera gdzie znajduje się index.html
2. Upewnij się, że wszystkie pliki .txt są w katalogu kurs/
3. Otwórz index.html w przeglądarce
4. Wszystkie moduły powinny być dostępne w nawigacji

UWAGI:
- Wszystkie pliki w UTF-8 (bez BOM)
- Format .txt dla modułów 2-10 (zgodnie z wymaganiami)
- Moduł 1 pozostaje w formacie .md (już na serwerze)

Data utworzenia: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
Wersja: 4.0
"@

Set-Content -Path $ReadmePath -Value $ReadmeContent -Encoding UTF8

Write-Host "    ✓ README.txt" -ForegroundColor Green

# Kompresuj do ZIP
Write-Host "  → Kompresowanie do ZIP..." -ForegroundColor Gray
Compress-Archive -Path "$TempDir\*" -DestinationPath $ZipPath -Force

# Usuń tymczasowy katalog
Remove-Item $TempDir -Recurse -Force

$ZipSize = (Get-Item $ZipPath).Length / 1KB
Write-Host "  ✓ Utworzono: $ZipPath ($([math]::Round($ZipSize, 2)) KB)" -ForegroundColor Green

Write-Host ""
Write-Host "[5/5] Weryfikacja zawartości ZIP..." -ForegroundColor Yellow

# Pokaż zawartość ZIP
Add-Type -AssemblyName System.IO.Compression.FileSystem
$Zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
$FileCount = $Zip.Entries.Count
Write-Host "  → Liczba plików w archiwum: $FileCount" -ForegroundColor Gray

$ModulesCount = ($Zip.Entries | Where-Object { $_.Name -like "Modul-*.txt" }).Count
$FormularzCount = ($Zip.Entries | Where-Object { $_.FullName -like "*/formularze/*" }).Count
$MateriałyCount = ($Zip.Entries | Where-Object { $_.FullName -like "*/materialy/*" }).Count

Write-Host "    • Moduły (.txt): $ModulesCount" -ForegroundColor Cyan
Write-Host "    • Formularze: $FormularzCount" -ForegroundColor Cyan
Write-Host "    • Materiały: $MateriałyCount" -ForegroundColor Cyan

$Zip.Dispose()

Write-Host ""
Write-Host "==================================================" -ForegroundColor Green
Write-Host "  ✓ BUILD ZAKOŃCZONY SUKCESEM!" -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Plik ZIP gotowy do wgrania:" -ForegroundColor White
Write-Host "  $ZipPath" -ForegroundColor Cyan
Write-Host ""
Write-Host "Następne kroki:" -ForegroundColor White
Write-Host "  1. Wgraj ZIP na serwer" -ForegroundColor Gray
Write-Host "  2. Rozpakuj w katalogu głównym kursu" -ForegroundColor Gray
Write-Host "  3. Otwórz index.html i sprawdź, czy wszystkie moduły się ładują" -ForegroundColor Gray
Write-Host ""

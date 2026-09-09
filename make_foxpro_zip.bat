@echo off
setlocal

set "SOURCE_DIR=%~dp0foxpro"
set "STAMP="
for /f %%I in ('powershell.exe -NoProfile -NonInteractive -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%I"
set "ZIP_PATH=%~dp0instalari_offline_ecogest_foxpro_%STAMP%.zip"

if not exist "%SOURCE_DIR%\" (
    echo Folderul foxpro nu exista: "%SOURCE_DIR%"
    exit /b 1
)

set "FOXPRO_SOURCE=%SOURCE_DIR%"
set "FOXPRO_ZIP=%ZIP_PATH%"
powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$source=[Environment]::GetEnvironmentVariable('FOXPRO_SOURCE'); $zip=[Environment]::GetEnvironmentVariable('FOXPRO_ZIP'); $stage=Join-Path ([System.IO.Path]::GetTempPath()) ('foxpro_pack_' + [guid]::NewGuid().ToString('N')); $stagedProject=Join-Path $stage 'github\instalari_offline_ecogest\foxpro'; try { Add-Type -AssemblyName System.IO.Compression.FileSystem; New-Item -ItemType Directory -Force -Path $stagedProject | Out-Null; Copy-Item -Path (Join-Path $source '*') -Destination $stagedProject -Recurse -Force; $snapshots=Join-Path $stagedProject 'app\storage\dbf_snapshots'; if (Test-Path -LiteralPath $snapshots) { Remove-Item -LiteralPath $snapshots -Recurse -Force }; if (Test-Path -LiteralPath $zip) { Remove-Item -LiteralPath $zip -Force }; [System.IO.Compression.ZipFile]::CreateFromDirectory($stage, $zip, [System.IO.Compression.CompressionLevel]::Optimal, $false) } finally { if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force } }"
if errorlevel 1 (
    echo Arhiva nu a putut fi creata.
    exit /b 1
)

echo Arhiva creata: "%ZIP_PATH%"
endlocal

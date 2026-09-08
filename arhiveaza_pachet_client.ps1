[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$ClientName,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$ApplicationRelativePath,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$SourceRelativePath
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$clientSource = Join-Path $root $ClientName
$applicationSource = Join-Path $clientSource $ApplicationRelativePath
$sourceToExclude = Join-Path $applicationSource $SourceRelativePath
$outputDirectory = Join-Path $root '_pachete_clienti'
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$archivePath = Join-Path $outputDirectory ($ClientName + '_offline_' + $timestamp + '.zip')
$hashPath = $archivePath + '.sha256'
$workingDirectory = Join-Path $outputDirectory ('.temp_' + $ClientName + '_' + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $workingDirectory 'instalari_offline_ecogest'
$clientDestination = Join-Path $packageRoot $ClientName
$installedClientPath = Join-Path 'C:\xampp\htdocs\github\instalari_offline_ecogest' $ClientName
$installedApplicationPath = Join-Path $installedClientPath $ApplicationRelativePath

function Stop-Package {
    param([string]$Message)

    throw $Message
}

function Clear-RuntimeDirectory {
    param([System.IO.DirectoryInfo]$Directory)

    Get-ChildItem -LiteralPath $Directory.FullName -Force -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction Stop
}

function Get-Sha256Hex {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    $stream = [System.IO.File]::OpenRead($Path)
    $algorithm = [System.Security.Cryptography.SHA256]::Create()
    try {
        $hashBytes = $algorithm.ComputeHash($stream)
        return ([System.BitConverter]::ToString($hashBytes)).Replace('-', '')
    }
    finally {
        $algorithm.Dispose()
        $stream.Dispose()
    }
}

try {
    if (-not (Test-Path -LiteralPath $clientSource -PathType Container)) {
        Stop-Package "Folderul clientului nu exista: $clientSource"
    }

    if (-not (Test-Path -LiteralPath $applicationSource -PathType Container)) {
        Stop-Package "Folderul aplicatiei compilate nu exista: $applicationSource"
    }

    $compiledExecutables = @(Get-ChildItem -LiteralPath $applicationSource -File -Filter '*.exe' -ErrorAction SilentlyContinue)
    if ($compiledExecutables.Count -eq 0) {
        Stop-Package "Lipseste executabilul compilat in $applicationSource. Compileaza aplicatia in acest folder si ruleaza din nou arhivarea."
    }

    $externalDataDirectory = Join-Path $applicationSource 'Data'
    if (-not (Test-Path -LiteralPath $externalDataDirectory -PathType Container)) {
        Stop-Package "Lipseste folderul extern Data in $applicationSource. Recompileaza aplicatia cu resursele externe si ruleaza din nou arhivarea."
    }

    $activeDatabases = @(
        Get-ChildItem -LiteralPath $clientSource -Recurse -File -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -eq 'pos.db' -or
                $_.Name -eq 'restaurant.sqlite'
            } |
            Where-Object {
                $_.FullName -notlike ($sourceToExclude + '*') -and
                $_.Name -notlike 'backup_*'
            }
    )
    if ($activeDatabases.Count -eq 0) {
        Stop-Package "Nu a fost gasita baza locala activa pos.db sau restaurant.sqlite in instalarea $ClientName."
    }

    New-Item -ItemType Directory -Path $clientDestination -Force | Out-Null

    $excludedDirectories = @($sourceToExclude)
    $excludedDirectories += @(
        Get-ChildItem -LiteralPath $clientSource -Recurse -Directory -Force -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -eq 'vechi_propuse_spre_eliminare' -or
                $_.Name -eq '.playwright-cli' -or
                ($ClientName -eq 'bestmixt' -and $_.Name -like 'backups_update_bestmixt_*')
            } |
            Select-Object -ExpandProperty FullName
    )
    $excludedDirectories = @($excludedDirectories | Sort-Object -Unique)

    $robocopyArguments = @(
        $clientSource,
        $clientDestination,
        '/E',
        '/COPY:DAT',
        '/DCOPY:DAT',
        '/R:1',
        '/W:1',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/NP',
        '/XD'
    )
    $robocopyArguments += $excludedDirectories
    $robocopyArguments += @(
        '/XF',
        '*.exop',
        'Admin Login.url',
        '*.log',
        '*.bak',
        '*.pdb',
        'backup_*.db',
        'CONFIGURARE_CENTRALA_CLIENT_*.sql',
        'DOCUMENTATIE_INSTALARE_OFFLINE.md',
        'offline_installation_identity.json',
        'offline_installation_identity.json.lock',
        'Thumbs.db',
        '.DS_Store'
    )

    Write-Host "Pregatesc pachetul pentru $ClientName..." -ForegroundColor Cyan
    & robocopy @robocopyArguments | Out-Null
    $robocopyExitCode = $LASTEXITCODE
    if ($robocopyExitCode -gt 7) {
        Stop-Package "Copierea fisierelor a esuat. Cod Robocopy: $robocopyExitCode"
    }

    $runtimeDirectoryNames = @(
        'bonuri_backup',
        'bonuri_trimise',
        'offline_sync_exports',
        'backups_transaction_reset',
        'backups_products_sync',
        'licenta',
        'ScannedNotes',
        'Logs'
    )
    Get-ChildItem -LiteralPath $clientDestination -Recurse -Directory -Force -ErrorAction SilentlyContinue |
        Where-Object { $runtimeDirectoryNames -contains $_.Name } |
        ForEach-Object { Clear-RuntimeDirectory -Directory $_ }

    Get-ChildItem -LiteralPath $clientDestination -Directory -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like 'api_offline*' } |
        ForEach-Object {
            Get-ChildItem -LiteralPath $_.FullName -Directory -Force -ErrorAction SilentlyContinue |
                Where-Object { $_.Name -match '^\d+$' } |
                ForEach-Object {
                    Get-ChildItem -LiteralPath $_.FullName -Recurse -File -Force -ErrorAction SilentlyContinue |
                        Remove-Item -Force -ErrorAction Stop
                }
        }

    $destinationApplication = Join-Path $clientDestination $ApplicationRelativePath
    if (@(Get-ChildItem -LiteralPath $destinationApplication -File -Filter '*.exe' -ErrorAction SilentlyContinue).Count -eq 0) {
        Stop-Package 'Executabilul compilat nu a ajuns in pachet.'
    }
    if (-not (Test-Path -LiteralPath (Join-Path $destinationApplication 'Data') -PathType Container)) {
        Stop-Package 'Folderul extern Data nu a ajuns in pachet.'
    }
    if (Test-Path -LiteralPath (Join-Path $destinationApplication $SourceRelativePath)) {
        Stop-Package 'Folderul cu sursele PHP ale interfetei a ajuns neasteptat in pachet.'
    }

    $restaurantScannerSettingsFiles = @(
        Get-ChildItem -LiteralPath $clientDestination -Recurse -File -Filter 'settings.json' -ErrorAction SilentlyContinue |
            Where-Object {
                $_.FullName -like '*autoscanner*products*restaurant*' -or
                $_.FullName -like '*autoscanneragecsproducts*restaurant*'
            }
    )
    foreach ($scannerSettingsFile in $restaurantScannerSettingsFiles) {
        try {
            $scannerSettings = Get-Content -LiteralPath $scannerSettingsFile.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($null -eq $scannerSettings.PSObject.Properties['RestaurantPath']) {
                continue
            }

            $scannerSettings.RestaurantPath = $installedApplicationPath
            $scannerSettings | ConvertTo-Json -Depth 20 |
                Set-Content -LiteralPath $scannerSettingsFile.FullName -Encoding UTF8
        }
        catch {
            Stop-Package ("Configurarea AutoScannerului nu a putut fi adaptata pentru pachet: " + $scannerSettingsFile.FullName + ". " + $_.Exception.Message)
        }
    }

    foreach ($scannerSettingsFile in $restaurantScannerSettingsFiles) {
        $packagedSettings = Get-Content -LiteralPath $scannerSettingsFile.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($packagedSettings.RestaurantPath -ne $installedApplicationPath) {
            Stop-Package ("AutoScannerul nu indica folderul aplicatiei compilate: " + $scannerSettingsFile.FullName)
        }
        if ([string]$packagedSettings.RestaurantPath -like ('*\\' + $SourceRelativePath)) {
            Stop-Package ("AutoScannerul indica inca folderul exclus al surselor PHP: " + $scannerSettingsFile.FullName)
        }
    }

    $storeScannerSettingsFiles = @(
        Get-ChildItem -LiteralPath $clientDestination -Recurse -File -Filter 'settings.json' -ErrorAction SilentlyContinue |
            Where-Object {
                $_.FullName -like '*autoscanner*products*magazin*' -or
                $_.FullName -like '*autoscanneragecsproducts*magazin*'
            }
    )
    if ($storeScannerSettingsFiles.Count -gt 0) {
        $packagedApiDirectories = @(
            Get-ChildItem -LiteralPath $clientDestination -Directory -Force -ErrorAction SilentlyContinue |
                Where-Object { $_.Name -like 'api_offline*' }
        )
        if ($packagedApiDirectories.Count -ne 1) {
            Stop-Package ("Instalarea de magazin trebuie sa contina exact un folder api_offline*. Au fost gasite: " + $packagedApiDirectories.Count)
        }

        $packagedApiDirectory = $packagedApiDirectories[0]
        $packagedDatabasePath = Join-Path $packagedApiDirectory.FullName 'db_local\pos.db'
        if (-not (Test-Path -LiteralPath $packagedDatabasePath -PathType Leaf)) {
            Stop-Package ("Lipseste baza SQLite a magazinului din pachet: " + $packagedDatabasePath)
        }

        $installedApiPath = Join-Path $installedClientPath $packagedApiDirectory.Name
        $installedDatabasePath = Join-Path $installedApiPath 'db_local\pos.db'

        foreach ($scannerSettingsFile in $storeScannerSettingsFiles) {
            try {
                $scannerSettings = Get-Content -LiteralPath $scannerSettingsFile.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
                if ($null -eq $scannerSettings.PSObject.Properties['OfflineApiPath']) {
                    Stop-Package ("Configurarea AutoScannerului de magazin nu contine OfflineApiPath: " + $scannerSettingsFile.FullName)
                }
                if ($null -eq $scannerSettings.PSObject.Properties['DatabasePath']) {
                    Stop-Package ("Configurarea AutoScannerului de magazin nu contine DatabasePath: " + $scannerSettingsFile.FullName)
                }

                $scannerSettings.OfflineApiPath = $installedApiPath
                $scannerSettings.DatabasePath = $installedDatabasePath
                $scannerSettings | ConvertTo-Json -Depth 20 |
                    Set-Content -LiteralPath $scannerSettingsFile.FullName -Encoding UTF8
            }
            catch {
                Stop-Package ("Configurarea AutoScannerului de magazin nu a putut fi adaptata pentru pachet: " + $scannerSettingsFile.FullName + ". " + $_.Exception.Message)
            }
        }

        foreach ($scannerSettingsFile in $storeScannerSettingsFiles) {
            $packagedSettings = Get-Content -LiteralPath $scannerSettingsFile.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($packagedSettings.OfflineApiPath -ne $installedApiPath) {
                Stop-Package ("AutoScannerul de magazin nu indica API-ul local al clientului: " + $scannerSettingsFile.FullName)
            }
            if ($packagedSettings.DatabasePath -ne $installedDatabasePath) {
                Stop-Package ("AutoScannerul de magazin nu indica baza pos.db a clientului: " + $scannerSettingsFile.FullName)
            }
            if ([string]$packagedSettings.OfflineApiPath -like '*\interfata_vanzare*' -or
                [string]$packagedSettings.DatabasePath -like '*\interfata_vanzare*' -or
                [string]$packagedSettings.OfflineApiPath -like '*\app_restaurant_v2*' -or
                [string]$packagedSettings.DatabasePath -like '*\app_restaurant_v2*') {
                Stop-Package ("AutoScannerul de magazin indica un folder de surse PHP in locul API-ului extern: " + $scannerSettingsFile.FullName)
            }
        }
    }

    $instructions = @(
        'PACHET APLICATIE OFFLINE ECOGEST',
        '',
        "Client: $ClientName",
        "Generat: $(Get-Date -Format 'dd.MM.yyyy HH:mm:ss')",
        '',
        'Cerinta: XAMPP trebuie sa fie deja instalat pe calculatorul clientului.',
        'Extragere: continutul arhivei se extrage in C:\xampp\htdocs\github.',
        'Calea finala trebuie sa fie C:\xampp\htdocs\github\instalari_offline_ecogest\' + $ClientName + '.',
        '',
        'Pachetul include executabilul compilat, resursele externe Data, baza SQLite, API-ul local,',
        'configurarea clientului, autoscanerul, aplicatiile auxiliare si shortcuturile existente.',
        'Sursele PHP ale interfetei compilate si datele temporare de executie nu sunt incluse.',
        'Identitatea instalarii, identitatea hardware si starea licentei se genereaza separat pe calculatorul clientului.'
    )
    Set-Content -LiteralPath (Join-Path $clientDestination 'PACHET_INSTALARE.txt') -Value $instructions -Encoding ASCII

    New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $workingDirectory,
        $archivePath,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )

    $hash = Get-Sha256Hex -Path $archivePath
    Set-Content -LiteralPath $hashPath -Value ($hash + '  ' + [System.IO.Path]::GetFileName($archivePath)) -Encoding ASCII

    $archive = Get-Item -LiteralPath $archivePath
    Write-Host ''
    Write-Host 'Pachet creat cu succes.' -ForegroundColor Green
    Write-Host ('Arhiva: ' + $archive.FullName)
    Write-Host ('Dimensiune: ' + [math]::Round($archive.Length / 1MB, 2) + ' MB')
    Write-Host ('SHA256: ' + $hash)
}
catch {
    if (Test-Path -LiteralPath $hashPath) {
        Remove-Item -LiteralPath $hashPath -Force -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force -ErrorAction SilentlyContinue
    }
    Write-Host ''
    Write-Host ('EROARE: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
finally {
    if (Test-Path -LiteralPath $workingDirectory) {
        Remove-Item -LiteralPath $workingDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }
}

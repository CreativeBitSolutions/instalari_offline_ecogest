[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$clientName = 'taverna_amicii'
$applicationRelativePath = 'taverna_amicii'
$sourceRelativePath = 'app_restaurant_v2'
$clientSource = Join-Path $root $clientName
$applicationSource = Join-Path $clientSource $applicationRelativePath
$sourceToExclude = Join-Path $applicationSource $sourceRelativePath
$compilerProject = Join-Path $clientSource 'compiler exe project taverna.exop'
$outputDirectory = Join-Path $root '_pachete_clienti'
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$archivePath = Join-Path $outputDirectory ($clientName + '_update_' + $timestamp + '.zip')
$hashPath = $archivePath + '.sha256'
$workingDirectory = Join-Path $outputDirectory ('.temp_update_' + $clientName + '_' + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $workingDirectory 'instalari_offline_ecogest'
$clientDestination = Join-Path $packageRoot $clientName

function Stop-Package {
    param([Parameter(Mandatory = $true)][string]$Message)
    throw $Message
}

function Assert-PathInside {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Parent
    )

    $resolvedParent = [System.IO.Path]::GetFullPath($Parent).TrimEnd('\') + '\'
    $resolvedPath = [System.IO.Path]::GetFullPath($Path)
    if (-not $resolvedPath.StartsWith($resolvedParent, [System.StringComparison]::OrdinalIgnoreCase)) {
        Stop-Package "Calea temporara nu este in folderul permis: $resolvedPath"
    }
}

try {
    if (-not (Test-Path -LiteralPath $clientSource -PathType Container)) {
        Stop-Package "Folderul instalarii nu exista: $clientSource"
    }

    if (-not (Test-Path -LiteralPath $applicationSource -PathType Container)) {
        Stop-Package "Folderul aplicatiei compilate nu exista: $applicationSource"
    }

    if (@(Get-ChildItem -LiteralPath $applicationSource -File -Filter '*.exe' -ErrorAction SilentlyContinue).Count -eq 0) {
        Stop-Package "Lipseste executabilul compilat. Ruleaza Recompile si Build, apoi porneste din nou acest script."
    }

    $externalDataDirectory = Join-Path $applicationSource 'Data'
    if (-not (Test-Path -LiteralPath $externalDataDirectory -PathType Container)) {
        Stop-Package "Lipseste folderul extern Data. Ruleaza Recompile si Build, apoi porneste din nou acest script."
    }

    if (-not (Test-Path -LiteralPath $compilerProject -PathType Leaf)) {
        Stop-Package "Lipseste proiectul de compilare: $compilerProject"
    }

    $requiredProjectFiles = @(
        'asteapta_casa_marcat.php',
        'asteapta_imprimanta.php',
        'offline_fiscal_inp_alternative.php',
        'offline_print_alternative.php',
        'offline_printer_flow_helper.php'
    )
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $projectArchive = [System.IO.Compression.ZipFile]::OpenRead($compilerProject)
    try {
        $filesEntry = $projectArchive.GetEntry('files.xml')
        if ($null -eq $filesEntry) {
            Stop-Package 'Proiectul de compilare nu contine files.xml.'
        }
        $projectReader = [System.IO.StreamReader]::new($filesEntry.Open())
        try {
            [xml]$projectFilesXml = $projectReader.ReadToEnd()
        }
        finally {
            $projectReader.Dispose()
        }
        $projectFilePaths = @($projectFilesXml.SelectNodes('//file') | ForEach-Object { [string]$_.path })
        $missingProjectFiles = @(
            foreach ($requiredProjectFile in $requiredProjectFiles) {
                if ($projectFilePaths -inotcontains $requiredProjectFile) {
                    $requiredProjectFile
                }
            }
        )
    }
    finally {
        $projectArchive.Dispose()
    }
    if ($missingProjectFiles.Count -gt 0) {
        Stop-Package ("Proiectul de compilare nu include toate fisierele PHP noi:`r`n" + ($missingProjectFiles -join "`r`n"))
    }

    New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
    Assert-PathInside -Path $workingDirectory -Parent $outputDirectory
    New-Item -ItemType Directory -Path $clientDestination -Force | Out-Null

    $runtimeDirectoryNames = @(
        'backups_products_sync',
        'backups_transaction_reset',
        'bonuri_backup',
        'bonuri_trimise',
        'licenta',
        'Logs',
        'logs',
        'offline_sync_exports',
        'raspunsuri_casa_marcat',
        'raspunsuri_casa_marcat_procesate',
        'ScannedNotes',
        'vechi_propuse_spre_eliminare',
        '.playwright-cli'
    )

    $excludedDirectories = @($sourceToExclude)
    $excludedDirectories += @(
        Get-ChildItem -LiteralPath $clientSource -Recurse -Directory -Force -ErrorAction SilentlyContinue |
            Where-Object {
                $runtimeDirectoryNames -contains $_.Name -or
                ($_.Parent.FullName -like (Join-Path $clientSource 'api_offline*') -and $_.Name -match '^\d+$')
            } |
            Select-Object -ExpandProperty FullName
    )
    $excludedDirectories = @($excludedDirectories | Sort-Object -Unique)

    # Aceste fisiere apartin instalarii active a clientului si nu intra niciodata in update.
    $protectedFileNames = @(
        '.htaccess',
        'appsettings.json',
        'config.json',
        'hardware_identity.json',
        'license_runtime.json',
        'license_state.json',
        'offline_config.local.php',
        'offline_installation_identity.json',
        'offline_installation_identity.json.lock',
        'printer_format.json',
        'restaurant.sqlite',
        'restaurant.sqlite-shm',
        'restaurant.sqlite-wal',
        'pos.db',
        'pos.db-shm',
        'pos.db-wal',
        'settings.json'
    )

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
        '*.lnk',
        '*.url',
        '*.log',
        '*.bak',
        '*.pdb',
        '*.lock',
        'Thumbs.db',
        '.DS_Store'
    )
    $robocopyArguments += $protectedFileNames

    Write-Host 'Pregatesc pachetul de UPDATE pentru Taverna Amicii...' -ForegroundColor Cyan
    & robocopy @robocopyArguments | Out-Null
    $robocopyExitCode = $LASTEXITCODE
    if ($robocopyExitCode -gt 7) {
        Stop-Package "Copierea fisierelor a esuat. Cod Robocopy: $robocopyExitCode"
    }

    $destinationApplication = Join-Path $clientDestination $applicationRelativePath
    if (@(Get-ChildItem -LiteralPath $destinationApplication -File -Filter '*.exe' -ErrorAction SilentlyContinue).Count -eq 0) {
        Stop-Package 'Executabilul compilat nu a ajuns in pachetul de update.'
    }
    if (-not (Test-Path -LiteralPath (Join-Path $destinationApplication 'Data') -PathType Container)) {
        Stop-Package 'Folderul Data nu a ajuns in pachetul de update.'
    }
    if (Test-Path -LiteralPath (Join-Path $destinationApplication $sourceRelativePath)) {
        Stop-Package 'Sursele app_restaurant_v2 au ajuns neasteptat in pachet.'
    }

    $forbiddenFiles = @(
        Get-ChildItem -LiteralPath $clientDestination -Recurse -File -Force -ErrorAction SilentlyContinue |
            Where-Object {
                $protectedFileNames -contains $_.Name -or
                $_.Extension -in @('.exop', '.lnk', '.url', '.log', '.bak', '.pdb', '.lock')
            }
    )
    if ($forbiddenFiles.Count -gt 0) {
        $relativeForbidden = $forbiddenFiles | ForEach-Object { $_.FullName.Substring($clientDestination.Length + 1) }
        Stop-Package ("Pachetul contine fisiere locale protejate:`r`n" + ($relativeForbidden -join "`r`n"))
    }

    $forbiddenDirectories = @(
        Get-ChildItem -LiteralPath $clientDestination -Recurse -Directory -Force -ErrorAction SilentlyContinue |
            Where-Object {
                $runtimeDirectoryNames -contains $_.Name -or
                ($_.Parent.FullName -like (Join-Path $clientDestination 'api_offline*') -and $_.Name -match '^\d+$')
            }
    )
    if ($forbiddenDirectories.Count -gt 0) {
        $relativeForbidden = $forbiddenDirectories | ForEach-Object { $_.FullName.Substring($clientDestination.Length + 1) }
        Stop-Package ("Pachetul contine directoare operationale protejate:`r`n" + ($relativeForbidden -join "`r`n"))
    }

    $instructions = @(
        'PACHET UPDATE ECOGEST OFFLINE, TAVERNA AMICII',
        '',
        "Generat: $(Get-Date -Format 'dd.MM.yyyy HH:mm:ss')",
        '',
        '1. Faceti o copie de siguranta a instalarii existente.',
        '2. Inchideti aplicatia ECOGEST si toate scanerele locale.',
        '3. Extrageti continutul arhivei in C:\xampp\htdocs\github si acceptati inlocuirea fisierelor existente.',
        '4. Nu stergeti folderul instalarii inainte de copiere.',
        '5. Porniti XAMPP, aplicatia ECOGEST si scanerele locale.',
        '',
        'Acest pachet NU contine si NU inlocuieste:',
        '- restaurant.sqlite, fisierele WAL sau SHM',
        '- configurarea AutoScannerului, imprimantelor, casei de marcat sau VeriBon',
        '- identitatea instalarii si starea licentei',
        '- cozile, bonurile, raspunsurile, backupurile si logurile existente',
        '- shortcuturile, sursele app_restaurant_v2 si proiectul .exop',
        '',
        'Modificarile de schema sunt aplicate de ensure schema din aplicatia recompilata peste baza existenta.'
    )
    Set-Content -LiteralPath (Join-Path $clientDestination 'PACHET_UPDATE.txt') -Value $instructions -Encoding ASCII

    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $workingDirectory,
        $archivePath,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )

    $hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash
    Set-Content -LiteralPath $hashPath -Value ($hash + '  ' + [System.IO.Path]::GetFileName($archivePath)) -Encoding ASCII

    $archive = Get-Item -LiteralPath $archivePath
    Write-Host ''
    Write-Host 'Pachetul de update a fost creat cu succes.' -ForegroundColor Green
    Write-Host ('Arhiva: ' + $archive.FullName)
    Write-Host ('Dimensiune: ' + [math]::Round($archive.Length / 1MB, 2) + ' MB')
    Write-Host ('SHA256: ' + $hash)
    Write-Host 'Baza SQLite si configurarile locale nu sunt incluse.' -ForegroundColor Yellow
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
        Assert-PathInside -Path $workingDirectory -Parent $outputDirectory
        Remove-Item -LiteralPath $workingDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }
}

#requires -version 5.1

[CmdletBinding()]
param(
    [Parameter()]
    [string]$OutputPath = (Join-Path $PSScriptRoot 'Resurse'),

    [Parameter()]
    [switch]$Force,

    [Parameter(DontShow = $true)]
    [int]$ClientIdValue = 0,

    [Parameter(DontShow = $true)]
    [Security.SecureString]$ApiKeySecure
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$formatVersion = 1
$authenticationPrefix = 'AGECS-RESURSE|1|'
$encryptionKeyMaterial = 'Bg5Lx8Ngf0XCXpvClotEtXowMYycqX4BWYweHX6Is7o='
$authenticationKeyMaterial = 'YctU/ZxAeASjnLrKsjcJK8Ms8kD4SGKpBUMW9D6MkSM='

function ConvertFrom-ApiKeySecureString {
    param(
        [Parameter(Mandatory = $true)]
        [Security.SecureString]$SecureValue
    )

    $pointer = [IntPtr]::Zero
    try {
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        if ($pointer -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        }
    }
}

function Read-ClientId {
    while ($true) {
        $value = Read-Host 'ClientId'
        $parsed = 0
        if ([int]::TryParse($value, [ref]$parsed) -and $parsed -gt 0) {
            return $parsed
        }

        Write-Host 'ClientId trebuie să fie un număr întreg mai mare decât zero.' -ForegroundColor Yellow
    }
}

function Read-ApiKeyFromClipboard {
    while ($true) {
        Write-Host ''
        Write-Host 'Copiază cheia API în Clipboard, apoi revino în această fereastră.' -ForegroundColor Cyan
        Read-Host 'Apasă ENTER pentru preluarea cheii' | Out-Null

        try {
            $plainValue = Get-Clipboard -Raw
        }
        catch {
            $plainValue = $null
        }

        if (-not [string]::IsNullOrWhiteSpace($plainValue) -and $plainValue.Trim().Length -le 4096) {
            try {
                Set-Clipboard -Value ([string]::Empty)
                Write-Host 'Cheia a fost preluată, iar Clipboard-ul a fost golit.' -ForegroundColor Green
            }
            catch {
                Write-Host 'Cheia a fost preluată. Clipboard-ul nu a putut fi golit automat.' -ForegroundColor Yellow
            }
            return $plainValue.Trim()
        }

        $plainValue = $null
        Write-Host 'Clipboard-ul nu conține o cheie validă. Copiază cheia și încearcă din nou.' -ForegroundColor Yellow
    }
}

function Join-ByteArrays {
    param(
        [Parameter(Mandatory = $true)]
        [byte[][]]$Arrays
    )

    $length = 0
    foreach ($array in $Arrays) {
        $length += $array.Length
    }

    $result = New-Object byte[] $length
    $offset = 0
    foreach ($array in $Arrays) {
        [Buffer]::BlockCopy($array, 0, $result, $offset, $array.Length)
        $offset += $array.Length
    }
    return $result
}

$apiKey = $null
$payloadBytes = $null
$encryptionKey = $null
$authenticationKey = $null
$iv = $null
$ciphertext = $null
$mac = $null
$authenticatedData = $null

try {
    if ($ClientIdValue -gt 0 -and $null -ne $ApiKeySecure) {
        $clientId = $ClientIdValue
        $apiKey = ConvertFrom-ApiKeySecureString -SecureValue $ApiKeySecure
    }
    else {
        $clientId = if ($ClientIdValue -gt 0) { $ClientIdValue } else { Read-ClientId }
        $apiKey = Read-ApiKeyFromClipboard
    }
    if ([string]::IsNullOrWhiteSpace($apiKey) -or $apiKey.Length -gt 4096) {
        throw 'Cheia API nu poate fi goală și nu poate depăși 4096 de caractere.'
    }
    $apiKey = $apiKey.Trim()
    $fullOutputPath = [IO.Path]::GetFullPath($OutputPath)

    if ([IO.File]::Exists($fullOutputPath) -and -not $Force) {
        $confirmation = Read-Host "Fișierul există deja. Îl suprascrii? [da/NU]"
        if ($confirmation -notmatch '^(da|d|yes|y)$') {
            Write-Host 'Operația a fost anulată.' -ForegroundColor Yellow
            exit 2
        }
    }

    $payload = [ordered]@{
        Version = $formatVersion
        ClientId = $clientId
        ApiKey = $apiKey
        IssuedAtUtc = [DateTime]::UtcNow.ToString('o')
    }
    $payloadJson = $payload | ConvertTo-Json -Compress
    $payloadBytes = [Text.Encoding]::UTF8.GetBytes($payloadJson)

    $encryptionKey = [Convert]::FromBase64String($encryptionKeyMaterial)
    $authenticationKey = [Convert]::FromBase64String($authenticationKeyMaterial)

    $aes = [Security.Cryptography.Aes]::Create()
    try {
        $aes.KeySize = 256
        $aes.BlockSize = 128
        $aes.Mode = [Security.Cryptography.CipherMode]::CBC
        $aes.Padding = [Security.Cryptography.PaddingMode]::PKCS7
        $aes.Key = $encryptionKey
        $aes.GenerateIV()
        $iv = $aes.IV

        $encryptor = $aes.CreateEncryptor()
        try {
            $ciphertext = $encryptor.TransformFinalBlock($payloadBytes, 0, $payloadBytes.Length)
        }
        finally {
            $encryptor.Dispose()
        }
    }
    finally {
        $aes.Dispose()
    }

    $prefixBytes = [Text.Encoding]::UTF8.GetBytes($authenticationPrefix)
    $authenticatedData = Join-ByteArrays -Arrays @($prefixBytes, $iv, $ciphertext)
    $hmac = New-Object -TypeName Security.Cryptography.HMACSHA256 -ArgumentList (,$authenticationKey)
    try {
        $mac = $hmac.ComputeHash($authenticatedData)
    }
    finally {
        $hmac.Dispose()
        [Array]::Clear($prefixBytes, 0, $prefixBytes.Length)
    }

    $envelope = [ordered]@{
        Version = $formatVersion
        Iv = [Convert]::ToBase64String($iv)
        Ciphertext = [Convert]::ToBase64String($ciphertext)
        Mac = [Convert]::ToBase64String($mac)
    }
    $envelopeJson = ($envelope | ConvertTo-Json -Compress) + [Environment]::NewLine

    $outputDirectory = [IO.Path]::GetDirectoryName($fullOutputPath)
    if (-not [string]::IsNullOrWhiteSpace($outputDirectory)) {
        [IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
    }
    [IO.File]::WriteAllText($fullOutputPath, $envelopeJson, (New-Object Text.UTF8Encoding($false)))

    Write-Host ''
    Write-Host "Fișierul Resurse a fost creat pentru clientul $clientId." -ForegroundColor Green
    Write-Host "Locație: $fullOutputPath"
}
catch {
    Write-Error ("Fișierul Resurse nu a putut fi creat. " + $_.Exception.Message)
    exit 1
}
finally {
    $apiKey = $null
    $payloadJson = $null
    $payload = $null
    if ($payloadBytes) { [Array]::Clear($payloadBytes, 0, $payloadBytes.Length) }
    if ($encryptionKey) { [Array]::Clear($encryptionKey, 0, $encryptionKey.Length) }
    if ($authenticationKey) { [Array]::Clear($authenticationKey, 0, $authenticationKey.Length) }
    if ($iv) { [Array]::Clear($iv, 0, $iv.Length) }
    if ($ciphertext) { [Array]::Clear($ciphertext, 0, $ciphertext.Length) }
    if ($mac) { [Array]::Clear($mac, 0, $mac.Length) }
    if ($authenticatedData) { [Array]::Clear($authenticatedData, 0, $authenticatedData.Length) }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}

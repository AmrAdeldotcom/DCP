param(
    [Parameter(Mandatory = $true)][int]$ProcessId,
    [Parameter(Mandatory = $true)][string]$SourceRoot,
    [Parameter(Mandatory = $true)][string]$InstallRoot,
    [Parameter(Mandatory = $true)][string]$WorkRoot
)
$ErrorActionPreference = 'Stop'
$LogRoot = Join-Path $env:LOCALAPPDATA 'DCP'
$Log = Join-Path $LogRoot 'update.log'
New-Item -ItemType Directory -Force -Path $LogRoot | Out-Null
Start-Transcript -Path $Log -Append | Out-Null
try {
    Write-Host "Waiting for DCP process $ProcessId to exit..."
    try { Wait-Process -Id $ProcessId -Timeout 120 -ErrorAction Stop } catch {
        if (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue) { throw 'DCP did not exit within 120 seconds.' }
    }
    $ManifestPath = Join-Path $SourceRoot 'update-manifest.json'
    if (-not (Test-Path -LiteralPath $ManifestPath)) { throw 'Release manifest is missing.' }
    $Manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
    if ($Manifest.schema -ne 1 -or -not $Manifest.version -or $Manifest.files.Count -lt 1) {
        throw 'Release manifest is invalid.'
    }
    $BackupRoot = Join-Path $WorkRoot 'previous-install'
    $PayloadRoot = Join-Path $SourceRoot 'payload'
    $DataRoot = Join-Path $InstallRoot 'platform\data'
    $StorageRoot = Join-Path $InstallRoot 'platform\storage'
    New-Item -ItemType Directory -Force -Path $BackupRoot | Out-Null

    $Entries = @($Manifest.files)
    foreach ($Entry in $Entries) {
        $Relative = [string]$Entry.path
        if ([string]::IsNullOrWhiteSpace($Relative) -or
            [IO.Path]::IsPathRooted($Relative) -or
            $Relative -match '(^|[\\/])\.\.([\\/]|$)' -or
            $Relative -match '^[A-Za-z]:') { throw "Unsafe update path: $Relative" }
        $Destination = Join-Path $InstallRoot $Relative
        $Source = Join-Path $PayloadRoot $Relative
        if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { throw "Update payload is missing: $Relative" }
        $Hash = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($Hash -ne ([string]$Entry.sha256).ToLowerInvariant()) { throw "Checksum mismatch: $Relative" }
        if ((Get-Item -LiteralPath $Source).Length -ne [int64]$Entry.size) { throw "File size mismatch: $Relative" }
        if ($Relative -match '^(platform[\\/](data|storage)([\\/]|$))') {
            throw "Manifest is not allowed to replace user data: $Relative"
        }
    }

    $Applied = New-Object System.Collections.Generic.List[string]
    try {
        foreach ($Entry in $Entries) {
            $Relative = [string]$Entry.path
            $Destination = Join-Path $InstallRoot $Relative
            $Source = Join-Path $PayloadRoot $Relative
            $Parent = Split-Path -Parent $Destination
            New-Item -ItemType Directory -Force -Path $Parent | Out-Null
            if (Test-Path -LiteralPath $Destination -PathType Leaf) {
                $Backup = Join-Path $BackupRoot $Relative
                New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Backup) | Out-Null
                Copy-Item -LiteralPath $Destination -Destination $Backup -Force
            }
            $Applied.Add($Relative)
            Copy-Item -LiteralPath $Source -Destination $Destination -Force
        }
        $Php = Join-Path $InstallRoot 'runtime\php\php.exe'
        $App = Join-Path $InstallRoot 'DCP.exe'
        if (-not (Test-Path -LiteralPath $Php) -or -not (Test-Path -LiteralPath $App)) {
            throw 'Essential DCP runtime files are missing after the delta update.'
        }
        Write-Host ("Applied {0} changed files for release {1}." -f $Applied.Count, $Manifest.version)
    }
    catch {
        Write-Warning 'Delta update failed. Rolling back changed files.'
        foreach ($Relative in $Applied) {
            $Destination = Join-Path $InstallRoot $Relative
            $Backup = Join-Path $BackupRoot $Relative
            if (Test-Path -LiteralPath $Backup -PathType Leaf) {
                Copy-Item -LiteralPath $Backup -Destination $Destination -Force
            } else {
                Remove-Item -LiteralPath $Destination -Force -ErrorAction SilentlyContinue
            }
        }
        throw
    }

    Write-Host 'Delta update complete. Launching DCP.'
    Start-Process -FilePath $App -WorkingDirectory $InstallRoot
    Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue
}
catch {
    Write-Error ("DCP update failed: " + $_.Exception.Message)
    $App = Join-Path $InstallRoot 'DCP.exe'
    if (Test-Path -LiteralPath $App) { Start-Process -FilePath $App -WorkingDirectory $InstallRoot }
    if (Test-Path -LiteralPath $WorkRoot) { Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
}
finally {
    try { Stop-Transcript | Out-Null } catch { }
    if (Test-Path -LiteralPath $PSCommandPath) { Remove-Item -LiteralPath $PSCommandPath -Force -ErrorAction SilentlyContinue }
}

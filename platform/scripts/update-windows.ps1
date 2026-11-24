param(
    [Parameter(Mandatory = $true)][int]$ProcessId,
    [Parameter(Mandatory = $true)][string]$SourceRoot,
    [Parameter(Mandatory = $true)][string]$InstallRoot,
    [Parameter(Mandatory = $true)][string]$WorkRoot,
    [switch]$NoRestart
)

$ErrorActionPreference = 'Stop'
$LogRoot = Join-Path $env:LOCALAPPDATA 'DCP'
$Log = Join-Path $LogRoot 'update.log'
New-Item -ItemType Directory -Force -Path $LogRoot | Out-Null
Start-Transcript -Path $Log -Append | Out-Null

function Write-UpdateStatus([string]$Status, [string]$Version, [int]$Revision, [string]$Message) {
    $statusPath = Join-Path $LogRoot 'update-status.json'
    $temporary = $statusPath + '.tmp'
    [pscustomobject]@{
        status = $Status
        version = $Version
        packageRevision = $Revision
        message = $Message
        updatedAt = [DateTime]::UtcNow.ToString('o')
    } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $temporary -Encoding UTF8
    Move-Item -LiteralPath $temporary -Destination $statusPath -Force
}

function Test-SafeUpdatePath([string]$Relative, [string]$Root) {
    if ([string]::IsNullOrWhiteSpace($Relative) -or [IO.Path]::IsPathRooted($Relative) -or
        $Relative -match '(^|[\\/])\.\.([\\/]|$)' -or $Relative -match '^[A-Za-z]:' -or
        $Relative -match '^[\\/]' -or $Relative.Contains(':')) { return $false }
    if ($Relative -match '^(DCP-UserData|platform[\\/](data|storage))([\\/]|$)') { return $false }
    $rootFull = [IO.Path]::GetFullPath($Root).TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    $destination = [IO.Path]::GetFullPath((Join-Path $Root $Relative))
    if (-not $destination.StartsWith($rootFull, [StringComparison]::OrdinalIgnoreCase)) { return $false }
    $cursor = $Root
    foreach ($part in ($Relative -split '[\\/]')) {
        $cursor = Join-Path $cursor $part
        if (Test-Path -LiteralPath $cursor) {
            $item = Get-Item -LiteralPath $cursor -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { return $false }
        }
    }
    return $true
}

$Version = ''
$Revision = 0
$BackupRoot = Join-Path $WorkRoot 'previous-install'
$PayloadRoot = Join-Path $SourceRoot 'payload'
$Applied = New-Object System.Collections.Generic.List[string]
$RollbackComplete = $false

try {
    Write-Host "Waiting for DCP process $ProcessId to exit..."
    try { Wait-Process -Id $ProcessId -Timeout 120 -ErrorAction Stop } catch {
        if (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue) { throw 'DCP did not exit within 120 seconds.' }
    }

    $ManifestPath = Join-Path $SourceRoot 'update-manifest.json'
    if (-not (Test-Path -LiteralPath $ManifestPath -PathType Leaf)) { throw 'Release manifest is missing.' }
    $Manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
    if ($Manifest.schema -notin @(1, 2) -or [string]::IsNullOrWhiteSpace([string]$Manifest.version) -or
        @($Manifest.files).Count -lt 1 -or @($Manifest.files).Count -gt 5000) {
        throw 'Release manifest is invalid.'
    }
    $Version = [string]$Manifest.version
    if ($Manifest.schema -eq 2) {
        $Revision = [int]$Manifest.package_revision
        if ($Revision -lt 1) { throw 'Update package revision is invalid.' }
    }
    New-Item -ItemType Directory -Force -Path $BackupRoot, $PayloadRoot | Out-Null

    $Entries = @($Manifest.files)
    foreach ($Entry in $Entries) {
        $Relative = [string]$Entry.path
        if (-not (Test-SafeUpdatePath $Relative $InstallRoot)) { throw "Unsafe update path: $Relative" }
        $Source = Join-Path $PayloadRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
        if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { throw "Update payload is missing: $Relative" }
        $ExpectedHash = ([string]$Entry.sha256).ToLowerInvariant()
        if ($ExpectedHash -notmatch '^[0-9a-f]{64}$') { throw "Invalid SHA-256 in manifest: $Relative" }
        $Info = Get-Item -LiteralPath $Source
        if ([int64]$Entry.size -lt 0 -or $Info.Length -ne [int64]$Entry.size) { throw "File size mismatch: $Relative" }
        $Hash = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($Hash -ne $ExpectedHash) { throw "Checksum mismatch: $Relative" }
    }

    try {
        foreach ($Entry in $Entries) {
            $Relative = [string]$Entry.path
            $Destination = Join-Path $InstallRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Source = Join-Path $PayloadRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Parent = Split-Path -Parent $Destination
            New-Item -ItemType Directory -Force -Path $Parent | Out-Null
            if (Test-Path -LiteralPath $Destination -PathType Leaf) {
                $Backup = Join-Path $BackupRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
                New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Backup) | Out-Null
                Copy-Item -LiteralPath $Destination -Destination $Backup -Force
            }
            $Applied.Add($Relative)
            Copy-Item -LiteralPath $Source -Destination $Destination -Force
        }

        $Php = Join-Path $InstallRoot 'platform\dcp-env\runtime\php\windows-x64\php.exe'
        $App = Join-Path $InstallRoot 'DCP.exe'
        if (-not (Test-Path -LiteralPath $Php -PathType Leaf) -or -not (Test-Path -LiteralPath $App -PathType Leaf)) {
            throw 'Essential DCP runtime files are missing after the delta update.'
        }

        if (-not $NoRestart) {
            Write-Host 'Launching the updated DCP application for a startup check...'
            $Started = Start-Process -FilePath $App -WorkingDirectory $InstallRoot -PassThru
            Start-Sleep -Seconds 12
            if (-not (Get-Process -Id $Started.Id -ErrorAction SilentlyContinue)) {
                throw 'Updated DCP exited during the post-update startup check.'
            }
        }

        $RollbackRoot = Join-Path $LogRoot 'rollback'
        New-Item -ItemType Directory -Force -Path $RollbackRoot | Out-Null
        $BackupDestination = Join-Path $RollbackRoot ((Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ') + '-' + ($Version -replace '[^0-9A-Za-z.-]', '_'))
        if ((Get-ChildItem -LiteralPath $BackupRoot -Force -ErrorAction SilentlyContinue | Measure-Object).Count -gt 0) {
            Move-Item -LiteralPath $BackupRoot -Destination $BackupDestination -Force
        }
        $RollbackComplete = $true
        $oldBackups = @(Get-ChildItem -LiteralPath $RollbackRoot -Directory | Sort-Object LastWriteTimeUtc -Descending | Select-Object -Skip 2)
        foreach ($oldBackup in $oldBackups) { Remove-Item -LiteralPath $oldBackup.FullName -Recurse -Force }
        Write-UpdateStatus 'applied' $Version $Revision 'Update verified and applied; rollback snapshot retained.'
        Write-Host ("Applied {0} verified files for release {1} revision {2}." -f $Applied.Count, $Version, $Revision)
    }
    catch {
        Write-Warning 'Update application failed. Restoring changed files from the transaction backup.'
        $rollbackEntries = $Applied.ToArray()
        for ($index = $rollbackEntries.Length - 1; $index -ge 0; $index--) {
            $Relative = $rollbackEntries[$index]
            $Destination = Join-Path $InstallRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Backup = Join-Path $BackupRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            if (Test-Path -LiteralPath $Backup -PathType Leaf) {
                Copy-Item -LiteralPath $Backup -Destination $Destination -Force
            } else {
                Remove-Item -LiteralPath $Destination -Force -ErrorAction SilentlyContinue
            }
        }
        Write-UpdateStatus 'rolled_back' $Version $Revision 'Update failed; original files were restored.'
        throw
    }
}
catch {
    Write-Error ("DCP update failed: " + $_.Exception.Message)
    if (-not $RollbackComplete) {
        $App = Join-Path $InstallRoot 'DCP.exe'
        if (-not $NoRestart -and (Test-Path -LiteralPath $App -PathType Leaf)) {
            try { Start-Process -FilePath $App -WorkingDirectory $InstallRoot | Out-Null } catch { }
        }
    }
    throw
}
finally {
    if (Test-Path -LiteralPath $WorkRoot) { Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
    try { Stop-Transcript | Out-Null } catch { }
    if ($PSCommandPath -and $PSCommandPath.StartsWith((Join-Path ([IO.Path]::GetTempPath()) 'DCP-Update-'), [StringComparison]::OrdinalIgnoreCase)) {
        Remove-Item -LiteralPath $PSCommandPath -Force -ErrorAction SilentlyContinue
    }
}

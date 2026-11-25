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

function Move-UpdateFileAtomic([string]$Source, [string]$Destination) {
    if ([IO.File]::Exists($Destination)) {
        try {
            # .NET 6+ supports overwrite via MoveFileEx/rename on the same volume.
            [IO.File]::Move($Source, $Destination, $true)
        } catch [System.Management.Automation.MethodException] {
            # Windows PowerShell 5.1/.NET Framework fallback: File.Replace requires
            # a real backup path (null is not portable across runtimes).
            $replacementBackup = $Destination + '.dcp-replace-backup-' + [guid]::NewGuid().ToString('N')
            [IO.File]::Replace($Source, $Destination, $replacementBackup)
            Remove-Item -LiteralPath $replacementBackup -Force -ErrorAction SilentlyContinue
        }
    } else {
        [IO.File]::Move($Source, $Destination)
    }
}

function Test-SafeUpdatePath([string]$Relative, [string]$Root) {
    if ([string]::IsNullOrWhiteSpace($Relative) -or [IO.Path]::IsPathRooted($Relative) -or
        $Relative -match '(^|[\\/])\.\.([\\/]|$)' -or $Relative -match '^[A-Za-z]:' -or
        $Relative -match '^[\\/]' -or $Relative.Contains(':')) { return $false }
    if ($Relative -match '^(DCP-UserData|platform[\\/](data|storage))([\\/]|$)' -or $Relative -match '(^|[\\/])\.env(?:$|\.(?!example$))') { return $false }
    if ($Relative.IndexOfAny([char[]](@(0..31) + 127)) -ge 0) { return $false }
    foreach ($segment in ($Relative -split '/')) {
        if ($segment.EndsWith('.') -or $segment.EndsWith(' ') -or
            $segment.IndexOfAny([char[]]@('<', '>', '"', '|', '?', '*')) -ge 0 -or
            $segment.Split('.')[0] -match '^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9]|CONIN\$|CONOUT\$)$') { return $false }
    }
    if (Test-Path -LiteralPath $Root) { $rootItem = Get-Item -LiteralPath $Root -Force; if (($rootItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { return $false } }
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

function Test-SafeUserDataPath([string]$Relative, [string]$Root) {
    if (-not $Relative.StartsWith('DCP-UserData/Windows/', [StringComparison]::OrdinalIgnoreCase)) { return $false }
    if (Test-Path -LiteralPath $Root) { $rootItem = Get-Item -LiteralPath $Root -Force; if (($rootItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { return $false } }
    $rootFull = [IO.Path]::GetFullPath($Root).TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    $cursor = $rootFull.TrimEnd([IO.Path]::DirectorySeparatorChar)
    foreach ($part in ($Relative -split '/')) {
        $cursor = Join-Path $cursor $part
        if (Test-Path -LiteralPath $cursor) {
            $item = Get-Item -LiteralPath $cursor -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { return $false }
        }
    }
    $destination = [IO.Path]::GetFullPath((Join-Path $Root ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)))
    return $destination.StartsWith($rootFull, [StringComparison]::OrdinalIgnoreCase)
}

$Version = ''
$Revision = 0
$BackupRoot = Join-Path $WorkRoot 'previous-install'
$PayloadRoot = Join-Path $SourceRoot 'payload'
$Applied = New-Object System.Collections.Generic.List[object]
$CriticalDataSnapshot = @()
$StartedProcessId = $null
$RollbackRestored = $false
$MutationStarted = $false
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
    $Deleted = if ($null -ne $Manifest.deleted) { @($Manifest.deleted) } else { @() }
    if ($Deleted.Count -gt 5000) { throw 'Update manifest deletion count is invalid.' }
    $SeenPaths = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    foreach ($Entry in $Entries) {
        if (-not $SeenPaths.Add([string]$Entry.path)) { throw 'Update manifest contains duplicate or case-colliding paths.' }
        $Relative = [string]$Entry.path
        if (-not (Test-SafeUpdatePath $Relative $InstallRoot)) { throw "Unsafe update path: $Relative" }
        $Source = Join-Path $PayloadRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
        if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { throw "Update payload is missing: $Relative" }
        $ExpectedHash = ([string]$Entry.sha256).ToLowerInvariant()
        if ($ExpectedHash -notmatch '^[0-9a-f]{64}$') { throw "Invalid SHA-256 in manifest: $Relative" }
        $Info = Get-Item -LiteralPath $Source -Force
        if ([int64]$Entry.size -lt 0 -or $Info.Length -ne [int64]$Entry.size) { throw "File size mismatch: $Relative" }
        $Hash = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($Hash -ne $ExpectedHash) { throw "Checksum mismatch: $Relative" }
    }
    foreach ($Relative in $Deleted) {
        if (-not (Test-SafeUpdatePath ([string]$Relative) $InstallRoot)) { throw "Unsafe update deletion path: $Relative" }
        if (-not $SeenPaths.Add([string]$Relative)) { throw "Update manifest replaces and deletes the same path: $Relative" }
    }

    # Snapshot only the critical SQLite/key files so a failed first launch can restore them.
    $CriticalPaths = @(
        'DCP-UserData/Windows/data/dcp.sqlite',
        'DCP-UserData/Windows/data/dcp.sqlite-wal',
        'DCP-UserData/Windows/data/dcp.sqlite-shm',
        'DCP-UserData/Windows/data/dcp.sqlite-journal',
        'DCP-UserData/Windows/storage/keys/master.key'
    )
    $CriticalRoot = Join-Path $WorkRoot 'critical-data'
    foreach ($index in 0..($CriticalPaths.Count - 1)) {
        $relative = $CriticalPaths[$index]
        if (-not (Test-SafeUserDataPath $relative $InstallRoot)) { throw "Unsafe critical user-data path: $relative" }
        $source = Join-Path $InstallRoot ($relative -replace '/', [IO.Path]::DirectorySeparatorChar)
        $saved = Join-Path $CriticalRoot ($index.ToString() + '.bin')
        $exists = Test-Path -LiteralPath $source
        if ($exists) {
            $item = Get-Item -LiteralPath $source -Force
            if (-not $item.PSIsContainer -and (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -eq 0)) {
                New-Item -ItemType Directory -Force -Path $CriticalRoot | Out-Null
                Copy-Item -LiteralPath $source -Destination $saved -Force
                $CriticalDataSnapshot += [pscustomobject]@{ Path = $source; Saved = $saved; Exists = $true }
            } else { throw "Critical user-data path is not a regular file: $relative" }
        } else { $CriticalDataSnapshot += [pscustomobject]@{ Path = $source; Saved = $saved; Exists = $false } }
    }

    $MutationStarted = $true
    try {
        foreach ($Entry in $Entries) {
            $Relative = [string]$Entry.path
            $Destination = Join-Path $InstallRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Source = Join-Path $PayloadRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Parent = Split-Path -Parent $Destination
            New-Item -ItemType Directory -Force -Path $Parent | Out-Null
            $Existed = Test-Path -LiteralPath $Destination
            if ($Existed) {
                if (-not (Test-Path -LiteralPath $Destination -PathType Leaf)) { throw "Update target is not a regular file: $Relative" }
                $Backup = Join-Path $BackupRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
                New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Backup) | Out-Null
                Copy-Item -LiteralPath $Destination -Destination $Backup -Force
            }
            $Applied.Add([pscustomobject]@{ Path = $Relative; Existed = $Existed })
            $Temporary = $Destination + '.dcp-update-' + [guid]::NewGuid().ToString('N')
            Copy-Item -LiteralPath $Source -Destination $Temporary -Force
            if ($Existed) { [IO.File]::Move($Temporary, $Destination, $true) } else { [IO.File]::Move($Temporary, $Destination) }
        }

        foreach ($Relative in $Deleted) {
            $Destination = Join-Path $InstallRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            if (-not (Test-Path -LiteralPath $Destination)) { continue }
            if (-not (Test-Path -LiteralPath $Destination -PathType Leaf)) { throw "Update deletion target is not a regular file: $Relative" }
            $Backup = Join-Path $BackupRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Backup) | Out-Null
            Copy-Item -LiteralPath $Destination -Destination $Backup -Force
            $Applied.Add([pscustomobject]@{ Path = $Relative; Existed = $true })
            Remove-Item -LiteralPath $Destination -Force
        }

        $Php = Join-Path $InstallRoot 'platform\dcp-env\runtime\php\windows-x64\php.exe'
        $App = Join-Path $InstallRoot 'DCP.exe'
        if (-not (Test-Path -LiteralPath $Php -PathType Leaf) -or -not (Test-Path -LiteralPath $App -PathType Leaf)) {
            throw 'Essential DCP runtime files are missing after the delta update.'
        }

        if (-not $NoRestart) {
            Write-Host 'Launching the updated DCP application for a startup check...'
            $Started = Start-Process -FilePath $App -WorkingDirectory $InstallRoot -PassThru
            $StartedProcessId = $Started.Id
            $PortFile = Join-Path $env:LOCALAPPDATA 'DiGiPL\DCP\DCP-UserData\Windows\storage\runtime-port.txt'
            if (-not (Test-Path -LiteralPath $PortFile)) {
                $PortFile = Join-Path $InstallRoot 'DCP-UserData\Windows\storage\runtime-port.txt'
            }
            Remove-Item -LiteralPath $PortFile -Force -ErrorAction SilentlyContinue
            $Healthy = $false
            for ($attempt = 0; $attempt -lt 40; $attempt++) {
                if (-not (Get-Process -Id $Started.Id -ErrorAction SilentlyContinue)) { break }
                if (Test-Path -LiteralPath $PortFile -PathType Leaf) {
                    $PortText = (Get-Content -LiteralPath $PortFile -Raw).Trim()
                    $Port = 0
                    if ([int]::TryParse($PortText, [ref]$Port) -and $Port -ge 1 -and $Port -le 65535) {
                        try {
                            $Health = Invoke-RestMethod -Uri ("http://127.0.0.1:{0}/api/status" -f $Port) -TimeoutSec 2 -Method Get
                            if ($Health.ok -eq $true) { $Healthy = $true; break }
                        } catch { }
                    }
                }
                Start-Sleep -Milliseconds 500
            }
            if (-not $Healthy) { throw 'Updated DCP /api/status did not become healthy.' }
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
        if ($StartedProcessId -and (Get-Process -Id $StartedProcessId -ErrorAction SilentlyContinue)) {
            Stop-Process -Id $StartedProcessId -Force -ErrorAction SilentlyContinue
            Start-Sleep -Milliseconds 500
        }
        $rollbackEntries = $Applied.ToArray()
        for ($index = $rollbackEntries.Length - 1; $index -ge 0; $index--) {
            $Operation = $rollbackEntries[$index]
            $Relative = [string]$Operation.Path
            $Destination = Join-Path $InstallRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            $Backup = Join-Path $BackupRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
            if ($Operation.Existed) {
                if (-not (Test-Path -LiteralPath $Backup -PathType Leaf)) { throw "Rollback backup is missing for original file: $Relative" }
                $Temporary = $Destination + '.dcp-rollback-' + [guid]::NewGuid().ToString('N')
                try {
                    Copy-Item -LiteralPath $Backup -Destination $Temporary -Force
                    [IO.File]::Move($Temporary, $Destination, $true)
                } catch {
                    Remove-Item -LiteralPath $Temporary -Force -ErrorAction SilentlyContinue
                    throw
                }
            } else {
                Remove-Item -LiteralPath $Destination -Force -ErrorAction SilentlyContinue
            }
        }
        foreach ($Critical in $CriticalDataSnapshot) {
            $CriticalRelative = [string]$Critical.Path.Substring(([IO.Path]::GetFullPath($InstallRoot).TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar).Length) -replace '\\','/'
            if (-not (Test-SafeUserDataPath $CriticalRelative $InstallRoot)) { throw 'Critical user-data restore path is unsafe.' }
            if ($Critical.Exists) {
                $Parent = Split-Path -Parent $Critical.Path
                New-Item -ItemType Directory -Force -Path $Parent | Out-Null
                $Temporary = $Critical.Path + '.dcp-restore-' + [guid]::NewGuid().ToString('N')
                Copy-Item -LiteralPath $Critical.Saved -Destination $Temporary -Force
                if (Test-Path -LiteralPath $Critical.Path -PathType Leaf) { [IO.File]::Move($Temporary, $Critical.Path, $true) } else { [IO.File]::Move($Temporary, $Critical.Path) }
            } else { Remove-Item -LiteralPath $Critical.Path -Force -ErrorAction SilentlyContinue }
        }
        $RollbackRestored = $true
        Write-UpdateStatus 'rolled_back' $Version $Revision 'Update failed; original files and critical user data were restored.'
        throw
    }
}
catch {
    Write-Error ("DCP update failed: " + $_.Exception.Message)
    if (-not $RollbackComplete) {
        if ($MutationStarted -and -not $RollbackRestored) {
            try { Write-UpdateStatus 'rollback_failed' $Version $Revision 'Automatic rollback could not be confirmed; recovery files were retained.' } catch { }
        }
        $App = Join-Path $InstallRoot 'DCP.exe'
        if ((-not $MutationStarted -or $RollbackRestored) -and -not $NoRestart -and (Test-Path -LiteralPath $App -PathType Leaf)) {
            try { Start-Process -FilePath $App -WorkingDirectory $InstallRoot | Out-Null } catch { }
        }
    }
    throw
}
finally {
    if (Test-Path -LiteralPath $WorkRoot) {
        if ($MutationStarted -and -not $RollbackRestored -and -not $RollbackComplete) {
            Write-Warning "Rollback could not be confirmed. Recovery payload and backups were retained at $WorkRoot"
        } else { Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
    }
    try { Stop-Transcript | Out-Null } catch { }
    if ($PSCommandPath -and $PSCommandPath.StartsWith((Join-Path ([IO.Path]::GetTempPath()) 'DCP-Update-'), [StringComparison]::OrdinalIgnoreCase)) {
        Remove-Item -LiteralPath $PSCommandPath -Force -ErrorAction SilentlyContinue
    }
}

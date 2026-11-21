param(
    [Parameter(Mandatory = $true)][int]$ProcessId,
    [Parameter(Mandatory = $true)][string]$SourceRoot,
    [Parameter(Mandatory = $true)][string]$InstallRoot,
    [Parameter(Mandatory = $true)][string]$WorkRoot
)

$ErrorActionPreference = 'Stop'
$Log = Join-Path $env:LOCALAPPDATA 'DCP\update.log'
try {
    Start-Transcript -Path $Log -Append | Out-Null
    Write-Host "Waiting for DCP process $ProcessId to exit..."
    try { Wait-Process -Id $ProcessId -Timeout 120 -ErrorAction Stop } catch {
        if (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue) { throw 'DCP did not exit within 120 seconds.' }
    }

    $SourceApp = Join-Path $SourceRoot 'DCP.exe'
    if (-not (Test-Path $SourceApp)) { throw 'The staged release does not contain DCP.exe.' }

    $SourceData = Join-Path $SourceRoot 'platform\data'
    $SourceStorage = Join-Path $SourceRoot 'platform\storage'
    $TargetData = Join-Path $InstallRoot 'platform\data'
    $TargetStorage = Join-Path $InstallRoot 'platform\storage'
    $BackupRoot = Join-Path $WorkRoot 'previous-install'

    New-Item -ItemType Directory -Force -Path $TargetData, $TargetStorage | Out-Null

    # Keep a rollback copy of application files, but never copy or replace user data or keys.
    if (Test-Path $InstallRoot) {
        & robocopy $InstallRoot $BackupRoot /E /R:2 /W:1 /XD $TargetData $TargetStorage /NFL /NDL /NJH /NJS
        if ($LASTEXITCODE -ge 8) { throw "Could not create update rollback copy (robocopy $LASTEXITCODE)." }
    }

    try {
        # The release archive contains empty data/storage placeholders. Excluding them
        # preserves the user's SQLite database, encryption key, settings and logs.
        & robocopy $SourceRoot $InstallRoot /MIR /R:2 /W:1 /XD $SourceData $SourceStorage /NFL /NDL /NJH /NJS
        if ($LASTEXITCODE -ge 8) { throw "DCP file update failed (robocopy $LASTEXITCODE)." }
        New-Item -ItemType Directory -Force -Path $TargetData, $TargetStorage | Out-Null
        if (-not (Test-Path (Join-Path $InstallRoot 'runtime\php\php.exe'))) {
            throw 'Updated release is missing its private PHP runtime.'
        }
    }
    catch {
        Write-Warning 'Update failed. Restoring the previous application files.'
        if (Test-Path $BackupRoot) {
            & robocopy $BackupRoot $InstallRoot /MIR /R:2 /W:1 /XD (Join-Path $BackupRoot 'platform\data') (Join-Path $BackupRoot 'platform\storage') /NFL /NDL /NJH /NJS
        }
        throw
    }

    Write-Host 'DCP update applied. Launching the updated application.'
    Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue
    Start-Process -FilePath (Join-Path $InstallRoot 'DCP.exe') -WorkingDirectory $InstallRoot
}
catch {
    Write-Error "DCP update failed: $($_.Exception.Message)"
    if (Test-Path (Join-Path $InstallRoot 'DCP.exe')) {
        Start-Process -FilePath (Join-Path $InstallRoot 'DCP.exe') -WorkingDirectory $InstallRoot
    }
    if (Test-Path $WorkRoot) { Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
}
finally {
    try { Stop-Transcript | Out-Null } catch { }
    if (Test-Path $PSCommandPath) { Remove-Item -LiteralPath $PSCommandPath -Force -ErrorAction SilentlyContinue -ErrorAction SilentlyContinue }
}

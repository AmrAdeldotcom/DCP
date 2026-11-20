# DCP one-command Windows installer. No Git installation is required.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$InstallRoot = Join-Path $env:LOCALAPPDATA 'DCP'
$WorkRoot = Join-Path $env:TEMP ('DCP-Install-' + [guid]::NewGuid().ToString('N'))
$Archive = Join-Path $WorkRoot 'dcp-source.zip'
$ExtractRoot = Join-Path $WorkRoot 'source'
$ArchiveUrl = 'https://github.com/AmrAdeldotcom/DCP/archive/refs/heads/feature/dcp-cloud-signaling.zip'

try {
    Write-Host 'DCP setup: downloading the official Windows package...' -ForegroundColor Cyan
    New-Item -ItemType Directory -Force -Path $WorkRoot, $ExtractRoot | Out-Null
    Invoke-WebRequest -Uri $ArchiveUrl -OutFile $Archive
    Expand-Archive -LiteralPath $Archive -DestinationPath $ExtractRoot -Force

    $Package = Join-Path $ExtractRoot 'DCP-feature-dcp-cloud-signaling\platform\dist\windows'
    if (-not (Test-Path (Join-Path $Package 'DCP.exe'))) {
        throw 'The downloaded repository does not contain the ready-to-run Windows package (platform/dist/windows/DCP.exe).'
    }

    Write-Host "Installing DCP into $InstallRoot ..." -ForegroundColor Cyan
    $ExistingDatabase = Join-Path $InstallRoot 'platform\data\dcp.sqlite'
    $SavedDatabase = Join-Path $WorkRoot 'dcp-user-data.sqlite'
    if (Test-Path $ExistingDatabase) { Copy-Item -LiteralPath $ExistingDatabase -Destination $SavedDatabase -Force }

    if (Test-Path $InstallRoot) {
        $Backup = $InstallRoot + '.backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
        Move-Item -LiteralPath $InstallRoot -Destination $Backup
        Write-Host "Previous installation preserved at: $Backup" -ForegroundColor Yellow
    }
    New-Item -ItemType Directory -Force -Path $InstallRoot | Out-Null
    Copy-Item -Path (Join-Path $Package '*') -Destination $InstallRoot -Recurse -Force

    # Never ship the build machine's database or stale server PID to an end user.
    $FreshData = Join-Path $InstallRoot 'platform\data'
    New-Item -ItemType Directory -Force -Path $FreshData | Out-Null
    $BundledDatabase = Join-Path $FreshData 'dcp.sqlite'
    if (Test-Path $BundledDatabase) { Remove-Item -LiteralPath $BundledDatabase -Force }
    if (Test-Path $SavedDatabase) { Copy-Item -LiteralPath $SavedDatabase -Destination $BundledDatabase -Force }
    $StalePid = Join-Path $InstallRoot 'platform\storage\php-server.pid'
    if (Test-Path $StalePid) { Remove-Item -LiteralPath $StalePid -Force }

    $Installer = Join-Path $InstallRoot 'Install-DCP.ps1'
    if (Test-Path $Installer) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Installer
        if ($LASTEXITCODE -ne 0) { throw 'DCP prerequisite setup failed.' }
    }

    $App = Join-Path $InstallRoot 'DCP.exe'
    if (-not (Test-Path $App)) { throw 'DCP.exe was not found after installation.' }
    Write-Host 'DCP has been installed successfully. Launching...' -ForegroundColor Green
    $ShortcutPath = Join-Path ([Environment]::GetFolderPath('Desktop')) 'DCP.lnk'
    $Shell = New-Object -ComObject WScript.Shell
    $Shortcut = $Shell.CreateShortcut($ShortcutPath)
    $Shortcut.TargetPath = $App
    $Shortcut.WorkingDirectory = $InstallRoot
    $Shortcut.Description = 'DCP — Desktop Connector Platform'
    $Shortcut.Save()
    Start-Process -FilePath $App -WorkingDirectory $InstallRoot
}
catch {
    Write-Host "DCP installation failed: $($_.Exception.Message)" -ForegroundColor Red
    throw
}
finally {
    if (Test-Path $WorkRoot) { Remove-Item -LiteralPath $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
}

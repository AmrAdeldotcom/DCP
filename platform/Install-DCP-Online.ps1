# DCP one-command Windows installer. Downloads the latest official GitHub release.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$InstallRoot = Join-Path $env:LOCALAPPDATA 'DCP'
$WorkRoot = Join-Path $env:TEMP ('DCP-Install-' + [guid]::NewGuid().ToString('N'))
$Archive = Join-Path $WorkRoot 'DCP-Windows-x64.zip'
$ExtractRoot = Join-Path $WorkRoot 'extract'
$ReleaseUrl = 'https://github.com/AmrAdeldotcom/DCP/releases/latest/download/DCP-Windows-x64.zip'

try {
    New-Item -ItemType Directory -Force -Path $WorkRoot, $ExtractRoot | Out-Null
    Write-Host 'Downloading the latest official DCP for Windows...' -ForegroundColor Cyan
    Invoke-WebRequest -Uri $ReleaseUrl -OutFile $Archive
    Expand-Archive -LiteralPath $Archive -DestinationPath $ExtractRoot -Force

    $Package = Join-Path $ExtractRoot 'windows'
    $AppInPackage = Join-Path $Package 'DCP.exe'
    $RuntimeInstaller = Join-Path $Package 'platform\scripts\install-runtime-windows.ps1'
    $RuntimeVerifier = Join-Path $Package 'platform\scripts\verify-runtime.php'
    $PrerequisiteLauncher = Join-Path $Package 'Install-DCP.ps1'
    if (-not (Test-Path $AppInPackage)) { throw 'The downloaded release is invalid: windows/DCP.exe is missing.' }
    if (-not (Test-Path $RuntimeInstaller)) { throw 'The downloaded release is incomplete: Windows prerequisite installer is missing.' }
    if (-not (Test-Path $RuntimeVerifier)) { throw 'The downloaded release is incomplete: PHP runtime verifier is missing.' }
    if (-not (Test-Path $PrerequisiteLauncher)) { throw 'The downloaded release is incomplete: Install-DCP.ps1 is missing.' }

    Write-Host "Installing DCP into $InstallRoot ..." -ForegroundColor Cyan
    $SavedData = Join-Path $WorkRoot 'user-data'
    $SavedStorage = Join-Path $WorkRoot 'user-storage'
    $ExistingData = Join-Path $InstallRoot 'platform\data'
    $ExistingStorage = Join-Path $InstallRoot 'platform\storage'

    # Preserve all user state, especially the master encryption key paired with SQLite.
    if (Test-Path $ExistingData) { Copy-Item -LiteralPath $ExistingData -Destination $SavedData -Recurse -Force }
    if (Test-Path $ExistingStorage) { Copy-Item -LiteralPath $ExistingStorage -Destination $SavedStorage -Recurse -Force }

    if (Test-Path $InstallRoot) {
        $Backup = $InstallRoot + '.backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
        Move-Item -LiteralPath $InstallRoot -Destination $Backup
        Write-Host "Previous installation preserved at: $Backup" -ForegroundColor Yellow
    }

    New-Item -ItemType Directory -Force -Path $InstallRoot | Out-Null
    Copy-Item -Path (Join-Path $Package '*') -Destination $InstallRoot -Recurse -Force

    $DataDir = Join-Path $InstallRoot 'platform\data'
    $StorageDir = Join-Path $InstallRoot 'platform\storage'
    New-Item -ItemType Directory -Force -Path $DataDir, $StorageDir | Out-Null
    if (Test-Path $SavedData) { Get-ChildItem -LiteralPath $SavedData -Force | Copy-Item -Destination $DataDir -Recurse -Force }
    if (Test-Path $SavedStorage) { Get-ChildItem -LiteralPath $SavedStorage -Force | Copy-Item -Destination $StorageDir -Recurse -Force }

    $StalePid = Join-Path $InstallRoot 'platform\storage\php-server.pid'
    if (Test-Path $StalePid) { Remove-Item -LiteralPath $StalePid -Force }

    Write-Host 'Installing and verifying PHP, Visual C++ Runtime, and WebView2...' -ForegroundColor Cyan
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $InstallRoot 'Install-DCP.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'DCP prerequisite setup failed.' }

    $App = Join-Path $InstallRoot 'DCP.exe'
    $ShortcutPath = Join-Path ([Environment]::GetFolderPath('Desktop')) 'DCP.lnk'
    $Shell = New-Object -ComObject WScript.Shell
    $Shortcut = $Shell.CreateShortcut($ShortcutPath)
    $Shortcut.TargetPath = $App
    $Shortcut.WorkingDirectory = $InstallRoot
    $Shortcut.Description = 'DCP — Desktop Connector Platform'
    $Shortcut.IconLocation = (Join-Path $InstallRoot 'DCP.exe')
    $Shortcut.Save()

    Write-Host 'DCP installed. Launching the application...' -ForegroundColor Green
    Start-Process -FilePath $App -WorkingDirectory $InstallRoot
}
catch {
    Write-Host "DCP installation failed: $($_.Exception.Message)" -ForegroundColor Red
    if ($Backup -and (Test-Path $Backup)) {
        if (Test-Path $InstallRoot) { Remove-Item -LiteralPath $InstallRoot -Recurse -Force }
        Move-Item -LiteralPath $Backup -Destination $InstallRoot
        Write-Host 'Previous DCP installation restored.' -ForegroundColor Yellow
    }
    throw
}
finally {
    if (Test-Path $WorkRoot) { Remove-Item $WorkRoot -Recurse -Force -ErrorAction SilentlyContinue }
}

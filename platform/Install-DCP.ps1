$ErrorActionPreference = 'Stop'
$PlatformRoot = $PSScriptRoot
$env:DCP_ROOT = Split-Path -Parent $PlatformRoot
$RuntimeInstaller = Join-Path $PlatformRoot 'scripts\install-runtime-windows.ps1'
if (-not (Test-Path $RuntimeInstaller)) {
    throw "DCP runtime installer was not found: $RuntimeInstaller"
}
& $RuntimeInstaller
Write-Host 'DCP prerequisites are ready. Start DCP.exe from the package folder.' -ForegroundColor Green

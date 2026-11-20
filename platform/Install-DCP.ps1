$ErrorActionPreference = 'Stop'
$env:DCP_ROOT = $PSScriptRoot
& (Join-Path $PSScriptRoot 'platform\scripts\install-runtime-windows.ps1')
Write-Host 'DCP prerequisites are ready. Start DCP.exe.'

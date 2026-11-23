$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))
$env:DCP_ROOT = $Root
& (Join-Path $Root 'platform\scripts\install-runtime-windows.ps1')
if ($LASTEXITCODE -ne 0) { throw 'DCP runtime bootstrap failed.' }

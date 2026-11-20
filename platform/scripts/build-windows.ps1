$ErrorActionPreference = 'Stop'
$Platform = Split-Path -Parent $PSScriptRoot
$Project = Join-Path $Platform 'wrappers\windows\DCP.WebView.csproj'
$Out = Join-Path $Platform 'dist\windows'
dotnet publish $Project -c Release -r win-x64 --self-contained true -o $Out
if ($LASTEXITCODE -ne 0) { throw 'The DCP Windows application build failed.' }
$Payload = Join-Path $Out 'platform'
New-Item -ItemType Directory -Force -Path $Payload | Out-Null
$Exclude = @('dist','wrappers','tests','.git')
Get-ChildItem $Platform -Force | Where-Object { $_.Name -notin $Exclude } | ForEach-Object {
  Copy-Item $_.FullName $Payload -Recurse -Force
}
# Keep the prerequisite installer and its PHP verification script inside the shipped package.
$env:DCP_ROOT = $Out
& (Join-Path $PSScriptRoot 'install-runtime-windows.ps1')
Write-Host "Windows portable package ready: $Out"

@('$ErrorActionPreference = ''Stop''', '$env:DCP_ROOT = $PSScriptRoot', '$Installer = Join-Path $PSScriptRoot ''platform\scripts\install-runtime-windows.ps1''', 'if (-not (Test-Path $Installer)) { throw ''DCP prerequisite installer is missing from this package.'' }', '& $Installer') | Set-Content (Join-Path $Out 'Install-DCP.ps1')

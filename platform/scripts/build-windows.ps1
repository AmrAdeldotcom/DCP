$ErrorActionPreference = 'Stop'
$Platform = Split-Path -Parent $PSScriptRoot
$Project = Join-Path $Platform 'wrappers\windows\DCP.WebView.csproj'
$Out = Join-Path $Platform 'dist\windows'
dotnet publish $Project -c Release -r win-x64 --self-contained true -o $Out
$Payload = Join-Path $Out 'platform'
New-Item -ItemType Directory -Force -Path $Payload | Out-Null
$Exclude = @('dist','wrappers','scripts','tests','.git')
Get-ChildItem $Platform -Force | Where-Object { $_.Name -notin $Exclude } | ForEach-Object {
  Copy-Item $_.FullName $Payload -Recurse -Force
}
$env:DCP_ROOT = $Out; & (Join-Path $PSScriptRoot 'install-runtime-windows.ps1')
Write-Host "Windows portable package ready: $Out"

@('$env:DCP_ROOT = $PSScriptRoot','& (Join-Path $PSScriptRoot ''platform\scripts\install-runtime-windows.ps1'')') | Set-Content (Join-Path $Out 'Install-DCP.ps1')

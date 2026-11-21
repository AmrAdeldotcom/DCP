param([string]$DCPVersion = $env:GITHUB_REF_NAME)

$ErrorActionPreference = 'Stop'
$Platform = Split-Path -Parent $PSScriptRoot
$Project = Join-Path $Platform 'wrappers\windows\DCP.WebView.csproj'
$Out = Join-Path $Platform 'dist\windows'

if ([string]::IsNullOrWhiteSpace($DCPVersion) -or $DCPVersion -notmatch '^v?\d+\.\d+\.\d+') {
    $DCPVersion = (& git -C (Split-Path -Parent $Platform) describe --tags --abbrev=0 2>$null)
}
if ([string]::IsNullOrWhiteSpace($DCPVersion)) { $DCPVersion = 'v1.0.0-alpha.7' }

dotnet publish $Project -c Release -r win-x64 --self-contained true -p:EnableWindowsTargeting=true -p:DCPVersion="$DCPVersion" -o $Out
if ($LASTEXITCODE -ne 0) { throw 'The DCP Windows application build failed.' }

$Payload = Join-Path $Out 'platform'
New-Item -ItemType Directory -Force -Path $Payload | Out-Null
$Exclude = @('dist','wrappers','tests','.git','data','storage')
Get-ChildItem $Platform -Force | Where-Object { $_.Name -notin $Exclude } | ForEach-Object {
  Copy-Item $_.FullName $Payload -Recurse -Force
}

# Never ship the developer's SQLite database, encryption keys, logs or local settings.
New-Item -ItemType Directory -Force -Path (Join-Path $Payload 'data'), (Join-Path $Payload 'storage') | Out-Null
New-Item -ItemType File -Force -Path (Join-Path $Payload 'data\.gitkeep'), (Join-Path $Payload 'storage\.gitkeep') | Out-Null

# Bundle the private PHP runtime and verify it before creating the release archive.
$env:DCP_ROOT = $Out
& (Join-Path $PSScriptRoot 'install-runtime-windows.ps1')
if ($LASTEXITCODE -ne 0) { throw 'The private PHP runtime setup failed.' }

@(
  '$ErrorActionPreference = ''Stop''',
  '$env:DCP_ROOT = $PSScriptRoot',
  '$Installer = Join-Path $PSScriptRoot ''platform\scripts\install-runtime-windows.ps1''',
  'if (-not (Test-Path $Installer)) { throw ''DCP prerequisite installer is missing from this package.'' }',
  '& $Installer'
) | Set-Content (Join-Path $Out 'Install-DCP.ps1')

Write-Host "Windows portable package ready: $Out (version $DCPVersion)"

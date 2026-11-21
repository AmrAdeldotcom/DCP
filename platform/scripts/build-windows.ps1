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


# Generate a deterministic SHA-256 delta manifest against the previous published tag.
$RepoRoot = Split-Path -Parent $Platform
$Manifest = Join-Path $Out 'DCP-Windows-update.json'
$PreviousManifestUrl = $null
$PayloadRoot = Join-Path $Out 'payload'
New-Item -ItemType Directory -Force -Path $PayloadRoot | Out-Null
$PreviousTag = (& git -C $RepoRoot tag --sort=-version:refname | Where-Object { $_ -ne $DCPVersion } | Select-Object -First 1)
if (-not [string]::IsNullOrWhiteSpace($PreviousTag)) { $PreviousManifestUrl = "https://github.com/AmrAdeldotcom/DCP/releases/download/$PreviousTag/DCP-Windows-update.json" }
$Changed = @()
if (-not [string]::IsNullOrWhiteSpace($PreviousTag)) {
  $Changed = @(& git -C $RepoRoot diff --name-only --diff-filter=ACMRT "$PreviousTag" HEAD -- platform)
  if ($LASTEXITCODE -ne 0) { throw 'Could not calculate the delta against the previous release tag.' }
} else {
  $Changed = @(& git -C $RepoRoot ls-files platform)
}
$Files = @()
foreach ($Relative in $Changed) {
  $Relative = $Relative -replace '\\','/'
  if (-not $Relative.StartsWith('platform/')) { continue }
  if ($Relative -match '^platform/(data|storage)(/|$)' -or $Relative -match '^platform/(dist|wrappers|tests)(/|$)') { continue }
  if ($Relative -match '^platform/\.git') { continue }
  if ($Relative -match '\.(md|map)$') { continue }
  $Source = Join-Path $RepoRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
  if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { continue }
  $Target = Join-Path $PayloadRoot ($Relative -replace '/', [IO.Path]::DirectorySeparatorChar)
  New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Target) | Out-Null
  Copy-Item -LiteralPath $Source -Destination $Target -Force
  $Info = Get-Item -LiteralPath $Target
  $Hash = (Get-FileHash -LiteralPath $Target -Algorithm SHA256).Hash.ToLowerInvariant()
  $Files += [pscustomobject]@{ path = $Relative; size = [int64]$Info.Length; sha256 = $Hash }
}
if ($Files.Count -eq 0) {
  # A release may change metadata only; still update the version marker file.
  $FallbackSource = Join-Path $Platform 'config\app.php'
  $InfoPath = Join-Path $PayloadRoot 'platform\config\app.php'
  if (Test-Path -LiteralPath $FallbackSource) {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $InfoPath) | Out-Null
    Copy-Item -LiteralPath $FallbackSource -Destination $InfoPath -Force
    $Info = Get-Item -LiteralPath $InfoPath
    $Files += [pscustomobject]@{ path = 'platform/config/app.php'; size = [int64]$Info.Length; sha256 = (Get-FileHash -LiteralPath $InfoPath -Algorithm SHA256).Hash.ToLowerInvariant() }
  }
}
[pscustomobject]@{ schema = 1; version = $DCPVersion; previous_manifest_url = $PreviousManifestUrl; files = @($Files) } |
  ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $Manifest -Encoding utf8
Write-Host ("Generated delta manifest with {0} changed files." -f $Files.Count)

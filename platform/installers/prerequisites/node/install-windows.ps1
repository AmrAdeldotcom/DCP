$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Version = '24.22.3'
$Archive = "node-v$Version-win-x64.zip"
$Base = "https://nodejs.org/dist/v$Version"
$Dest = Join-Path $env:LOCALAPPDATA "Programs\nodejs-$Version"
$Node = Join-Path $Dest 'node.exe'
if ((Test-Path $Node) -and ((& $Node --version) -eq "v$Version")) { Write-Output "Node.js v$Version already verified at $Node"; exit 0 }
$Temp = Join-Path $env:TEMP ("DCP-Node-" + [guid]::NewGuid())
New-Item -ItemType Directory -Force -Path $Temp | Out-Null
try {
  Write-Output "[1/5] Downloading official Node.js $Version archive"
  $Zip = Join-Path $Temp $Archive
  Invoke-WebRequest -Uri "$Base/$Archive" -OutFile $Zip
  Write-Output "[2/5] Downloading official SHA-256 manifest"
  $Manifest = Join-Path $Temp 'SHASUMS256.txt'
  Invoke-WebRequest -Uri "$Base/SHASUMS256.txt" -OutFile $Manifest
  $Line = Get-Content $Manifest | Where-Object { $_ -match "\s+$([regex]::Escape($Archive))$" } | Select-Object -First 1
  if (-not $Line) { throw "Official SHA-256 entry missing for $Archive" }
  $Expected = ($Line -split '\s+')[0].ToLowerInvariant()
  $Actual = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
  if ($Expected -ne $Actual) { throw 'Node.js archive SHA-256 mismatch' }
  Write-Output "[3/5] SHA-256 verified; extracting user-local runtime"
  $Stage = Join-Path $Temp 'stage'
  Expand-Archive -LiteralPath $Zip -DestinationPath $Stage
  $Source = Join-Path $Stage "node-v$Version-win-x64"
  if (-not (Test-Path (Join-Path $Source 'node.exe'))) { throw 'node.exe missing after extraction' }
  if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }
  New-Item -ItemType Directory -Force -Path (Split-Path $Dest) | Out-Null
  Move-Item $Source $Dest
  $UserPath = [Environment]::GetEnvironmentVariable('Path','User')
  if (($UserPath -split ';') -notcontains $Dest) {
    [Environment]::SetEnvironmentVariable('Path', (($UserPath.TrimEnd(';') + ';' + $Dest).Trim(';')), 'User')
  }
  Write-Output "[4/5] Verifying Node and npm"
  & $Node --version
  & (Join-Path $Dest 'npm.cmd') --version
  Write-Output "[5/5] Node.js runtime installation verified: $Node"
} finally { Remove-Item $Temp -Recurse -Force -ErrorAction SilentlyContinue }

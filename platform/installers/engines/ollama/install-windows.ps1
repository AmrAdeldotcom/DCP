$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Dest = Join-Path $env:LOCALAPPDATA 'Programs\Ollama'
if (Test-Path (Join-Path $Dest 'ollama.exe')) { Write-Host "Ollama already installed at $Dest"; exit 0 }
$Release = Invoke-RestMethod -Uri 'https://api.github.com/repos/ollama/ollama/releases/latest' -Headers @{ 'User-Agent' = 'DCP-Runtime-Installer' }
$Asset = $Release.assets | Where-Object { $_.name -eq 'ollama-windows-amd64.zip' } | Select-Object -First 1
if (-not $Asset -or -not $Asset.digest.StartsWith('sha256:')) { throw 'Official Ollama Windows archive or SHA-256 digest is missing.' }
$Temp = Join-Path $env:TEMP ('DCP-Ollama-' + [guid]::NewGuid()); New-Item -ItemType Directory -Force -Path $Temp | Out-Null
try {
  Write-Output '[1/5] Downloading official Ollama Windows archive (large download)'
  $Zip = Join-Path $Temp 'ollama.zip'; Invoke-WebRequest -Uri $Asset.browser_download_url -OutFile $Zip
  Write-Output '[2/5] Verifying downloaded Ollama archive SHA-256'
  $Actual = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
  if ($Actual -ne $Asset.digest.Substring(7).ToLowerInvariant()) { throw 'Ollama SHA-256 verification failed.' }
  Write-Output '[3/5] Extracting Ollama runtime'
  $Stage = Join-Path $Temp 'unpacked'; Expand-Archive -LiteralPath $Zip -DestinationPath $Stage
  $Exe = Get-ChildItem -LiteralPath $Stage -Filter 'ollama.exe' -File -Recurse | Select-Object -First 1
  if (-not $Exe) { throw 'ollama.exe was not found in the official archive.' }
  Write-Output '[4/5] Installing Ollama into the standard user-local program directory'
  New-Item -ItemType Directory -Force -Path $Dest | Out-Null
  Copy-Item -Path (Join-Path $Exe.DirectoryName '*') -Destination $Dest -Recurse -Force
  $InstalledExe = Join-Path $Dest 'ollama.exe'
  if (-not (Test-Path $InstalledExe)) { throw 'ollama.exe missing after extraction/copy.' }
  $Version = & $InstalledExe --version 2>&1
  if ($LASTEXITCODE -ne 0) { throw 'Installed Ollama executable failed its version check.' }
  Write-Output '[5/5] Ollama binary verified at standard user-local path'
  Write-Output "SUCCESS: Ollama installed at $Dest; models use the standard user directory. Version: $Version"
} finally { Remove-Item $Temp -Recurse -Force -ErrorAction SilentlyContinue }

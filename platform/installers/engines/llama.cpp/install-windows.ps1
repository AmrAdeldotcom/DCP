$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Dest = Join-Path $env:LOCALAPPDATA 'Programs\llama.cpp'
$SystemServer = Get-Command 'llama-server.exe' -ErrorAction SilentlyContinue
if ($SystemServer) { Write-Host "System llama-server exists at $($SystemServer.Source); DCP will reuse it."; exit 0 }
if (Test-Path (Join-Path $Dest 'llama-server.exe')) { Write-Host "llama.cpp already installed at $Dest"; exit 0 }
$Releases = Invoke-RestMethod -Uri 'https://api.github.com/repos/ggml-org/llama.cpp/releases?per_page=1' -Headers @{ 'User-Agent' = 'DCP-Runtime-Installer' }
$Asset = $Releases[0].assets | Where-Object { $_.name -match '^llama-.*-bin-win-cpu-x64\.zip$' } | Select-Object -First 1
if (-not $Asset -or -not $Asset.digest.StartsWith('sha256:')) { throw 'Official llama.cpp Windows CPU x64 archive or SHA-256 digest is missing.' }
$Temp = Join-Path $env:TEMP ('DCP-llama-' + [guid]::NewGuid())
New-Item -ItemType Directory -Force -Path $Temp | Out-Null
try {
  $Zip = Join-Path $Temp 'llama.zip'; Invoke-WebRequest -Uri $Asset.browser_download_url -OutFile $Zip
  $Actual = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
  if ($Actual -ne $Asset.digest.Substring(7).ToLowerInvariant()) { throw 'llama.cpp SHA-256 verification failed.' }
  $Stage = Join-Path $Temp 'unpacked'; Expand-Archive -LiteralPath $Zip -DestinationPath $Stage
  $Server = Get-ChildItem -LiteralPath $Stage -Filter 'llama-server.exe' -File -Recurse | Select-Object -First 1
  if (-not $Server) { throw 'llama-server.exe was not found in the official archive.' }
  $New = $Dest + '.new'; if (Test-Path $New) { Remove-Item $New -Recurse -Force }
  New-Item -ItemType Directory -Force -Path $New | Out-Null
  Copy-Item -Path (Join-Path $Server.DirectoryName '*') -Destination $New -Recurse -Force
  $Previous = $Dest + '.previous'
  if (Test-Path $Previous) { Remove-Item $Previous -Recurse -Force }
  if (Test-Path $Dest) { Move-Item $Dest $Previous }
  try { Move-Item $New $Dest }
  catch {
    if (Test-Path $Previous) { Move-Item $Previous $Dest }
    throw
  }
  if (Test-Path $Previous) { Remove-Item $Previous -Recurse -Force }
  Write-Host "System llama.cpp installed to $Dest."
} finally { Remove-Item $Temp -Recurse -Force -ErrorAction SilentlyContinue }

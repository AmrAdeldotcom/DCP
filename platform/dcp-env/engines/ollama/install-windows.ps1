$ErrorActionPreference = 'Stop'
$PlatformRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))
$Dest = Join-Path $PlatformRoot 'dcp-env\engines\ollama\windows-x64'
try {
  $Health = Invoke-WebRequest -Uri 'http://127.0.0.1:11434/api/tags' -TimeoutSec 2
  if ($Health.StatusCode -eq 200) { Write-Host 'A healthy Ollama service is already running; DCP will reuse it.'; exit 0 }
} catch {}
$Release = Invoke-RestMethod -Uri 'https://api.github.com/repos/ollama/ollama/releases/latest' -Headers @{ 'User-Agent' = 'DCP-Runtime-Installer' }
$Asset = $Release.assets | Where-Object { $_.name -eq 'ollama-windows-amd64.zip' } | Select-Object -First 1
if (-not $Asset -or -not $Asset.digest.StartsWith('sha256:')) { throw 'Official Ollama Windows standalone ZIP or SHA-256 digest is missing.' }
$Temp = Join-Path $env:TEMP ('DCP-Ollama-' + [guid]::NewGuid())
New-Item -ItemType Directory -Force -Path $Temp | Out-Null
try {
  $Zip = Join-Path $Temp 'ollama.zip'
  Invoke-WebRequest -Uri $Asset.browser_download_url -OutFile $Zip
  $Actual = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
  if ($Actual -ne $Asset.digest.Substring(7).ToLowerInvariant()) { throw 'Ollama SHA-256 verification failed.' }
  $Stage = Join-Path $Temp 'unpacked'; Expand-Archive -LiteralPath $Zip -DestinationPath $Stage
  $Exe = Get-ChildItem -LiteralPath $Stage -Filter 'ollama.exe' -File -Recurse | Select-Object -First 1
  if (-not $Exe) { throw 'ollama.exe was not found in the official archive.' }
  $New = $Dest + '.new'; if (Test-Path $New) { Remove-Item $New -Recurse -Force }
  New-Item -ItemType Directory -Force -Path $New | Out-Null
  Copy-Item -Path (Join-Path $Exe.DirectoryName '*') -Destination $New -Recurse -Force
  if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }; Move-Item $New $Dest
  Write-Host "DCP-managed Ollama installed to $Dest; system Ollama was not changed."
} finally { Remove-Item $Temp -Recurse -Force -ErrorAction SilentlyContinue }

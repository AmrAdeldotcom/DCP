$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Version = '24.22.3'
$Archive = "node-v$Version-x64.msi"
$Base = "https://nodejs.org/dist/v$Version"
$Temp = Join-Path $env:TEMP ("DCP-Node-" + [guid]::NewGuid())
New-Item -ItemType Directory -Force -Path $Temp | Out-Null
function Download-WithProgress([string]$Uri,[string]$Destination) {
  try {
    $job=Start-BitsTransfer -Source $Uri -Destination $Destination -Asynchronous -ErrorAction Stop
    $last=-1
    while($job.JobState -in @('Connecting','Transferring','Queued','TransientError')) {
      $job=Get-BitsTransfer -JobId $job.JobId -ErrorAction SilentlyContinue
      if(-not $job){break}
      if($job.BytesTotal -gt 0){$percent=[math]::Min(100,[math]::Floor(100*$job.BytesTransferred/$job.BytesTotal));if($percent -ne $last){Write-Output "DOWNLOAD_PROGRESS: $percent%";$last=$percent}}
      Start-Sleep -Milliseconds 350
    }
    $job=Get-BitsTransfer -JobId $job.JobId -ErrorAction SilentlyContinue
    if($job -and $job.JobState -eq 'Transferred'){Complete-BitsTransfer -BitsJob $job;Write-Output 'DOWNLOAD_PROGRESS: 100%';return}
    if($job){Remove-BitsTransfer -BitsJob $job -Confirm:$false -ErrorAction SilentlyContinue}
    throw 'BITS transfer did not complete successfully.'
  } catch {Invoke-WebRequest -Uri $Uri -OutFile $Destination -ErrorAction Stop;Write-Output 'Download complete (fallback transfer; byte progress unavailable).'}
}
try {
  Write-Output "[1/5] Downloading official Node.js $Version MSI"
  $Msi = Join-Path $Temp $Archive
  Download-WithProgress "$Base/$Archive" $Msi
  Write-Output '[2/5] Downloading official SHA-256 manifest'
  $Manifest = Join-Path $Temp 'SHASUMS256.txt'
  Download-WithProgress "$Base/SHASUMS256.txt" $Manifest
  $Line = Get-Content $Manifest | Where-Object { $_ -match "\s+$([regex]::Escape($Archive))$" } | Select-Object -First 1
  if (-not $Line) { throw "Official SHA-256 entry missing for $Archive" }
  $Expected = ($Line -split '\s+')[0].ToLowerInvariant()
  $Actual = (Get-FileHash -LiteralPath $Msi -Algorithm SHA256).Hash.ToLowerInvariant()
  if ($Expected -ne $Actual) { throw 'Node.js MSI SHA-256 mismatch' }
  Write-Output '[3/5] SHA-256 verified; requesting Windows administrator approval'
  $proc = Start-Process -FilePath 'msiexec.exe' -ArgumentList @('/i',"`"$Msi`"",'/qn','/norestart') -Verb RunAs -Wait -PassThru
  if ($proc.ExitCode -notin @(0,3010)) { throw "Node.js MSI installer exited with code $($proc.ExitCode)" }
  Write-Output '[4/5] Verifying system Node.js and npm'
  $Node = Join-Path $env:ProgramFiles 'nodejs\node.exe'
  $Npm = Join-Path $env:ProgramFiles 'nodejs\npm.cmd'
  if (-not (Test-Path $Node)) { throw "System node.exe missing at $Node" }
  $actualVersion = (& $Node --version).Trim()
  if ($actualVersion -ne "v$Version") { throw "Unexpected Node.js version: $actualVersion" }
  & $Npm --version
  Write-Output "[5/5] Node.js system installation verified: $Node"
} finally { Remove-Item $Temp -Recurse -Force -ErrorAction SilentlyContinue }

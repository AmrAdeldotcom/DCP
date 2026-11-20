$ErrorActionPreference = 'Stop'
$Root = if ($env:DCP_ROOT) { $env:DCP_ROOT } else { Split-Path -Parent (Split-Path -Parent $PSScriptRoot) }
$PhpDir = Join-Path $Root 'runtime\php'
$PhpExe = Join-Path $PhpDir 'php.exe'
$PhpVersion = '8.5 latest'
if (-not (Test-Path $PhpExe)) {
    New-Item -ItemType Directory -Force -Path $PhpDir | Out-Null
    $Zip = Join-Path $env:TEMP "dcp-php-$PhpVersion.zip"
    $Url = 'https://windows.php.net/downloads/releases/latest/php-8.5-nts-Win32-vs17-x64-latest.zip'
    Write-Host "Downloading private PHP CLI runtime $PhpVersion..."
    Invoke-WebRequest -Uri $Url -OutFile $Zip
    Expand-Archive -Path $Zip -DestinationPath $PhpDir -Force
    Remove-Item $Zip -Force
}
$Ini = Join-Path $PhpDir 'php.ini'
if (-not (Test-Path $Ini)) {
    Copy-Item (Join-Path $PhpDir 'php.ini-production') $Ini
    @('extension_dir=ext','extension=curl','extension=fileinfo','extension=intl','extension=mbstring','extension=openssl','extension=pdo_sqlite','extension=sodium','extension=sqlite3') | Add-Content $Ini
}
$VcRuntime = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
if (-not $VcRuntime) {
    $Vc = Join-Path $env:TEMP 'DCP-vc_redist.x64.exe'
    Invoke-WebRequest 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $Vc
    Start-Process $Vc -ArgumentList '/install','/quiet','/norestart' -Wait
    Remove-Item $Vc -Force -ErrorAction SilentlyContinue
}

& $PhpExe (Join-Path $PSScriptRoot 'verify-runtime.php')
if ($LASTEXITCODE -ne 0) { throw 'PHP runtime verification failed.' }
Write-Host "DCP private PHP runtime is ready."
$WebViewFound = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\EdgeUpdate\Clients\*' -ErrorAction SilentlyContinue | Where-Object { $_.name -like '*WebView2*' }
if (-not $WebViewFound) {
    $Bootstrapper = Join-Path $env:TEMP 'MicrosoftEdgeWebView2Setup.exe'
    Invoke-WebRequest 'https://go.microsoft.com/fwlink/p/?LinkId=2124703' -OutFile $Bootstrapper
    Start-Process $Bootstrapper -ArgumentList '/silent','/install' -Wait
    Remove-Item $Bootstrapper -Force -ErrorAction SilentlyContinue
}

param(
    [switch]$ForceManaged,
    [switch]$InstallSystemPrerequisites
)
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Root = if ($env:DCP_ROOT) { $env:DCP_ROOT } else { Split-Path -Parent (Split-Path -Parent $PSScriptRoot) }
$Platform = Join-Path $Root 'platform'
$PhpDir = Join-Path $Platform 'dcp-env\runtime\php\windows-x64'
$PhpExe = Join-Path $PhpDir 'php.exe'
$Verifier = Join-Path $PSScriptRoot 'verify-runtime.php'
$PhpVersion = '8.5.13'
$PhpUrl = 'https://downloads.php.net/~windows/releases/php-8.5.13-nts-Win32-vs17-x64.zip'
$PhpSha256 = '4ae6c72bc0ca4f6b8f3e9b107874bae0135699f3aabe6c26a1f6103d040eea97'

function Test-DcpPhp([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { return $false }
    $Code = "echo (version_compare(PHP_VERSION,'8.2.0','>=') && extension_loaded('curl') && extension_loaded('intl') && extension_loaded('mbstring') && extension_loaded('openssl') && extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('fileinfo')) ? 'DCP_OK' : 'DCP_MISSING';"
    try {
        $Output = & $Path -r $Code 2>$null
        return ($LASTEXITCODE -eq 0 -and ($Output -join '').Trim() -eq 'DCP_OK')
    } catch { return $false }
}
function Test-VcRuntime {
    $Runtime = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
    return ($Runtime -and $Runtime.Installed -eq 1)
}
function Test-WebView2 {
    $ProgramFilesX86 = [Environment]::GetFolderPath('ProgramFilesX86')
    $Paths = @(
        (Join-Path $ProgramFilesX86 'Microsoft\EdgeWebView\Application'),
        (Join-Path $env:ProgramFiles 'Microsoft\EdgeWebView\Application')
    )
    foreach ($Path in $Paths) {
        if (Test-Path -LiteralPath $Path) {
            $Version = Get-ChildItem -LiteralPath $Path -Directory -ErrorAction SilentlyContinue |
                Where-Object { $_.Name -match '^\d+\.' } | Select-Object -First 1
            if ($Version) { return $true }
        }
    }
    return $false
}

if (-not $ForceManaged) {
    $SystemPhp = Get-Command 'php.exe' -ErrorAction SilentlyContinue
    if ($SystemPhp -and (Test-DcpPhp $SystemPhp.Source)) {
        $env:DCP_ROOT = $Root
        & $SystemPhp.Source $Verifier
        if ($LASTEXITCODE -ne 0) { throw 'The existing system PHP failed DCP runtime verification.' }
        Write-Host "Reusing compatible system PHP: $($SystemPhp.Source)"
        exit 0
    }
}
if (-not (Test-VcRuntime)) {
    if (-not $InstallSystemPrerequisites) {
        throw 'Microsoft Visual C++ 2015-2022 Redistributable x64 is required. Re-run with -InstallSystemPrerequisites to install it explicitly.'
    }
    $Vc = Join-Path $env:TEMP 'DCP-vc_redist.x64.exe'
    try {
        Invoke-WebRequest 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $Vc
        Start-Process $Vc -ArgumentList '/install','/quiet','/norestart' -Wait
    } finally { Remove-Item -LiteralPath $Vc -Force -ErrorAction SilentlyContinue }
    if (-not (Test-VcRuntime)) { throw 'Microsoft Visual C++ Redistributable installation could not be verified.' }
}

if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf) -or -not (Test-DcpPhp $PhpExe)) {
    $Parent = Split-Path -Parent $PhpDir
    New-Item -ItemType Directory -Force -Path $Parent | Out-Null
    $Stage = Join-Path $Parent ('windows-x64.stage-' + [guid]::NewGuid().ToString('N'))
    $Zip = Join-Path $env:TEMP ('dcp-php-' + [guid]::NewGuid().ToString('N') + '.zip')
    $Backup = $PhpDir + '.backup-' + [guid]::NewGuid().ToString('N')
    try {
        Write-Host "Downloading official PHP $PhpVersion NTS x64 runtime..."
        Invoke-WebRequest -Uri $PhpUrl -OutFile $Zip
        $Actual = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($Actual -ne $PhpSha256) { throw 'PHP archive SHA-256 verification failed.' }
        New-Item -ItemType Directory -Force -Path $Stage | Out-Null
        Expand-Archive -LiteralPath $Zip -DestinationPath $Stage -Force
        $Ini = Join-Path $Stage 'php.ini'
        Copy-Item (Join-Path $Stage 'php.ini-production') $Ini
        @(
            'extension_dir=ext',
            'extension=curl',
            'extension=fileinfo',
            'extension=intl',
            'extension=mbstring',
            'extension=openssl',
            'extension=pdo_sqlite',
            'extension=sodium',
            'extension=sqlite3'
        ) | Add-Content -LiteralPath $Ini
        $StagedPhp = Join-Path $Stage 'php.exe'
        if (-not (Test-DcpPhp $StagedPhp)) { throw 'The downloaded PHP runtime is missing a required extension or is incompatible.' }
        if (Test-Path -LiteralPath $PhpDir) { Move-Item -LiteralPath $PhpDir -Destination $Backup }
        try {
            Move-Item -LiteralPath $Stage -Destination $PhpDir
            if (-not (Test-DcpPhp $PhpExe)) { throw 'The installed PHP runtime failed its post-install verification.' }
        } catch {
            if (Test-Path -LiteralPath $PhpDir) { Remove-Item -LiteralPath $PhpDir -Recurse -Force }
            if (Test-Path -LiteralPath $Backup) { Move-Item -LiteralPath $Backup -Destination $PhpDir }
            throw
        }
        if (Test-Path -LiteralPath $Backup) { Remove-Item -LiteralPath $Backup -Recurse -Force }
    } finally {
        Remove-Item -LiteralPath $Zip -Force -ErrorAction SilentlyContinue
        if (Test-Path -LiteralPath $Stage) { Remove-Item -LiteralPath $Stage -Recurse -Force }
    }
}
$CaBundleSource = Join-Path $Platform 'resources\certs\cacert.pem'
if (-not (Test-Path -LiteralPath $CaBundleSource -PathType Leaf)) { throw 'The bundled Mozilla CA certificate store is missing.' }
$CaBundleDest = Join-Path $PhpDir 'cacert.pem'
Copy-Item -LiteralPath $CaBundleSource -Destination $CaBundleDest -Force
$Ini = Join-Path $PhpDir 'php.ini'
$CaPath = (Resolve-Path -LiteralPath $CaBundleDest).Path.Replace('\','/')
$IniText = [IO.File]::ReadAllText($Ini)
foreach ($Directive in @('curl.cainfo','openssl.cafile')) {
    $Line = $Directive + ' = "' + $CaPath + '"'
    $Pattern = '(?m)^\s*;?\s*' + [regex]::Escape($Directive) + '\s*=.*$'
    if ($IniText -match $Pattern) { $IniText = [regex]::Replace($IniText,$Pattern,[System.Text.RegularExpressions.MatchEvaluator]{param($m) $Line}) }
    else { $IniText += "`r`n$Line`r`n" }
}
[IO.File]::WriteAllText($Ini,$IniText,[Text.Encoding]::ASCII)
$env:DCP_ROOT = $Root
& $PhpExe $Verifier
if ($LASTEXITCODE -ne 0) { throw 'DCP-managed PHP runtime verification failed.' }
Write-Host "DCP-managed PHP $PhpVersion is ready: $PhpExe"

if (-not (Test-WebView2)) {
    if (-not $InstallSystemPrerequisites) {
        throw 'Microsoft Edge WebView2 Runtime is required. Re-run with -InstallSystemPrerequisites to install it explicitly.'
    }
    $Bootstrapper = Join-Path $env:TEMP 'MicrosoftEdgeWebView2Setup.exe'
    try {
        Invoke-WebRequest 'https://go.microsoft.com/fwlink/p/?LinkId=2124703' -OutFile $Bootstrapper
        Start-Process $Bootstrapper -ArgumentList '/silent','/install' -Wait
    } finally { Remove-Item -LiteralPath $Bootstrapper -Force -ErrorAction SilentlyContinue }
    if (-not (Test-WebView2)) { throw 'Microsoft Edge WebView2 Runtime installation could not be verified.' }
}

param()
$ErrorActionPreference = 'Stop'
$UpdaterScript = Join-Path (Split-Path -Parent $PSScriptRoot) 'scripts/update-windows.ps1'
if (-not (Test-Path -LiteralPath $UpdaterScript)) { throw 'Windows updater script is missing.' }

$OriginalLocalAppData = $env:LOCALAPPDATA
$TestRoot = Join-Path ([IO.Path]::GetTempPath()) ('dcp-update-tests-' + [guid]::NewGuid().ToString('N'))
$env:LOCALAPPDATA = Join-Path $TestRoot 'local-app-data'
New-Item -ItemType Directory -Force -Path $env:LOCALAPPDATA | Out-Null

function New-UpdateFixture([string]$Name) {
    $root = Join-Path $TestRoot $Name
    $install = Join-Path $root 'install'
    $work = Join-Path $root 'work'
    $payload = Join-Path $work 'payload'
    New-Item -ItemType Directory -Force -Path $install, $payload | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $install 'platform\dcp-env\runtime\php\windows-x64') | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $install 'DCP-UserData\Windows\data'), (Join-Path $install 'DCP-UserData\Windows\storage\keys') | Out-Null
    Set-Content -LiteralPath (Join-Path $install 'DCP.exe') -Value 'placeholder executable'
    Set-Content -LiteralPath (Join-Path $install 'platform\dcp-env\runtime\php\windows-x64\php.exe') -Value 'placeholder php'
    Set-Content -LiteralPath (Join-Path $install 'DCP-UserData\Windows\data\dcp.sqlite') -Value 'preserve database'
    Set-Content -LiteralPath (Join-Path $install 'DCP-UserData\Windows\storage\keys\master.key') -Value 'preserve key'
    return @{ Root = $root; Install = $install; Work = $work; Payload = $payload }
}

function New-Entry([string]$Relative, [string]$Source) {
    $item = Get-Item -LiteralPath $Source
    return [pscustomobject]@{
        path = $Relative
        size = [int64]$item.Length
        sha256 = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash.ToLowerInvariant()
    }
}

function Write-Manifest($Fixture, [object[]]$Entries) {
    [pscustomobject]@{
        schema = 2
        version = '1.0.0-alpha.10'
        package_revision = 1
        release_tag = 'v1.0.0-alpha.10'
        files = @($Entries)
    } | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $Fixture.Work 'update-manifest.json') -Encoding UTF8
}

try {
    # Success path: verifies schema 2, payload hashes, the current PHP runtime path,
    # retained rollback snapshot, and byte-preservation of the per-user DB/master key.
    $success = New-UpdateFixture 'success'
    $probe = Join-Path $success.Payload 'platform\app\updater-probe.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $probe) | Out-Null
    Set-Content -LiteralPath $probe -Value 'new release content'
    $oldProbe = Join-Path $success.Install 'platform\app\updater-probe.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $oldProbe) | Out-Null
    Set-Content -LiteralPath $oldProbe -Value 'old release content'
    $db = Join-Path $success.Install 'DCP-UserData\Windows\data\dcp.sqlite'
    $key = Join-Path $success.Install 'DCP-UserData\Windows\storage\keys\master.key'
    $dbBefore = (Get-FileHash -LiteralPath $db -Algorithm SHA256).Hash
    $keyBefore = (Get-FileHash -LiteralPath $key -Algorithm SHA256).Hash
    Write-Manifest $success @((New-Entry 'platform/app/updater-probe.txt' $probe))
    & $UpdaterScript -ProcessId 2147483647 -SourceRoot $success.Work -InstallRoot $success.Install -WorkRoot $success.Work -NoRestart
    if ((Get-Content -LiteralPath $oldProbe -Raw).Trim() -ne 'new release content') { throw 'Success case did not apply the new file.' }
    if ((Get-FileHash -LiteralPath $db -Algorithm SHA256).Hash -ne $dbBefore) { throw 'Success case changed SQLite data.' }
    if ((Get-FileHash -LiteralPath $key -Algorithm SHA256).Hash -ne $keyBefore) { throw 'Success case changed master.key.' }
    $rollback = Join-Path $env:LOCALAPPDATA 'DCP\rollback'
    $snapshot = Get-ChildItem -LiteralPath $rollback -Directory | Select-Object -First 1
    if (-not $snapshot) { throw 'Success case did not retain a rollback snapshot.' }
    $snapshotFile = Join-Path $snapshot.FullName 'platform\app\updater-probe.txt'
    if ((Get-Content -LiteralPath $snapshotFile -Raw).Trim() -ne 'old release content') { throw 'Rollback snapshot does not contain the original file.' }
    Write-Host 'PASS: schema 2 apply, PHP path, rollback snapshot, SQLite/key preservation'

    # Forced mid-apply failure: the first file is changed, then a file-vs-directory
    # collision stops the second write. The first file must be restored.
    $rollbackCase = New-UpdateFixture 'forced-rollback'
    $firstSource = Join-Path $rollbackCase.Payload 'platform\app\first.txt'
    $blockedSource = Join-Path $rollbackCase.Payload 'platform\app\blocked\child.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $firstSource), (Split-Path -Parent $blockedSource) | Out-Null
    Set-Content -LiteralPath $firstSource -Value 'new first'
    Set-Content -LiteralPath $blockedSource -Value 'new child'
    $firstDestination = Join-Path $rollbackCase.Install 'platform\app\first.txt'
    $blockedDestination = Join-Path $rollbackCase.Install 'platform\app\blocked'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $firstDestination) | Out-Null
    Set-Content -LiteralPath $firstDestination -Value 'old first'
    Set-Content -LiteralPath $blockedDestination -Value 'blocking file'
    $rollbackEntries = @(
        (New-Entry 'platform/app/first.txt' $firstSource),
        (New-Entry 'platform/app/blocked/child.txt' $blockedSource)
    )
    Write-Manifest $rollbackCase $rollbackEntries
    $failedAsExpected = $false
    try {
        & $UpdaterScript -ProcessId 2147483647 -SourceRoot $rollbackCase.Work -InstallRoot $rollbackCase.Install -WorkRoot $rollbackCase.Work -NoRestart
    } catch { $failedAsExpected = $true }
    if (-not $failedAsExpected) { throw 'Forced rollback case unexpectedly succeeded.' }
    if ((Get-Content -LiteralPath $firstDestination -Raw).Trim() -ne 'old first') { throw 'Forced rollback did not restore the first file.' }
    $status = Get-Content -LiteralPath (Join-Path $env:LOCALAPPDATA 'DCP\update-status.json') -Raw | ConvertFrom-Json
    if ($status.status -ne 'rolled_back') { throw 'Forced rollback status was not recorded.' }
    Write-Host 'PASS: forced mid-apply failure restores changed files and records rollback'

    # User-data paths must be rejected even when a matching payload file is supplied.
    $unsafe = New-UpdateFixture 'unsafe-user-data'
    $unsafeFile = Join-Path $unsafe.Payload 'DCP-UserData\Windows\data\dcp.sqlite'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $unsafeFile) | Out-Null
    Set-Content -LiteralPath $unsafeFile -Value 'attacker payload'
    Write-Manifest $unsafe @((New-Entry 'DCP-UserData/Windows/data/dcp.sqlite' $unsafeFile))
    $rejected = $false
    try {
        & $UpdaterScript -ProcessId 2147483647 -SourceRoot $unsafe.Work -InstallRoot $unsafe.Install -WorkRoot $unsafe.Work -NoRestart
    } catch { $rejected = $true }
    if (-not $rejected) { throw 'Updater accepted a user-data replacement path.' }
    if ((Get-Content -LiteralPath (Join-Path $unsafe.Install 'DCP-UserData\Windows\data\dcp.sqlite') -Raw).Trim() -ne 'preserve database') {
        throw 'Unsafe manifest changed the SQLite database.'
    }
    Write-Host 'PASS: user-data replacement paths are rejected'

    Write-Host 'ALL WINDOWS UPDATER TESTS PASSED'
}
finally {
    if (Test-Path -LiteralPath $TestRoot) { Remove-Item -LiteralPath $TestRoot -Recurse -Force -ErrorAction SilentlyContinue }
    if ($null -eq $OriginalLocalAppData) { Remove-Item Env:LOCALAPPDATA -ErrorAction SilentlyContinue }
    else { $env:LOCALAPPDATA = $OriginalLocalAppData }
}

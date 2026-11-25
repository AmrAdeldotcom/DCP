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
    New-Item -ItemType Directory -Force -Path (Join-Path $install 'platform/dcp-env/runtime/php/windows-x64') | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $install 'DCP-UserData/Windows/data'), (Join-Path $install 'DCP-UserData/Windows/storage/keys') | Out-Null
    Set-Content -LiteralPath (Join-Path $install 'DCP.exe') -Value 'placeholder executable'
    Set-Content -LiteralPath (Join-Path $install 'platform/dcp-env/runtime/php/windows-x64/php.exe') -Value 'placeholder php'
    Set-Content -LiteralPath (Join-Path $install 'DCP-UserData/Windows/data/dcp.sqlite') -Value 'preserve database'
    Set-Content -LiteralPath (Join-Path $install 'DCP-UserData/Windows/storage/keys/master.key') -Value 'preserve key'
    return @{ Root = $root; Install = $install; Work = $work; Payload = $payload }
}

function New-Entry([string]$Relative, [string]$Source) {
    $item = Get-Item -LiteralPath $Source -Force
    return [pscustomobject]@{
        path = $Relative
        size = [int64]$item.Length
        sha256 = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash.ToLowerInvariant()
    }
}

function Write-Manifest($Fixture, [object[]]$Entries, [string[]]$Deleted = @()) {
    [pscustomobject]@{
        schema = 2
        version = '1.0.0-alpha.10'
        package_revision = 1
        release_tag = 'v1.0.0-alpha.10'
        files = @($Entries)
        deleted = @($Deleted)
    } | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $Fixture.Work 'update-manifest.json') -Encoding UTF8
}

try {
    # Success path: verifies schema 2, payload hashes, the current PHP runtime path,
    # retained rollback snapshot, and byte-preservation of the per-user DB/master key.
    $success = New-UpdateFixture 'success'
    $probe = Join-Path $success.Payload 'platform/app/updater-probe.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $probe) | Out-Null
    Set-Content -LiteralPath $probe -Value 'new release content'
    $envExample = Join-Path $success.Payload 'platform/cloud/.env.example'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $envExample) | Out-Null
    New-Item -ItemType File -Force -Path $envExample | Out-Null
    Set-Content -LiteralPath $envExample -Value 'APP_KEY=replace-me'
    $oldProbe = Join-Path $success.Install 'platform/app/updater-probe.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $oldProbe) | Out-Null
    Set-Content -LiteralPath $oldProbe -Value 'old release content'
    $db = Join-Path $success.Install 'DCP-UserData/Windows/data/dcp.sqlite'
    $key = Join-Path $success.Install 'DCP-UserData/Windows/storage/keys/master.key'
    $dbBefore = (Get-FileHash -LiteralPath $db -Algorithm SHA256).Hash
    $keyBefore = (Get-FileHash -LiteralPath $key -Algorithm SHA256).Hash
    $obsolete = Join-Path $success.Install 'platform/app/obsolete.txt'
    Set-Content -LiteralPath $obsolete -Value 'obsolete package file'
    Write-Manifest $success @(
        (New-Entry 'platform/app/updater-probe.txt' $probe),
        (New-Entry 'platform/cloud/.env.example' $envExample)
    ) @('platform/app/obsolete.txt')
    & $UpdaterScript -ProcessId 2147483647 -SourceRoot $success.Work -InstallRoot $success.Install -WorkRoot $success.Work -NoRestart
    if ((Get-Content -LiteralPath $oldProbe -Raw).Trim() -ne 'new release content') { throw 'Success case did not apply the new file.' }
    if ((Get-Content -LiteralPath (Join-Path $success.Install 'platform/cloud/.env.example') -Raw).Trim() -ne 'APP_KEY=replace-me') { throw 'Success case did not install the safe environment example template.' }
    if (Test-Path -LiteralPath $obsolete) { throw 'Success case did not apply the signed deletion list.' }
    if ((Get-FileHash -LiteralPath $db -Algorithm SHA256).Hash -ne $dbBefore) { throw 'Success case changed SQLite data.' }
    if ((Get-FileHash -LiteralPath $key -Algorithm SHA256).Hash -ne $keyBefore) { throw 'Success case changed master.key.' }
    $rollback = Join-Path $env:LOCALAPPDATA 'DCP/rollback'
    $snapshot = Get-ChildItem -LiteralPath $rollback -Directory | Select-Object -First 1
    if (-not $snapshot) { throw 'Success case did not retain a rollback snapshot.' }
    $snapshotFile = Join-Path $snapshot.FullName 'platform/app/updater-probe.txt'
    if ((Get-Content -LiteralPath $snapshotFile -Raw).Trim() -ne 'old release content') { throw 'Rollback snapshot does not contain the original file.' }
    $snapshotDeleted = Join-Path $snapshot.FullName 'platform/app/obsolete.txt'
    if ((Get-Content -LiteralPath $snapshotDeleted -Raw).Trim() -ne 'obsolete package file') { throw 'Rollback snapshot did not preserve the deleted file.' }
    Write-Host 'PASS: schema 2 apply, signed deletions, rollback snapshot, SQLite/key preservation'

    # Full-package fallback: normalized full payload replaces the package-owned
    # files and removes only paths explicitly listed by the signed full manifest.
    $full = New-UpdateFixture 'full-fallback'
    $fullExe = Join-Path $full.Payload 'DCP.exe'
    $fullPhp = Join-Path $full.Payload 'platform/dcp-env/runtime/php/windows-x64/php.exe'
    $fullApp = Join-Path $full.Payload 'platform/app/index.php'
    $fullNew = Join-Path $full.Payload 'platform/app/new.php'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $fullPhp), (Split-Path -Parent $fullApp) | Out-Null
    Set-Content -LiteralPath $fullExe -Value 'new full executable'
    Set-Content -LiteralPath $fullPhp -Value 'new full managed PHP'
    Set-Content -LiteralPath $fullApp -Value 'new full application'
    Set-Content -LiteralPath $fullNew -Value 'new full file'
    $oldRemoved = Join-Path $full.Install 'platform/app/removed.php'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $oldRemoved) | Out-Null
    Set-Content -LiteralPath $oldRemoved -Value 'old package-owned file'
    $fullEntries = @(
        (New-Entry 'DCP.exe' $fullExe),
        (New-Entry 'platform/dcp-env/runtime/php/windows-x64/php.exe' $fullPhp),
        (New-Entry 'platform/app/index.php' $fullApp),
        (New-Entry 'platform/app/new.php' $fullNew)
    )
    Write-Manifest $full $fullEntries @('platform/app/removed.php')
    $fullDb = Join-Path $full.Install 'DCP-UserData/Windows/data/dcp.sqlite'
    $fullKey = Join-Path $full.Install 'DCP-UserData/Windows/storage/keys/master.key'
    $fullDbBefore = (Get-FileHash -LiteralPath $fullDb -Algorithm SHA256).Hash
    $fullKeyBefore = (Get-FileHash -LiteralPath $fullKey -Algorithm SHA256).Hash
    & $UpdaterScript -ProcessId 2147483647 -SourceRoot $full.Work -InstallRoot $full.Install -WorkRoot $full.Work -NoRestart
    if ((Get-Content -LiteralPath (Join-Path $full.Install 'DCP.exe') -Raw).Trim() -ne 'new full executable') { throw 'Full fallback did not replace DCP.exe.' }
    if ((Get-Content -LiteralPath (Join-Path $full.Install 'platform/dcp-env/runtime/php/windows-x64/php.exe') -Raw).Trim() -ne 'new full managed PHP') { throw 'Full fallback did not replace the managed PHP runtime.' }
    if (Test-Path -LiteralPath $oldRemoved) { throw 'Full fallback did not apply the signed deletion list.' }
    if ((Get-FileHash -LiteralPath $fullDb -Algorithm SHA256).Hash -ne $fullDbBefore -or (Get-FileHash -LiteralPath $fullKey -Algorithm SHA256).Hash -ne $fullKeyBefore) { throw 'Full fallback changed SQLite or master.key.' }
    Write-Host 'PASS: full-package fallback applies signed files/deletions and preserves SQLite/master.key'

    # Forced mid-apply failure: the first file is changed, then a file-vs-directory
    # collision stops the second write. The first file must be restored.
    $rollbackCase = New-UpdateFixture 'forced-rollback'
    $firstSource = Join-Path $rollbackCase.Payload 'platform/app/first.txt'
    $blockedSource = Join-Path $rollbackCase.Payload 'platform/app/blocked/child.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $firstSource), (Split-Path -Parent $blockedSource) | Out-Null
    Set-Content -LiteralPath $firstSource -Value 'new first'
    Set-Content -LiteralPath $blockedSource -Value 'new child'
    $firstDestination = Join-Path $rollbackCase.Install 'platform/app/first.txt'
    $blockedDestination = Join-Path $rollbackCase.Install 'platform/app/blocked'
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
    $status = Get-Content -LiteralPath (Join-Path $env:LOCALAPPDATA 'DCP/update-status.json') -Raw | ConvertFrom-Json
    if ($status.status -ne 'rolled_back') { throw 'Forced rollback status was not recorded.' }
    Write-Host 'PASS: forced mid-apply failure restores changed files and records rollback'

    # Deletion rollback: a signed deletion of the essential executable forces the
    # post-apply runtime gate to fail; the original executable must be restored.
    $deleteRollback = New-UpdateFixture 'delete-rollback'
    $newSource = Join-Path $deleteRollback.Payload 'platform/app/new.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $newSource) | Out-Null
    Set-Content -LiteralPath $newSource -Value 'new package file'
    Write-Manifest $deleteRollback @((New-Entry 'platform/app/new.txt' $newSource)) @('DCP.exe')
    $deleteFailed = $false
    try { & $UpdaterScript -ProcessId 2147483647 -SourceRoot $deleteRollback.Work -InstallRoot $deleteRollback.Install -WorkRoot $deleteRollback.Work -NoRestart } catch { $deleteFailed = $true }
    if (-not $deleteFailed) { throw 'Delete rollback case unexpectedly succeeded.' }
    if (-not (Test-Path -LiteralPath (Join-Path $deleteRollback.Install 'DCP.exe') -PathType Leaf)) { throw 'Deletion rollback did not restore the original DCP.exe.' }
    if (Test-Path -LiteralPath (Join-Path $deleteRollback.Install 'platform/app/new.txt')) { throw 'Deletion rollback did not remove the newly added file.' }
    Write-Host 'PASS: deleted package files are restored when the post-update gate fails'

    # User-data paths must be rejected even when a matching payload file is supplied.
    $unsafe = New-UpdateFixture 'unsafe-user-data'
    $unsafeFile = Join-Path $unsafe.Payload 'DCP-UserData/Windows/data/dcp.sqlite'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $unsafeFile) | Out-Null
    Set-Content -LiteralPath $unsafeFile -Value 'attacker payload'
    Write-Manifest $unsafe @((New-Entry 'platform/app/safe.txt' $unsafeFile)) @('DCP-UserData/Windows/data/dcp.sqlite')
    $rejected = $false
    try {
        & $UpdaterScript -ProcessId 2147483647 -SourceRoot $unsafe.Work -InstallRoot $unsafe.Install -WorkRoot $unsafe.Work -NoRestart
    } catch { $rejected = $true }
    if (-not $rejected) { throw 'Updater accepted a user-data replacement path.' }
    if ((Get-Content -LiteralPath (Join-Path $unsafe.Install 'DCP-UserData/Windows/data/dcp.sqlite') -Raw).Trim() -ne 'preserve database') {
        throw 'Unsafe manifest changed the SQLite database.'
    }
    Write-Host 'PASS: user-data replacement paths are rejected'

    $envSecret = New-UpdateFixture 'env-secret'
    $envSecretSource = Join-Path $envSecret.Payload 'platform/cloud/.env.local'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $envSecretSource) | Out-Null
    Set-Content -LiteralPath $envSecretSource -Value 'APP_KEY=real-secret'
    Write-Manifest $envSecret @((New-Entry 'platform/cloud/.env.local' $envSecretSource))
    $envSecretRejected = $false
    try { & $UpdaterScript -ProcessId 2147483647 -SourceRoot $envSecret.Work -InstallRoot $envSecret.Install -WorkRoot $envSecret.Work -NoRestart } catch { $envSecretRejected = $true }
    if (-not $envSecretRejected) { throw 'Updater accepted a real .env.local secret file.' }
    Write-Host 'PASS: .env.example is updateable while .env.local remains protected'

    $reserved = New-UpdateFixture 'reserved-paths'
    $reservedFile = Join-Path $reserved.Payload 'platform/app/safe.txt'
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $reservedFile) | Out-Null
    Set-Content -LiteralPath $reservedFile -Value 'safe payload'
    Write-Manifest $reserved @((New-Entry 'platform/app/safe.txt' $reservedFile)) @('platform/app/CON.txt', 'platform/app/trailing.')
    $reservedRejected = $false
    try { & $UpdaterScript -ProcessId 2147483647 -SourceRoot $reserved.Work -InstallRoot $reserved.Install -WorkRoot $reserved.Work -NoRestart } catch { $reservedRejected = $true }
    if (-not $reservedRejected) { throw 'Windows reserved device names or trailing-dot aliases were not rejected.' }
    Write-Host 'PASS: Windows reserved names and trailing-dot aliases are rejected'

    Write-Host 'ALL WINDOWS UPDATER TESTS PASSED'
}
finally {
    if (Test-Path -LiteralPath $TestRoot) { Remove-Item -LiteralPath $TestRoot -Recurse -Force -ErrorAction SilentlyContinue }
    if ($null -eq $OriginalLocalAppData) { Remove-Item Env:LOCALAPPDATA -ErrorAction SilentlyContinue }
    else { $env:LOCALAPPDATA = $OriginalLocalAppData }
}

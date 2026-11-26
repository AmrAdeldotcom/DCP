param([ValidateSet('desktop-commander','deepseek-harness')][string]$Agent,[string]$Log,[string]$State,[string]$PlatformRoot)
$ErrorActionPreference='Stop'; $ProgressPreference='SilentlyContinue'
New-Item -ItemType Directory -Force -Path (Split-Path $Log),(Split-Path $State) | Out-Null
$identity=[Security.Principal.WindowsIdentity]::GetCurrent();$principal=[Security.Principal.WindowsPrincipal]::new($identity)
if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){$argsList=@('-NoLogo','-NoProfile','-ExecutionPolicy','Bypass','-File',"`"$PSCommandPath`"",'-Agent',$Agent,'-Log',"`"$Log`"",'-State',"`"$State`"",'-PlatformRoot',"`"$PlatformRoot`"");$elevated=Start-Process -FilePath 'powershell.exe' -ArgumentList $argsList -Verb RunAs -Wait -PassThru;exit $elevated.ExitCode}
function Set-State($Status,$Stage){@{agent=$Agent;status=$Status;stage=$Stage;updated_at=(Get-Date).ToUniversalTime().ToString('o')}|ConvertTo-Json -Compress|Set-Content -LiteralPath $State -Encoding UTF8;Write-Output $Stage}
try {
 Start-Transcript -Path $Log -Append | Out-Null
 if($Agent -eq 'desktop-commander'){$Package='@wonderwhy-er/desktop-commander@latest';$Expected='@wonderwhy-er\desktop-commander'}else{$Package='@deepseek-ai/dsh@latest';$Expected='@deepseek-ai\dsh'}
 Set-State running 'Checking Node.js prerequisite'
 $NodeHome=Join-Path $env:ProgramFiles 'nodejs';$Node=Join-Path $NodeHome 'node.exe';$Version=''
 if(Test-Path $Node){$Version=(& $Node --version).Trim()}
 if($Version -ne 'v24.22.3'){Set-State running 'Installing verified Node.js 24.22.3';& (Join-Path $PlatformRoot 'installers\prerequisites\node\install-windows.ps1');$Node=Join-Path $env:ProgramFiles 'nodejs\node.exe'}
 if(-not(Test-Path $Node)){throw 'Node.js installation did not produce node.exe.'}
 $NodeHome=Split-Path $Node;$env:Path="$NodeHome;$env:Path";$Npm=Join-Path $NodeHome 'npm.cmd'
 if(-not(Test-Path $Npm)){throw 'npm.cmd is missing beside the verified Node.js runtime.'}
 Set-State running "Installing $Package into the system npm package directory";& $Npm install --global --prefix (Join-Path $env:ProgramFiles 'nodejs') --no-audit --no-fund $Package
 if($LASTEXITCODE -ne 0){throw "npm install exited with code $LASTEXITCODE"}
 $GlobalRoot=Join-Path (Join-Path $env:ProgramFiles 'nodejs') 'node_modules';$PackagePath=Join-Path $GlobalRoot $Expected
 if(-not(Test-Path $PackagePath)){throw "npm completed but package directory is missing: $PackagePath"}
 Set-State complete "Installed and verified $Package";Write-Output "SUCCESS: $Agent installed at $PackagePath"
} catch {Set-State failed $_.Exception.Message;Write-Error $_;exit 1} finally {try{Stop-Transcript|Out-Null}catch{}}

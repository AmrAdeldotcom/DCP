<?php
declare(strict_types=1);
return new class implements AgentAdapter {
    public function status(): array {
        if (PHP_OS_FAMILY === 'Windows') {
            $probe='Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "desktop-commander.*remote" } | Select-Object -First 1 -ExpandProperty ProcessId';
            $out=[]; exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($probe).' 2>NUL',$out);
        } else {
            $out=[]; exec('/usr/bin/pgrep -fl '.escapeshellarg('desktop-commander remote').' 2>/dev/null',$out);
        }
        $found=false;
        foreach($out as $line){if(str_contains($line,'desktop-commander')&&str_contains($line,'remote')&&!str_contains($line,'pgrep')){$found=true;break;}}
        return ['id'=>'desktop-commander','name'=>'Desktop Commander Remote','installed'=>true,'running'=>$found,'connected'=>false,'detail'=>$found?'Remote process detected; authenticated DCP task session is not configured.':'Remote process not detected.','capabilities'=>['remote-files','remote-process'],'integration'=>'process-detection-only'];
    }
};

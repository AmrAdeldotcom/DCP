<?php
declare(strict_types=1);
return new class implements AgentAdapter {
    public function status(): array {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return ['id'=>'deepseek-harness','name'=>'DeepSeek Harness','installed'=>false,'running'=>false,'connected'=>false,'detail'=>'Available only on macOS.','capabilities'=>['agent-tasks'],'integration'=>'detection-only'];
        }
        $installed = is_dir('/Applications/DeepSeek Harness.app');
        if (!$installed) {
            return ['id'=>'deepseek-harness','name'=>'DeepSeek Harness','installed'=>false,'running'=>false,'connected'=>false,'detail'=>'Not installed.','capabilities'=>['agent-tasks'],'integration'=>'detection-only'];
        }
        $out=[]; exec('/usr/bin/pgrep -fl '.escapeshellarg('dsh-runtime').' 2>/dev/null',$out);
        $running=count(array_filter($out,fn($line)=>str_contains($line,'dsh-runtime')&&!str_contains($line,'pgrep')))>0;
        return ['id'=>'deepseek-harness','name'=>'DeepSeek Harness','installed'=>true,'running'=>$running,'connected'=>false,'detail'=>$running?'Application process detected; task automation adapter is not yet verified.':'Installed, but not running.','capabilities'=>['agent-tasks'],'integration'=>'detection-only'];
    }
};

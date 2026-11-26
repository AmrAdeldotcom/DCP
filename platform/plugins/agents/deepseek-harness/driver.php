<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/app/Services/SystemAgentRuntime.php';
return new class implements AgentAdapter {
 public function status():array{return SystemAgentRuntime::status('deepseek-harness');}
 public function install():array{return SystemAgentRuntime::install('deepseek-harness');}
 public function start():array{return SystemAgentRuntime::start('deepseek-harness');}
 public function stop():array{return SystemAgentRuntime::stop('deepseek-harness');}
};

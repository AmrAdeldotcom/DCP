<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/app/Services/SystemAgentRuntime.php';
return new class implements AgentAdapter {
 public function status():array{return SystemAgentRuntime::status('desktop-commander');}
 public function install():array{return SystemAgentRuntime::install('desktop-commander');}
 public function start():array{return SystemAgentRuntime::start('desktop-commander');}
 public function stop():array{return SystemAgentRuntime::stop('desktop-commander');}
};

<?php
declare(strict_types=1);
return new class implements ToolAdapter {public function build(array $config):TerminalService{return new TerminalService($config);}};

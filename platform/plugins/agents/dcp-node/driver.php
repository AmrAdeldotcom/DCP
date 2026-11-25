<?php
declare(strict_types=1);
return new class implements AgentAdapter {public function status():array{return ['id'=>'dcp-node','name'=>'DCP Local Node','installed'=>true,'running'=>true,'connected'=>true,'detail'=>'PHP platform and SQLite database are active.','capabilities'=>['tasks','terminal.execute','devices']];}};

<?php
declare(strict_types=1);
final class DcpMemoryService {
 private MarkdownJsonMemory $memory;
 public function __construct(array $config){$this->memory=new MarkdownJsonMemory((string)$config['user_data']);}
 public function bootstrap(string $project,string $agent='any'):array{return $this->memory->bootstrap($project,$agent);}
 public function recall(string $query,string $project,string $agent='any',int $limit=5):array{return $this->memory->recall($query,$project,$agent,$limit);}
 public function remember(array $input):array{return $this->memory->remember($input);}
 public function read(string $id):array{return $this->memory->read($id);}
 public function retire(string $id,string $reason):array{return $this->memory->retire($id,$reason);}
 public function export():array{return $this->memory->export();}
}

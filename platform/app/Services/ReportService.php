<?php
declare(strict_types=1);
final class ReportService {
 public function __construct(private PDO $db,private string $root){}
 public function create(string $title,string $body):array{$id='bug_'.bin2hex(random_bytes(8));$now=date(DATE_ATOM);$q=$this->db->prepare('INSERT INTO reports(id,title,body,created_at) VALUES(?,?,?,?)');$q->execute([$id,$title,$body,$now]);$dir=$this->root.'/reports';if(!is_dir($dir))mkdir($dir,0700,true);$file=$dir.'/'.$id.'.json';file_put_contents($file,json_encode(['id'=>$id,'title'=>$title,'body'=>$body,'created_at'=>$now,'php'=>PHP_VERSION,'platform'=>PHP_OS_FAMILY],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));return ['id'=>$id,'file'=>$file,'created_at'=>$now];}
}

<?php
declare(strict_types=1);

final class TaskInboxService {
 public function __construct(private PDO $db) {}

 public function enqueue(string $message, ?string $sessionId=null, string $targetAgent='dp-core'):array {
  $message=trim($message);
  if($message==='')throw new InvalidArgumentException('A queued message cannot be empty.');
  if(!in_array($targetAgent,['dp-core','dc','dsh'],true))throw new InvalidArgumentException('Unsupported task target.');
  if(mb_strlen($message)>20000)throw new InvalidArgumentException('A queued message cannot exceed 20,000 characters.');
  if($sessionId!==null&&$sessionId!==''){
   $q=$this->db->prepare('SELECT id FROM chat_sessions WHERE id=?');$q->execute([$sessionId]);
   if(!$q->fetchColumn())throw new InvalidArgumentException('The selected chat session does not exist.');
  }else{$sessionId=null;}
  $id='inbox_'.bin2hex(random_bytes(12));$now=date(DATE_ATOM);
  $q=$this->db->prepare("INSERT INTO task_inbox(id,session_id,message,target_agent,status,priority,created_at,updated_at) VALUES(?,?,?,?,'queued',100,?,?)");
  $q->execute([$id,$sessionId,$message,$targetAgent,$now,$now]);
  return $this->get($id);
 }

 public function list(?string $sessionId=null,int $limit=50):array {
  $limit=max(1,min(100,$limit));
  if($sessionId!==null&&$sessionId!==''){
   $q=$this->db->prepare("SELECT id,session_id,message,target_agent,status,priority,created_at,updated_at FROM task_inbox WHERE status='queued' AND (session_id IS NULL OR session_id=?) ORDER BY priority,created_at,id LIMIT ?");
   $q->bindValue(1,$sessionId);$q->bindValue(2,$limit,PDO::PARAM_INT);$q->execute();
  }else{
   $q=$this->db->prepare("SELECT id,session_id,message,target_agent,status,priority,created_at,updated_at FROM task_inbox WHERE status='queued' ORDER BY priority,created_at,id LIMIT ?");
   $q->bindValue(1,$limit,PDO::PARAM_INT);$q->execute();
  }
  return $q->fetchAll();
 }

 public function reprioritize(string $id,int $priority):array {
  if($priority<0||$priority>9999)throw new InvalidArgumentException('Priority must be between 0 and 9999.');
  $q=$this->db->prepare("UPDATE task_inbox SET priority=?,updated_at=? WHERE id=? AND status='queued'");
  $q->execute([$priority,date(DATE_ATOM),$id]);
  if(!$q->rowCount())throw new RuntimeException('Queued message not found or no longer editable.');
  return $this->get($id);
 }

 public function markSent(string $id):array {
  $q=$this->db->prepare("UPDATE task_inbox SET status='sent',updated_at=? WHERE id=? AND status='queued'");
  $q->execute([date(DATE_ATOM),$id]);
  if(!$q->rowCount())throw new RuntimeException('Queued message not found or already processed.');
  return $this->get($id);
 }

 public function cancel(string $id):array {
  $q=$this->db->prepare("UPDATE task_inbox SET status='cancelled',updated_at=? WHERE id=? AND status='queued'");
  $q->execute([date(DATE_ATOM),$id]);
  if(!$q->rowCount())throw new RuntimeException('Queued message not found or already processed.');
  return $this->get($id);
 }

 private function get(string $id):array {
  $q=$this->db->prepare('SELECT id,session_id,message,target_agent,status,priority,created_at,updated_at FROM task_inbox WHERE id=?');$q->execute([$id]);
  $row=$q->fetch();if(!$row)throw new RuntimeException('Queued message was not found.');return $row;
 }
}

<?php
declare(strict_types=1);
final class DcpCapabilityGrantService {
 public function __construct(private PDO $db){}
 public function assertGranted(string $jobId,string $stepKey,int $planVersion,string $toolName,string $capability):void{
  $q=$this->db->prepare("SELECT g.id FROM chat_job_capability_grants g JOIN chat_job_execution_steps s ON s.id=g.step_id AND s.job_id=g.job_id AND s.plan_version=g.plan_version JOIN chat_job_execution_plans p ON p.job_id=g.job_id AND p.plan_version=g.plan_version JOIN chat_job_approvals a ON a.job_id=g.job_id AND a.step_id=g.step_id AND a.plan_version=g.plan_version WHERE g.job_id=? AND g.plan_version=? AND s.step_key=? AND g.tool_name=? AND g.capability=? AND g.status='granted' AND a.status='approved' LIMIT 1");$q->execute([$jobId,$planVersion,$stepKey,$toolName,$capability]);if(!$q->fetchColumn())throw new RuntimeException('The exact tool capability has not been granted for this approved plan step.');
 }
 public function revoke(string $jobId,string $stepKey,int $planVersion):void{$q=$this->db->prepare("UPDATE chat_job_capability_grants SET status='revoked',revoked_at=? WHERE job_id=? AND plan_version=? AND step_id=(SELECT id FROM chat_job_execution_steps WHERE job_id=? AND plan_version=? AND step_key=?) AND status='granted'");$q->execute([date(DATE_ATOM),$jobId,$planVersion,$jobId,$planVersion,$stepKey]);}
}

<?php
declare(strict_types=1);

/**
 * Converts provider-proposed function calls into a bounded, versioned DCP plan.
 * This phase deliberately does not execute tools. Execution is a separate,
 * approval-gated boundary and is not enabled by merely receiving model output.
 */
final class ChatExecutionCoordinator {
 public const MAX_TOOL_CALLS=16;
 public const MAX_TOTAL_ARGUMENT_BYTES=16384;
 public const MAX_PLAN_METADATA_BYTES=32768;
 public function __construct(private ChatJobService $jobs,private DcpToolRegistry $tools){}

 public function recordProviderProposal(string $jobId,ProviderToolCallResult $response):array{
  if($response->refusal!==null)return ['status'=>'refused','tool_calls'=>0,'execution_started'=>false,'automatic_execution'=>false,'message'=>$response->refusal];
  if($response->toolCalls===[])return ['status'=>'no_tool_calls','tool_calls'=>0,'execution_started'=>false,'automatic_execution'=>false];
  if(count($response->toolCalls)>self::MAX_TOOL_CALLS)throw new InvalidArgumentException('Coordinator tool-call budget exceeded.');

  $validated=[];$seen=[];$totalBytes=0;
  foreach($response->toolCalls as $index=>$call){
   $id=(string)$call['id'];if(isset($seen[$id]))throw new InvalidArgumentException('Provider returned duplicate tool-call IDs.');$seen[$id]=true;
   $encoded=json_encode($call['arguments'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);$totalBytes+=strlen($encoded);
   if($totalBytes>self::MAX_TOTAL_ARGUMENT_BYTES)throw new InvalidArgumentException('Coordinator aggregate argument budget exceeds 16 KB.');
   $inspection=$this->tools->validateProposal((string)$call['name'],$call['arguments']);
   $validated[]=['index'=>$index+1,'id'=>$id,'name'=>(string)$call['name'],'arguments'=>$call['arguments'],'arguments_hash'=>$inspection['arguments_hash'],'policy'=>$inspection['policy']];
  }

  $context=$this->jobs->executionContextForJob($jobId);$version=((int)($context['plan_version']??0))+1;
  $steps=[];$planCalls=[];$approvalRequests=[];
  foreach($validated as $call){
   $key='tool_'.str_pad((string)$call['index'],3,'0',STR_PAD_LEFT);
   $idem='dcp_tool_'.substr(hash('sha256',$jobId.'|'.$version.'|'.$call['id'].'|'.$call['arguments_hash']),0,48);
   $steps[]=['key'=>$key,'title'=>$call['name'],'depends_on'=>[],'idempotency_key'=>$idem];
   $planCalls[]=['step_key'=>$key,'provider_call_id'=>$call['id'],'tool_name'=>$call['name'],'arguments'=>$call['arguments'],'arguments_hash'=>$call['arguments_hash'],'classification'=>$call['policy']['classification'],'capability'=>$call['policy']['capability'],'approval_required'=>(bool)$call['policy']['approval_required'],'execution_status'=>'not_started'];
  }
  $plan=['schema_version'=>1,'kind'=>'provider_tool_proposal','plan_version'=>$version,'provider_finish_reason'=>$response->finishReason,'assistant_context'=>$response->content,'tool_calls'=>$planCalls,'human_review_required'=>true,'automatic_execution'=>false,'execution_started'=>false,'budgets'=>['max_tool_calls'=>self::MAX_TOOL_CALLS,'max_total_argument_bytes'=>self::MAX_TOTAL_ARGUMENT_BYTES]];
  $planBytes=strlen(json_encode($plan,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
  if($planBytes>self::MAX_PLAN_METADATA_BYTES)throw new InvalidArgumentException('Coordinator plan metadata exceeds 32 KB.');
  $saved=$this->jobs->saveExecutionPlan($jobId,$version,$plan,$steps);
  foreach($validated as $call){
   if(!$call['policy']['approval_required'])continue;
   $key='tool_'.str_pad((string)$call['index'],3,'0',STR_PAD_LEFT);
   $approvalKey='approval_'.substr(hash('sha256',$jobId.'|'.$version.'|'.$call['id']),0,24);
   $summary='Approve '.$call['name'].' on this DCP installation (arguments SHA-256: '.$call['arguments_hash'].').';
   $approval=$this->jobs->requestExecutionApproval($jobId,$key,$approvalKey,$summary);
   $this->jobs->setExecutionStepStatus($jobId,$key,'blocked');
   $approvalRequests[]=$approval+['step_key'=>$key,'tool_name'=>$call['name'],'arguments_hash'=>$call['arguments_hash']];
  }
  return ['status'=>'proposal_pending_review','plan_version'=>$version,'tool_calls'=>count($validated),'approval_requests'=>$approvalRequests,'execution_started'=>false,'automatic_execution'=>false,'human_review_required'=>true,'execution_context'=>$this->jobs->executionContextForJob($jobId)];
 }
}

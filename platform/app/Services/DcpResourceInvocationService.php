<?php
declare(strict_types=1);

require_once __DIR__.'/DcpResourceChatClient.php';

/** Executes a registered AI resource only on its owner device, resolving credentials through ProviderService. */
final class DcpResourceInvocationService {
    public function __construct(
        private DcpResourceRegistryService $registry,
        private DcpResourceChatClient $chat
    ) {}

    public function invoke(string $resourceId, string $ownerId, string $message): array {
        if (trim($message)==='' || strlen($message)>200000) throw new InvalidArgumentException('Resource prompt must contain 1–200,000 bytes.');
        $resource=$this->registry->get($resourceId,$ownerId);
        if (!in_array('invoke',$resource['operations'],true)) throw new RuntimeException('This resource does not grant the invoke operation.');
        if (!in_array($resource['kind'],['local_model','inference_engine','external_api'],true)) throw new RuntimeException('This resource kind does not yet have a verified invocation adapter.');
        $model=trim((string)$resource['model']);if($model==='')throw new RuntimeException('The resource has no configured model identifier.');
        $providerId='';
        if ((string)$resource['provider']==='ollama') {
            $providerId='ollama';
        } else {
            $secretRef=(string)$resource['secret_ref'];
            if (!preg_match('/^provider:([A-Za-z0-9_-]{1,80})$/',$secretRef,$match)) throw new RuntimeException('This resource has no owner-local provider credential reference.');
            $providerId=$match[1];
        }
        $invocation=$this->registry->beginInvocation($resourceId,'user',$ownerId,'invoke');
        try {
            $text=$this->chat->send($providerId,$model,$message);
            if (strlen($text)>65536) $text=substr($text,0,65536);
            $this->registry->finishInvocation($invocation['invocation_id'],$ownerId,'completed');
            return ['invocation_id'=>$invocation['invocation_id'],'resource_id'=>$resourceId,'status'=>'completed','provider'=>$resource['provider'],'model'=>$model,'text'=>$text];
        } catch (Throwable $error) {
            try {$this->registry->finishInvocation($invocation['invocation_id'],$ownerId,'failed');} catch (Throwable) {}
            error_log('[DCP Resource Invocation] '.$error::class.': '.$error->getMessage());
            throw new RuntimeException('Resource invocation failed. Credentials and provider response details were not returned to the caller.',0,$error);
        }
    }
}

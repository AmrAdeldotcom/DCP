<?php
declare(strict_types=1);

final class CloudTerminalExecutionService {
    public function __construct(
        private PDO $db,
        private CloudTerminalCloudClient $cloud,
        private CloudTerminalShellExecutor $terminal,
        private CloudTerminalDeviceSigner $envelopes
    ) {}

    private function redactOutput(string $output): string {
        $output = preg_replace("#(?i)(authorization\\s*:\\s*bearer\\s+)[A-Za-z0-9._~+/=-]+#", '$1[REDACTED]', $output) ?? $output;
        $output = preg_replace("#(?i)\\b(api[_-]?key|access[_-]?token|refresh[_-]?token|password|passwd|secret|client[_-]?secret)(\\s*[:=]\\s*)\\S+#", '$1$2[REDACTED]', $output) ?? $output;
        $output = preg_replace('/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s', '[REDACTED PRIVATE KEY]', $output) ?? $output;
        return substr($output, 0, 16384);
    }

    public function executeApproved(string $requestId): array {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $requestId)) throw new InvalidArgumentException('Invalid remote terminal request.');
        $device = $this->cloud->deviceIdentity();
        $deviceId = (string)$device['device_id'];
        $localKey = $this->envelopes->registerSigningKey($deviceId);
        $this->cloud->registerTerminalDeviceSigningKey((string)$localKey['public_key']);
        $dispatch = $this->cloud->dispatchRemoteTerminalRequest($requestId);
        $envelope = $dispatch['envelope'] ?? null;
        $signature = (string)($dispatch['signature'] ?? '');
        if (!is_array($envelope) || !$this->cloud->verifyTerminalDispatch($envelope, $signature)) {
            throw new RuntimeException('Cloud terminal envelope signature or exact approval binding is invalid. Nothing was executed.');
        }
        if (!hash_equals($requestId, (string)$envelope['request_id']) || !hash_equals($deviceId, (string)$envelope['target_device_id'])) {
            throw new RuntimeException('Cloud terminal envelope targets another request or device. Nothing was executed.');
        }

        $existing = $this->db->prepare('SELECT status,result_json,result_signature FROM cloud_terminal_executions WHERE request_id=?');
        $existing->execute([$requestId]);
        $prior = $existing->fetch(PDO::FETCH_ASSOC);
        if ($prior) {
            if (in_array($prior['status'], ['completed','failed'], true) && is_string($prior['result_json']) && is_string($prior['result_signature'])) {
                $saved = json_decode($prior['result_json'], true, 32, JSON_THROW_ON_ERROR);
                $ack = $this->cloud->submitRemoteTerminalResult($requestId, $saved, $prior['result_signature']);
                return ['request_id'=>$requestId,'status'=>$prior['status'],'result'=>$saved,'cloud_ack'=>$ack,'replayed'=>true,'executed_again'=>false];
            }
            throw new RuntimeException('This approved command already has a running or incomplete execution record. It will not be executed again automatically.');
        }

        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare("INSERT INTO cloud_terminal_executions(envelope_id,request_id,target_device_id,command_sha256,status,created_at) VALUES(?,?,?,?, 'running', ?)");
            $q->execute([(string)$envelope['envelope_id'],$requestId,$deviceId,(string)$envelope['command_sha256'],date(DATE_ATOM)]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Remote command execution was claimed concurrently or already consumed. No second execution was started.', 0, $e);
        }

        try {
            $execution = $this->terminal->execute(
                (string)$envelope['command'],
                (string)$envelope['working_directory'],
                (int)$envelope['timeout_seconds'],
                (string)$envelope['shell']
            );
            $output = $this->redactOutput((string)($execution['output'] ?? ''));
            $exitCode = isset($execution['exit_code']) ? (int)$execution['exit_code'] : null;
            $timedOut = (bool)($execution['timed_out'] ?? false);
            $duration = max(0,min(120000,(int)($execution['duration_ms'] ?? 0)));
            $status = ($exitCode === 0 && !$timedOut) ? 'completed' : 'failed';
        } catch (Throwable $e) {
            $output = $this->redactOutput($e->getMessage());
            $exitCode = null; $timedOut = false; $duration = 0; $status = 'failed';
        }

        $result = [
            'schema_version'=>1,
            'request_id'=>$requestId,
            'envelope_id'=>(string)$envelope['envelope_id'],
            'target_device_id'=>$deviceId,
            'command_sha256'=>(string)$envelope['command_sha256'],
            'status'=>$status,
            'exit_code'=>$exitCode,
            'timed_out'=>$timedOut,
            'output'=>$output,
            'duration_ms'=>$duration,
            'completed_at'=>date(DATE_ATOM),
        ];
        $resultSignature = $this->envelopes->signPayload($deviceId, $result);
        $save = $this->db->prepare('UPDATE cloud_terminal_executions SET status=?,result_json=?,result_signature=?,completed_at=? WHERE request_id=? AND status=\'running\'');
        $save->execute([$status,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$resultSignature,date(DATE_ATOM),$requestId]);
        if ($save->rowCount() !== 1) throw new RuntimeException('Local remote-execution ledger changed unexpectedly; signed result was not submitted.');
        $ack = $this->cloud->submitRemoteTerminalResult($requestId, $result, $resultSignature);
        return ['request_id'=>$requestId,'status'=>$status,'result'=>$result,'cloud_ack'=>$ack,'replayed'=>false,'executed_again'=>false];
    }
}

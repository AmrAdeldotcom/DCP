<?php
declare(strict_types=1);

final class CloudService {
    private const BASE_URL = 'https://dcp.amradel.com/api/v1';

    public function __construct(private PDO $db, private Crypto $crypto) {}

    private function value(string $key): string {
        $q = $this->db->prepare('SELECT value FROM settings WHERE key=?');
        $q->execute([$key]);
        return (string)($q->fetchColumn() ?: '');
    }

    private function save(string $key, string $value): void {
        $q = $this->db->prepare('INSERT INTO settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at');
        $q->execute([$key, $value, date(DATE_ATOM)]);
    }

    private function secret(string $key): string {
        $stored = $this->value($key);
        return $stored === '' ? '' : $this->crypto->decrypt($stored);
    }

    private function saveSecret(string $key, string $value): void {
        $this->save($key, $value === '' ? '' : $this->crypto->encrypt($value));
    }

    private function request(string $method, string $path, ?array $body = null, string $token = ''): array {
        $ch = curl_init(self::BASE_URL . $path);
        if ($ch === false) throw new RuntimeException('Could not initialize the DCP Cloud connection.');
        $headers = ['Accept: application/json'];
        if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('DCP Cloud is unreachable: ' . ($error ?: 'network error'));
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) throw new RuntimeException('DCP Cloud returned an invalid response (HTTP ' . $status . ').');
        if ($status === 401) throw new RuntimeException('DCP Cloud session expired or was revoked. Sign in again.');
        if ($status < 200 || $status >= 300) {
            $message = (string)($data['error'] ?? $data['message'] ?? ('HTTP ' . $status));
            throw new RuntimeException('DCP Cloud: ' . $message);
        }
        return $data;
    }

    public function register(string $email, string $password, string $displayName): array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid DCP Cloud email address.');
        if (strlen($password) < 12) throw new InvalidArgumentException('DCP Cloud passwords must contain at least 12 characters.');
        $result = $this->request('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'display_name' => trim($displayName) !== '' ? trim($displayName) : 'DCP User',
        ]);
        $this->storeSession($email, (string)($result['access_token'] ?? ''));
        return $this->status();
    }

    public function login(string $email, string $password): array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') throw new InvalidArgumentException('Enter your DCP Cloud email and password.');
        $result = $this->request('POST', '/login', ['email' => $email, 'password' => $password]);
        $this->storeSession($email, (string)($result['access_token'] ?? ''));
        return $this->status();
    }

    private function storeSession(string $email, string $token): void {
        if (strlen($token) < 40) throw new RuntimeException('DCP Cloud did not issue a valid account token.');
        $sameAccount = strtolower($this->value('cloud.account_email')) === strtolower($email);
        $this->save('cloud.account_email', $email);
        $this->saveSecret('cloud.user_token_enc', $token);
        if (!$sameAccount) {
            $this->save('cloud.device_id', '');
            $this->saveSecret('cloud.device_token_enc', '');
        }
    }

    private function ensureDevice(string $userToken): array {
        $deviceId = $this->value('cloud.device_id');
        $deviceToken = $this->secret('cloud.device_token_enc');
        if ($deviceId !== '' && $deviceToken !== '') return ['device_id' => $deviceId, 'device_token' => $deviceToken];

        $label = gethostname() ?: 'DCP Device';
        $result = $this->request('POST', '/devices/register', [
            'label' => mb_substr($label, 0, 120),
            'platform' => PHP_OS_FAMILY . ' ' . php_uname('m'),
        ], $userToken);
        $deviceId = (string)($result['device_id'] ?? '');
        $deviceToken = (string)($result['device_token'] ?? '');
        if ($deviceId === '' || strlen($deviceToken) < 40) throw new RuntimeException('DCP Cloud did not return valid device credentials.');
        $this->save('cloud.device_id', $deviceId);
        $this->saveSecret('cloud.device_token_enc', $deviceToken);
        return ['device_id' => $deviceId, 'device_token' => $deviceToken];
    }

    public function status(): array {
        $userToken = $this->secret('cloud.user_token_enc');
        if ($userToken === '') return ['configured' => false, 'connected' => false, 'account' => '', 'device_id' => '', 'devices' => []];

        try {
            $device = $this->ensureDevice($userToken);
            $this->request('POST', '/devices/heartbeat', [], $device['device_token']);
            $result = $this->request('GET', '/devices', null, $userToken);
            return [
                'configured' => true,
                'connected' => true,
                'account' => $this->value('cloud.account_email'),
                'device_id' => $device['device_id'],
                'devices' => array_values($result['devices'] ?? []),
                'message' => 'DCP Cloud account and device heartbeat verified.',
            ];
        } catch (Throwable $e) {
            return [
                'configured' => true,
                'connected' => false,
                'account' => $this->value('cloud.account_email'),
                'device_id' => $this->value('cloud.device_id'),
                'devices' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    public function logout(): array {
        $userToken = $this->secret('cloud.user_token_enc');
        $deviceId = $this->value('cloud.device_id');
        if ($userToken !== '' && $deviceId !== '') {
            try { $this->request('POST', '/devices/' . rawurlencode($deviceId) . '/revoke', [], $userToken); }
            catch (Throwable $e) { /* Local credentials are still cleared on logout. */ }
        }
        foreach (['cloud.account_email', 'cloud.device_id', 'cloud.user_token_enc', 'cloud.device_token_enc'] as $key) $this->save($key, '');
        return ['ok' => true, 'configured' => false, 'message' => 'DCP Cloud signed out on this device.'];
    }

    public function requestConnection(string $targetDevice): array {
        $deviceToken = $this->secret('cloud.device_token_enc');
        if ($deviceToken === '') throw new RuntimeException('Sign in to DCP Cloud on this device first.');
        if ($targetDevice === '' || $targetDevice === $this->value('cloud.device_id')) throw new InvalidArgumentException('Choose another paired device.');
        return $this->request('POST', '/connections/request', ['target_device' => $targetDevice], $deviceToken);
    }

    public function sendSignal(string $requestId, string $envelope): array {
        $deviceToken = $this->secret('cloud.device_token_enc');
        if ($deviceToken === '') throw new RuntimeException('Sign in to DCP Cloud on this device first.');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $requestId)) throw new InvalidArgumentException('Invalid connection request ID.');
        if ($envelope === '' || strlen($envelope) > 32768) throw new InvalidArgumentException('Invalid signaling envelope.');
        return $this->request('POST', '/connections/' . $requestId . '/signal', ['envelope' => $envelope], $deviceToken);
    }

    public function signals(string $requestId, int $after = 0): array {
        $deviceToken = $this->secret('cloud.device_token_enc');
        if ($deviceToken === '') throw new RuntimeException('Sign in to DCP Cloud on this device first.');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $requestId)) throw new InvalidArgumentException('Invalid connection request ID.');
        return $this->request('GET', '/connections/' . $requestId . '/signals?after=' . max(0, $after), null, $deviceToken);
    }
}

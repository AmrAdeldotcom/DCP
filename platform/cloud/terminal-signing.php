<?php
declare(strict_types=1);

function dcp_cloud_terminal_canonical_json(array $value): string {
    $normalize = static function ($item) use (&$normalize) {
        if (!is_array($item)) return $item;
        if (array_is_list($item)) return array_map($normalize, $item);
        ksort($item, SORT_STRING);
        foreach ($item as $key => $child) $item[$key] = $normalize($child);
        return $item;
    };
    return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function dcp_cloud_terminal_signing_keys(): array {
    $encoded = (string)getenv('DCP_CLOUD_SIGNING_SEED');
    $seed = base64_decode($encoded, true);
    if ($seed === false || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        throw new RuntimeException('DCP Cloud terminal signing seed is not configured.');
    }
    $pair = sodium_crypto_sign_seed_keypair($seed);
    sodium_memzero($seed);
    $public = sodium_crypto_sign_publickey($pair);
    $secret = sodium_crypto_sign_secretkey($pair);
    return ['public_key' => $public, 'secret_key' => $secret, 'key_id' => hash('sha256', $public)];
}

function dcp_cloud_terminal_sign(array $payload): string {
    $keys = dcp_cloud_terminal_signing_keys();
    try {
        return base64_encode(sodium_crypto_sign_detached(dcp_cloud_terminal_canonical_json($payload), $keys['secret_key']));
    } finally {
        sodium_memzero($keys['secret_key']);
    }
}

function dcp_cloud_terminal_verify(array $payload, string $signature, string $publicKey): bool {
    if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return false;
    $raw = base64_decode($signature, true);
    return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_BYTES
        && sodium_crypto_sign_verify_detached($raw, dcp_cloud_terminal_canonical_json($payload), $publicKey);
}

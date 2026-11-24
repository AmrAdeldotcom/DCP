<?php
declare(strict_types=1);

/**
 * Return current and optional previous-key hashes for safe API key rotation.
 * The raw token is never stored or logged; callers only compare HMAC digests.
 */
function dcp_cloud_hash_token(string $token, string $key): string
{
    return hash_hmac('sha256', $token, $key);
}

function dcp_cloud_hash_token_candidates(string $token, string $currentKey, string $previousKeys = ''): array
{
    if ($currentKey === '') return [];
    $keys = [$currentKey];
    foreach (explode(',', $previousKeys) as $previous) {
        $previous = trim($previous);
        if ($previous !== '' && !hash_equals($currentKey, $previous) && !in_array($previous, $keys, true)) {
            $keys[] = $previous;
        }
    }
    return array_map(static fn(string $key): string => dcp_cloud_hash_token($token, $key), $keys);
}

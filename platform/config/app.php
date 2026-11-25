<?php
declare(strict_types=1);

$appRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
$osKey = match (PHP_OS_FAMILY) {
    'Darwin' => 'macOS',
    'Windows' => 'Windows',
    'Linux' => 'Linux',
    default => preg_replace('/[^A-Za-z0-9_-]/', '', PHP_OS_FAMILY) ?: 'Other',
};
$mode = (string)($_SERVER['DCP_MODE'] ?? getenv('DCP_MODE') ?: 'desktop');
$userDataRoot = getenv('DCP_USER_DATA_DIR') ?: (
    $appRoot . '/DCP-UserData/' . ($mode === 'web-preview' ? 'WebPreview' : $osKey)
);
$storage = rtrim($userDataRoot, '/\\') . DIRECTORY_SEPARATOR . 'storage';
$versionManifestPath = dirname(__DIR__) . '/version.json';
$versionManifest = is_file($versionManifestPath)
    ? json_decode((string)file_get_contents($versionManifestPath), true)
    : null;
$appVersion = (string)($versionManifest['version'] ?? 'dev');
$appVersion = preg_replace('/^v/', '', trim($appVersion)) ?: 'dev';
if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:-[0-9A-Za-z.-]+)?$/', $appVersion)) {
    $appVersion = 'dev';
}
$releaseTag = (string)($versionManifest['releaseTag'] ?? ('v' . $appVersion));
$appBuild = (string)($versionManifest['build'] ?? '1');

return [
    'name' => 'DCP',
    'version' => $appVersion,
    'release_tag' => $releaseTag,
    'build' => $appBuild,
    'bind' => '127.0.0.1',
    'port' => 60776,
    'database' => rtrim($userDataRoot, '/\\') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'dcp.sqlite',
    'storage' => $storage,
    'key_file' => $storage . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'master.key',
    'user_data' => $userDataRoot,
    'mode' => $mode,
    'base_path' => (string)($_SERVER['DCP_BASE_PATH'] ?? getenv('DCP_BASE_PATH') ?: ''),
    'cloud_base_url' => rtrim(getenv('DCP_CLOUD_BASE_URL') ?: 'https://dcp.amradel.com', '/'),
    'session_name' => 'DCPSESSID',
    'timezone' => 'Africa/Cairo',
    'default_locale' => 'en',
    'supported_locales' => ['en', 'ar', 'nl', 'es', 'pt', 'fr', 'de', 'zh', 'ja'],
    'ollama_url' => 'http://127.0.0.1:11434',
    'terminal_root' => $appRoot,
    'max_terminal_seconds' => 8,
];

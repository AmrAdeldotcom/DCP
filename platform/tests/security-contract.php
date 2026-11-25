<?php
declare(strict_types=1);

$router = file_get_contents(__DIR__ . '/../public/router.php');
$htaccess = file_get_contents(__DIR__ . '/../.htaccess');
$webEntry = file_get_contents(__DIR__ . '/../index.php');
$bootstrap = file_get_contents(__DIR__ . '/../bootstrap/app.php');
$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$cloud = file_get_contents(__DIR__ . '/../app/Services/CloudService.php');
$terminal = file_get_contents(__DIR__ . '/../app/Services/TerminalService.php');
$gguf = file_get_contents(__DIR__ . '/../app/Services/CustomModelManager.php');
$gitignore = file_get_contents(__DIR__ . '/../../.gitignore');
$sources = [$router,$htaccess,$webEntry,$bootstrap,$page,$api,$cloud,$terminal,$gguf,$gitignore];
if (in_array(false, $sources, true)) {
    fwrite(STDERR, "FAIL: security contract source could not be read.\n");
    exit(1);
}
$checks = [
    'desktop router decodes paths and serves only real files under public/assets' => str_contains($router, 'rawurldecode') && str_contains($router, "realpath(__DIR__ . '/assets')") && str_contains($router, 'str_starts_with($candidate, $assetRoot . DIRECTORY_SEPARATOR)'),
    'desktop router restricts served asset extensions and never serves arbitrary files' => str_contains($router, 'in_array($extension, $allowed, true)') && !str_contains($router, '$file=__DIR__.$path'),
    'Apache blocks internal code, data, secrets and direct PHP execution' => str_contains($htaccess, '(app|bootstrap|config|cloud|') && str_contains($htaccess, '(sqlite3?') && str_contains($htaccess, 'RewriteRule \\.php$ - [F,L,NC]'),
    'web preview forcibly uses a dedicated data root separate from desktop profiles' => str_contains($webEntry, "DCP_MODE=web-preview") && str_contains($webEntry, 'DCP_WEB_PREVIEW_DATA_DIR') && str_contains($webEntry, 'DCP_USER_DATA_DIR='),
    'bootstrap uses restrictive file permissions and hides PHP errors from clients' => str_contains($bootstrap, 'umask(0077)') && str_contains($bootstrap, "ini_set('display_errors','0')") && str_contains($bootstrap, "'httponly'=>true") && str_contains($bootstrap, "'samesite'=>'Strict'"),
    'application pages set a nonce-based CSP and deny framing/object execution' => str_contains($page, 'Content-Security-Policy') && str_contains($page, "frame-ancestors 'none'") && str_contains($page, "object-src 'none'") && str_contains($page, 'nonce=') && str_contains($page, 'Permissions-Policy: camera=()'),
    'all state-changing API POST routes require a timing-safe CSRF token check' => str_contains($api, 'hash_equals($_SESSION[\'csrf\'],$token)') && str_contains($api, 'if($method===\'POST\')$this->csrf();'),
    'Cloud client requires HTTPS except explicit loopback test URLs and verifies certificates' => str_contains($cloud, 'if ($scheme !== \'https\'') && str_contains($cloud, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($cloud, 'CURLOPT_SSL_VERIFYHOST=>2') && str_contains($cloud, 'CURLOPT_FOLLOWLOCATION=>false'),
    'full terminal accepts any existing working directory and executes via the current OS identity without a command allow-list' => str_contains($terminal, 'realpath($working)') && str_contains($terminal, 'proc_open($args') && str_contains($api, '$this->terminal->execute') && !str_contains($terminal, 'function run('),
    'repository ignores local signing keys and disposable test harnesses' => str_contains($gitignore, 'DCP-KEYS/') && str_contains($gitignore, '.tmp/'),
    'GGUF import enforces extension, size, symlink, hash and private file mode checks' => str_contains($gguf, "PATHINFO_EXTENSION") && str_contains($gguf, '21474836480') && str_contains($gguf, 'is_link($source)') && str_contains($gguf, "hash_file('sha256'") && str_contains($gguf, '0600'),
];
$cloudClient = file_get_contents(__DIR__ . '/../app/Services/CloudService.php');
$macWrapper = file_get_contents(__DIR__ . '/../wrappers/macos/main.m');
$winWrapper = file_get_contents(__DIR__ . '/../wrappers/windows/Program.cs');
$linuxWrapper = file_get_contents(__DIR__ . '/../wrappers/linux/dcp-webview.c');
$checks['Cloud heartbeat retries transient network failures without retrying unsafe mutations'] = str_contains($cloudClient, "\$path==='/devices/heartbeat'") && str_contains($cloudClient, '$attempts=') && str_contains($cloudClient, 'automatic reconnect attempts');
$checks['all three desktop launchers permit large GGUF file uploads'] = str_contains($macWrapper, 'upload_max_filesize=1G') && str_contains($winWrapper, 'upload_max_filesize=1G') && str_contains($linuxWrapper, 'upload_max_filesize=1G');
$checks['Windows and macOS desktop wrapper logs start fresh on each launch'] = str_contains($macWrapper, 'removeItemAtPath:log.path') && str_contains($winWrapper, 'File.WriteAllText(LogPath, string.Empty)');
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL SECURITY CONTRACT CHECKS PASSED\n" : "$failed SECURITY CONTRACT CHECK(S) FAILED\n";
if ($failed > 0) throw new RuntimeException($failed . ' security contract check(s) failed.');

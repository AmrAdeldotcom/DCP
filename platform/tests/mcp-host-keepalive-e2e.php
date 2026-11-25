<?php
declare(strict_types=1);

$platform = dirname(__DIR__);
$server = $platform . '/plugins/tools/mcp-host/server.php';
$data = sys_get_temp_dir() . '/dcp-mcp-keepalive-' . bin2hex(random_bytes(4));
$env = getenv();
if (!is_array($env)) $env = [];
$env['DCP_USER_DATA_DIR'] = $data;
$env['DCP_MODE'] = 'desktop';
$env['DCP_MCP_PING_INTERVAL'] = '1';

$pipes = [];
$process = proc_open([PHP_BINARY, $server], [
    0=>['pipe','r'],
    1=>['pipe','w'],
    2=>['pipe','w'],
], $pipes, $platform, $env);
if (!is_resource($process)) throw new RuntimeException('Unable to start MCP keepalive test host.');
stream_set_timeout($pipes[1], 5);

$send = static function(array $message) use ($pipes): void {
    $line = json_encode($message, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($pipes[0], $line) !== strlen($line)) throw new RuntimeException('Unable to write MCP test request.');
    fflush($pipes[0]);
};
$read = static function() use ($pipes): array {
    $line = fgets($pipes[1]);
    if ($line === false) throw new RuntimeException('Timed out waiting for MCP server message.');
    $decoded = json_decode($line, true);
    if (!is_array($decoded)) throw new RuntimeException('MCP server emitted invalid JSON: ' . $line);
    return $decoded;
};

$send(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25']]);
$initialize = $read();
if (($initialize['result']['serverInfo']['name'] ?? '') !== 'DCP Core') throw new RuntimeException('MCP initialize identity mismatch.');
$icons = $initialize['result']['serverInfo']['icons'] ?? [];
if (($icons[0]['mimeType'] ?? '') !== 'image/png' || !str_starts_with((string)($icons[0]['src'] ?? ''), 'data:image/png;base64,')) throw new RuntimeException('DCP Core logo was not advertised.');

$ping = $read();
if (($ping['method'] ?? '') !== 'ping' || !str_starts_with((string)($ping['id'] ?? ''), 'dcp-ping-')) throw new RuntimeException('MCP server did not send its idle keepalive ping.');
$send(['jsonrpc'=>'2.0','id'=>$ping['id'],'result'=>new stdClass()]);
$send(['jsonrpc'=>'2.0','id'=>2,'method'=>'ping','params'=>new stdClass()]);
$clientPing = $read();
if (($clientPing['id'] ?? null) !== 2 || !isset($clientPing['result'])) throw new RuntimeException('MCP server failed to answer client ping after keepalive.');

$send(['jsonrpc'=>'2.0','id'=>3,'method'=>'tools/list','params'=>new stdClass()]);
$tools = $read();
if (count($tools['result']['tools'] ?? []) !== 35) throw new RuntimeException('MCP tool registry must expose exactly 35 tools with one full terminal.');

fclose($pipes[0]);
$stdoutRemainder = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
if ($exit !== 0) throw new RuntimeException('MCP host did not exit cleanly after stdin EOF. ' . $stderr . $stdoutRemainder);

echo "DCP_MCP_KEEPALIVE_LOGO_E2E_PASS\n";

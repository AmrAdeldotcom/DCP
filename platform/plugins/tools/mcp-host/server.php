<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 3);
$config = require $root . '/config/app.php';

require $root . '/app/Core/Database.php';
require $root . '/app/Core/Crypto.php';
require $root . '/app/Core/PluginManager.php';
require $root . '/app/Core/ProviderAdapter.php';
require $root . '/app/Core/AgentAdapter.php';
require $root . '/app/Core/MailAdapter.php';
require $root . '/app/Core/ToolAdapter.php';
require $root . '/app/Core/TaskExecutor.php';
require $root . '/app/Memory/MarkdownJsonMemory.php';
require $root . '/app/Services/DcpMemoryService.php';
require $root . '/app/Services/DcpResourceRegistryService.php';
require $root . '/app/Services/ProviderService.php';
require $root . '/app/Services/AgentService.php';
require $root . '/app/Services/MailService.php';
require $root . '/app/Services/TerminalService.php';
require $root . '/app/Services/DcpFilesystemService.php';
require $root . '/app/Services/DeviceService.php';
require $root . '/app/Services/CloudService.php';
require $root . '/app/Services/ChatService.php';
require $root . '/app/Services/DcpToolRegistry.php';

$db = Database::connect($config);
Database::migrate($db);
$plugins = new PluginManager($root . '/plugins');
$crypto = new Crypto($config['key_file']);
$provider = new ProviderService($db, $crypto, $plugins);
$devices = new DeviceService($db);
$terminalPlugin = $plugins->get('terminal');
if (!$terminalPlugin) {
    throw new RuntimeException('DCP terminal plugin is not installed.');
}
$terminalFactory = require dirname($terminalPlugin['_manifest']) . '/driver.php';
$terminal = $terminalFactory->build($config);
$registry = new DcpToolRegistry($config, $db, $provider, $devices, $terminal, $plugins, $crypto);
$tools = $registry->definitions();

$memory = new DcpMemoryService($config);
$memoryTools = [
    ['name'=>'dcp_memory_bootstrap','description'=>'Load compact hierarchical Markdown+JSON project memory.','inputSchema'=>['type'=>'object','properties'=>['project'=>['type'=>'string','maxLength'=>80],'agent'=>['type'=>'string','maxLength'=>80]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
    ['name'=>'dcp_memory_recall','description'=>'Recall bounded memory snippets relevant to a query.','inputSchema'=>['type'=>'object','properties'=>['query'=>['type'=>'string','minLength'=>1,'maxLength'=>2000],'project'=>['type'=>'string','maxLength'=>80],'agent'=>['type'=>'string','maxLength'=>80],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>8]],'required'=>['query','project'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
    ['name'=>'dcp_memory_read','description'=>'Read one Markdown memory entry by ID.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'string','minLength'=>1,'maxLength'=>100]],'required'=>['id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
    ['name'=>'dcp_memory_remember','description'=>'Save a durable memory to the local Markdown vault and JSON index.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'string','maxLength'=>100],'title'=>['type'=>'string','minLength'=>1,'maxLength'=>240],'content'=>['type'=>'string','minLength'=>1,'maxLength'=>20000],'level'=>['type'=>'string','enum'=>['global','project','task','session','agent']],'project'=>['type'=>'string','maxLength'=>80],'agent'=>['type'=>'string','maxLength'=>80],'summary'=>['type'=>'string','maxLength'=>500],'tags'=>['type'=>'array','maxItems'=>20,'items'=>['type'=>'string','maxLength'=>40]]],'required'=>['title','content','level','project'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false]],
    ['name'=>'dcp_memory_retire','description'=>'Soft-retire one memory while preserving history.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'string','minLength'=>1,'maxLength'=>100],'reason'=>['type'=>'string','minLength'=>1,'maxLength'=>1000]],'required'=>['id','reason'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false]],
];

if (getenv('DCP_MCP_MEMORY_TOOLS') === '1') {
    $tools = array_merge($tools, array_slice($memoryTools, 0, 3));
}

$logoPath = $root . '/public/assets/dcp-logo.png';
$logoDataUri = null;
if (is_file($logoPath) && filesize($logoPath) <= 200000) {
    $logoDataUri = 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoPath));
}

function dcpMcpText(mixed $value): array
{
    return ['content'=>[['type'=>'text','text'=>json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]]];
}

function dcpMcpWrite(array $message): void
{
    $json = json_encode($message, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    $written = fwrite(STDOUT, $json . "\n");
    if ($written === false) {
        throw new RuntimeException('Unable to write MCP response to stdout.');
    }
    fflush(STDOUT);
}

$pingInterval = (int)(getenv('DCP_MCP_PING_INTERVAL') ?: 25);
$pingInterval = max(1, min(55, $pingInterval));
$pingTimeout = 15;
$lastPingAt = microtime(true);
$pingCounter = 0;
$pendingPing = null;
$pendingPingAt = 0.0;
$inputBuffer = '';
stream_set_blocking(STDIN, false);

while (true) {
    $read = [STDIN];
    $write = [];
    $except = [];
    $ready = @stream_select($read, $write, $except, 1);

    if ($ready === false) {
        usleep(100000);
        continue;
    }

    if ($ready > 0) {
        $chunk = fread(STDIN, 65536);
        if ($chunk !== false && $chunk !== '') {
            $inputBuffer .= $chunk;
            while (($newline = strpos($inputBuffer, "\n")) !== false) {
                $line = trim(substr($inputBuffer, 0, $newline));
                $inputBuffer = substr($inputBuffer, $newline + 1);
                if ($line === '') {
                    continue;
                }
                $request = json_decode($line, true);
                if (!is_array($request)) {
                    continue;
                }

                $requestId = $request['id'] ?? null;
                $method = (string)($request['method'] ?? '');
                $params = $request['params'] ?? [];

                // A response to a server-initiated MCP ping is not a client request.
                if ($method === '' && $requestId !== null && $pendingPing !== null && (string)$requestId === (string)$pendingPing) {
                    $pendingPing = null;
                    $pendingPingAt = 0.0;
                    continue;
                }

                try {
                    if ($method === 'initialize') {
                        $serverInfo = ['name'=>'DCP Core','version'=>$config['version']];
                        if ($logoDataUri !== null) {
                            $serverInfo['icons'] = [[
                                'src'=>$logoDataUri,
                                'mimeType'=>'image/png',
                                'sizes'=>['128x128'],
                            ]];
                        }
                        $result = [
                            'protocolVersion'=>$params['protocolVersion'] ?? '2024-11-05',
                            'capabilities'=>['tools'=>['listChanged'=>false]],
                            'serverInfo'=>$serverInfo,
                        ];
                    } elseif ($method === 'ping') {
                        $result = new stdClass();
                    } elseif ($method === 'tools/list') {
                        $result = ['tools'=>$tools];
                    } elseif ($method === 'tools/call') {
                        $name = (string)($params['name'] ?? '');
                        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
                        $memoryRead = ['dcp_memory_bootstrap','dcp_memory_recall','dcp_memory_read'];
                        $memoryWrite = ['dcp_memory_remember','dcp_memory_retire'];
                        if (in_array($name, $memoryRead, true)) {
                            if ($name === 'dcp_memory_bootstrap') {
                                $value = $memory->bootstrap((string)($arguments['project'] ?? 'global'), (string)($arguments['agent'] ?? 'any'));
                            } elseif ($name === 'dcp_memory_recall') {
                                $value = $memory->recall((string)$arguments['query'], (string)$arguments['project'], (string)($arguments['agent'] ?? 'any'), (int)($arguments['limit'] ?? 5));
                            } else {
                                $value = $memory->read((string)$arguments['id']);
                            }
                            $result = dcpMcpText($value);
                        } elseif (in_array($name, $memoryWrite, true)) {
                            throw new RuntimeException('DCP memory mutation through MCP is disabled until the explicit memory-write scope/approval gate is configured.');
                        } else {
                            $result = dcpMcpText($registry->call($name, $arguments, ['caller'=>'mcp','integration_configured'=>true]));
                        }
                    } elseif (str_starts_with($method, 'notifications/')) {
                        continue;
                    } else {
                        throw new RuntimeException('Unsupported MCP method: ' . $method);
                    }

                    if ($requestId !== null) {
                        dcpMcpWrite(['jsonrpc'=>'2.0','id'=>$requestId,'result'=>$result]);
                    }
                } catch (Throwable $error) {
                    if ($requestId !== null) {
                        dcpMcpWrite(['jsonrpc'=>'2.0','id'=>$requestId,'error'=>['code'=>-32000,'message'=>$error->getMessage()]]);
                    }
                }
            }
        }

        if (feof(STDIN)) {
            if (trim($inputBuffer) !== '') {
                $request = json_decode(trim($inputBuffer), true);
                if (is_array($request) && isset($request['id'])) {
                    dcpMcpWrite(['jsonrpc'=>'2.0','id'=>$request['id'],'error'=>['code'=>-32700,'message'=>'Incomplete JSON-RPC input before EOF.']]);
                }
            }
            break;
        }
    }

    $now = microtime(true);
    if ($pendingPing !== null && ($now - $pendingPingAt) > $pingTimeout) {
        // Do not kill an otherwise healthy STDIO host just because a client does not
        // answer optional keepalive pings; retry on the next interval.
        $pendingPing = null;
        $pendingPingAt = 0.0;
    }
    if ($pendingPing === null && ($now - $lastPingAt) >= $pingInterval) {
        $pendingPing = 'dcp-ping-' . (++$pingCounter);
        $pendingPingAt = $now;
        $lastPingAt = $now;
        dcpMcpWrite(['jsonrpc'=>'2.0','id'=>$pendingPing,'method'=>'ping']);
    }
}

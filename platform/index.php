<?php
declare(strict_types=1);

// Apache/shared-hosting entry point. Desktop builds continue to use public/router.php.
$scriptName = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = rtrim(str_replace('\\\\', '/', dirname($scriptName)), '/');
if ($basePath === '.' || $basePath === '/') $basePath = '';
$_SERVER['DCP_BASE_PATH'] = $basePath;
putenv('DCP_MODE=web-preview');
$_SERVER['DCP_MODE'] = 'web-preview';
$webPreviewDataRoot = getenv('DCP_WEB_PREVIEW_DATA_DIR') ?: (dirname(__DIR__) . '/DCP-UserData/WebPreview');
putenv('DCP_USER_DATA_DIR=' . $webPreviewDataRoot);
$_SERVER['DCP_USER_DATA_DIR'] = $webPreviewDataRoot;

require __DIR__ . '/bootstrap/app.php';

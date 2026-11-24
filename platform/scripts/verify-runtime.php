<?php
declare(strict_types=1);

$required = ['curl', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sodium', 'fileinfo', 'phar'];
if (PHP_OS_FAMILY !== 'Windows') $required[] = 'posix';
$missing = array_values(array_filter($required, static fn(string $extension): bool => !extension_loaded($extension)));

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "DCP requires PHP 8.2 or newer.\n");
    exit(2);
}
if (!class_exists(PharData::class)) $missing[] = 'PharData';
if (PHP_OS_FAMILY !== 'Windows' && !function_exists('posix_kill')) $missing[] = 'posix_kill';
if ($missing !== []) {
    fwrite(STDERR, "Missing PHP extensions: " . implode(', ', $missing) . "\n");
    exit(3);
}

echo "PHP " . PHP_VERSION . " and all required extensions are available.\n";

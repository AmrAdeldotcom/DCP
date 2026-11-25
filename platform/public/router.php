<?php
declare(strict_types=1);

// The desktop server may serve only regular static assets beneath public/assets.
// Never let a decoded traversal path fall through to PHP's built-in static handler.
$path = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
if (str_starts_with($path, '/assets/')) {
    $assetRoot = realpath(__DIR__ . '/assets');
    $candidate = realpath(__DIR__ . $path);
    $extension = strtolower(pathinfo($candidate ?: '', PATHINFO_EXTENSION));
    $allowed = ['css', 'ico', 'js', 'png', 'svg', 'ttf', 'txt'];
    if ($assetRoot !== false && $candidate !== false && is_file($candidate)
        && str_starts_with($candidate, $assetRoot . DIRECTORY_SEPARATOR)
        && in_array($extension, $allowed, true)) {
        return false;
    }
}

require dirname(__DIR__) . '/bootstrap/app.php';

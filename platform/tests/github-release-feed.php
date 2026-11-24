<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/lib/GitHubReleaseFeed.php';

function feedCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo "PASS: {$message}\n";
}

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <entry><title>DCP v1.0.0-alpha.10</title><link rel="alternate" href="https://github.com/AmrAdeldotcom/DCP/releases/tag/v1.0.0-alpha.10"/></entry>
  <entry><title>DCP v1.0.0-alpha.9</title><link href="https://github.com/AmrAdeldotcom/DCP/releases/tag/v1.0.0-alpha.9" rel="alternate"/></entry>
  <entry><title>External spoof</title><link rel="alternate" href="https://github.com/attacker/DCP/releases/tag/v9.9.9"/></entry>
  <entry><title>Invalid tag</title><link rel="alternate" href="https://github.com/AmrAdeldotcom/DCP/releases/tag/latest"/></entry>
  <entry><title>Duplicate</title><link rel="alternate" href="https://github.com/AmrAdeldotcom/DCP/releases/tag/v1.0.0-alpha.10"/></entry>
</feed>
XML;

$tags = DcpGitHubReleaseFeed::tags($xml, 'AmrAdeldotcom/DCP');
feedCheck($tags === ['v1.0.0-alpha.10', 'v1.0.0-alpha.9'], 'Atom feed accepts only unique semantic release tags from the exact repository');
feedCheck(DcpGitHubReleaseFeed::tags('<feed><entry><link rel="alternate" href="https://github.com/attacker/DCP/releases/tag/v1.0.0"/></entry></feed>', 'AmrAdeldotcom/DCP') === [], 'Atom feed rejects a different repository origin');
feedCheck(DcpGitHubReleaseFeed::assetUrl('AmrAdeldotcom/DCP', 'v1.0.0-alpha.10', 'DCP-Linux-update-v3.json') === 'https://github.com/AmrAdeldotcom/DCP/releases/download/v1.0.0-alpha.10/DCP-Linux-update-v3.json', 'release asset URLs are constructed only from the fixed official repository and validated tag');
$oversizedRejected = false;
try {
    DcpGitHubReleaseFeed::tags(str_repeat('x', 4 * 1024 * 1024 + 1), 'AmrAdeldotcom/DCP');
} catch (RuntimeException) {
    $oversizedRejected = true;
}
feedCheck($oversizedRejected, 'oversized Atom feed is rejected');

echo "ALL GITHUB RELEASE FEED TESTS PASSED\n";

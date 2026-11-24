<?php
declare(strict_types=1);

/**
 * Reads public GitHub release tags from the repository Atom feed.
 *
 * The unauthenticated REST releases endpoint is rate-limited. The public
 * releases.atom feed avoids that API quota; signed manifests and package
 * hashes remain the authority for update authenticity.
 */
final class DcpGitHubReleaseFeed
{
    public static function tags(string $xml, string $repository): array
    {
        if ($xml === '' || strlen($xml) > 4 * 1024 * 1024) {
            throw new RuntimeException('GitHub release Atom feed is empty or exceeds its size limit.');
        }
        if (!preg_match_all('/<entry\\b[^>]*>(.*?)<\\/entry>/is', $xml, $entries)) {
            return [];
        }

        $prefix = 'https://github.com/' . $repository . '/releases/tag/';
        $tags = [];
        foreach ($entries[1] as $entry) {
            if (!preg_match_all('/<link\\b([^>]*)\\/?\\s*>/i', $entry, $links)) {
                continue;
            }
            foreach ($links[1] as $attributes) {
                if (!preg_match('/\\brel="alternate"/i', $attributes) ||
                    !preg_match('/\\bhref="([^"]+)"/i', $attributes, $hrefMatch)) {
                    continue;
                }
                $href = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                if (!str_starts_with($href, $prefix)) {
                    continue;
                }
                $tag = rawurldecode(substr($href, strlen($prefix)));
                if (preg_match('/^v\\d+\\.\\d+\\.\\d+(?:-[0-9A-Za-z.-]+)?$/', $tag) &&
                    !in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
                break;
            }
        }
        return $tags;
    }

    public static function assetUrl(string $repository, string $tag, string $name): string
    {
        return 'https://github.com/' . $repository . '/releases/download/' .
            rawurlencode($tag) . '/' . rawurlencode($name);
    }
}

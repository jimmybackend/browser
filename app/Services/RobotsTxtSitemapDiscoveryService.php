<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;

final class RobotsTxtSitemapDiscoveryService
{
    public function normalizeRobotsTxtUrl(string $inputUrl): ?string
    {
        $parts = parse_url(trim($inputUrl));
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if (strtolower($path) === '/robots.txt') {
            return $scheme . '://' . $host . '/robots.txt';
        }

        return $scheme . '://' . $host . '/robots.txt';
    }

    /** @return array{sitemaps:list<string>,errors:list<string>} */
    public function extractSitemaps(string $robotsTxtBody): array
    {
        $sitemaps = [];
        $errors = [];

        foreach (preg_split('/\r\n|\r|\n/', $robotsTxtBody) ?: [] as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (!preg_match('/^sitemap\s*:\s*(.+)$/i', $trimmed, $matches)) {
                continue;
            }

            $candidate = trim((string) ($matches[1] ?? ''));
            if ($candidate === '') {
                continue;
            }

            $sitemaps[] = $candidate;
        }

        return ['sitemaps' => array_values(array_unique($sitemaps)), 'errors' => $errors];
    }

    /** @return array{status:int,body:string} */
    public function fetchRobotsTxt(string $robotsUrl): array
    {
        $ch = curl_init($robotsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'BrowserBot/0.1 robots-loader',
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
        ]);

        $response = curl_exec($ch);
        if (!is_string($response)) {
            throw new RuntimeException('Error CURL: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (strlen($response) > 1024 * 1024) {
            throw new RuntimeException('robots.txt excede tamaño máximo (1MB).');
        }

        return ['status' => $status, 'body' => $response];
    }
}

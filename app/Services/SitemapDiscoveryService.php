<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;
use Throwable;

final class SitemapDiscoveryService
{
    public function fetchSitemapXml(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'BrowserBot/0.1 sitemap-loader',
            CURLOPT_HTTPHEADER => ['Accept: application/xml,text/xml'],
        ]);

        $response = curl_exec($ch);
        if (!is_string($response)) {
            throw new RuntimeException('Error CURL: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 400) {
            throw new RuntimeException('HTTP status inválido: ' . $status);
        }

        if (strlen($response) > 5 * 1024 * 1024) {
            throw new RuntimeException('Sitemap excede tamaño máximo (5MB).');
        }

        return $response;
    }

    /** @return array{type:string,urls:list<string>} */
    public function parseSitemapUrls(string $xmlBody): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlBody, 'SimpleXMLElement', $this->sitemapLibxmlOptions());
            if ($xml === false) {
                throw new RuntimeException('XML inválido.');
            }

            $root = strtolower($xml->getName());
            if ($root === 'urlset') {
                $urls = [];
                foreach ($xml->url as $urlNode) {
                    $loc = trim((string) ($urlNode->loc ?? ''));
                    if ($loc !== '') {
                        $urls[] = $loc;
                    }
                }

                return ['type' => 'urlset', 'urls' => array_values(array_unique($urls))];
            }

            if ($root === 'sitemapindex') {
                $urls = [];
                foreach ($xml->sitemap as $sitemapNode) {
                    $loc = trim((string) ($sitemapNode->loc ?? ''));
                    if ($loc !== '') {
                        $urls[] = $loc;
                    }
                }

                return ['type' => 'sitemapindex (direct loc only)', 'urls' => array_values(array_unique($urls))];
            }

            throw new RuntimeException('Root XML no soportado. Use urlset o sitemapindex.');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function sitemapLibxmlOptions(): int
    {
        return LIBXML_NONET | LIBXML_NOCDATA;
    }

    /**
     * @param callable(string):?string $normalizeUrl
     * @param callable(string,int,int):int $createJob
     * @param callable(string):void|null $logger
     */
    public function createJobsFromParsedSitemap(
        array $parsed,
        int $maxDepth,
        int $maxPages,
        int $limit,
        callable $normalizeUrl,
        callable $createJob,
        ?callable $logger = null
    ): array {
        $created = 0;
        $skipped = 0;

        foreach (($parsed['urls'] ?? []) as $candidate) {
            if ($created >= $limit) {
                break;
            }

            $normalized = $normalizeUrl((string) $candidate);
            if (!is_string($normalized) || $normalized === '') {
                $skipped++;
                if ($logger !== null) {
                    $logger('[SKIP] URL inválida.');
                }
                continue;
            }

            try {
                $createJob($normalized, $maxDepth, $maxPages);
                $created++;
            } catch (Throwable $exception) {
                $skipped++;
                if ($logger !== null) {
                    $logger('[SKIP] URL inválida.');
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}

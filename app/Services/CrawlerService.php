<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Models\CrawlJob;
use Browser\Models\CrawlUrl;
use Browser\Models\IndexedPage;
use DOMDocument;
use DOMXPath;
use Throwable;

final class CrawlerService
{
    private const MAX_BYTES = 2097152;
    private const TIMEOUT_SECONDS = 8;

    public function __construct(private readonly RobotsTxtService $robotsTxt)
    {
    }

    public function runJob(array $job): array
    {
        $jobId = (int) $job['id'];
        $maxDepth = (int) $job['max_depth'];
        $maxPages = (int) ($job['max_pages'] ?? 25);
        $seed = $this->normalizeUrl((string) $job['seed_url']);

        if ($seed === null) {
            throw new \RuntimeException('Seed URL inválida.');
        }

        $seedParts = parse_url($seed);
        $seedDomain = strtolower((string) $seedParts['host']);

        CrawlUrl::enqueue($jobId, $seed, hash('sha256', $seed), $seedDomain, 0, null);

        $indexed = 0;

        while ($indexed < $maxPages) {
            $next = CrawlUrl::nextQueued($jobId);
            if ($next === null) {
                break;
            }

            $depth = (int) $next['depth'];
            if ($depth > $maxDepth) {
                CrawlUrl::markDone((int) $next['id'], 'skipped', null, 'max_depth exceeded');
                continue;
            }

            $url = (string) $next['url'];
            CrawlUrl::markRunning((int) $next['id']);

            if (!$this->robotsTxt->isAllowed($url, 'BrowserBot')) {
                CrawlUrl::markDone((int) $next['id'], 'skipped', null, 'robots.txt disallow');
                continue;
            }

            try {
                $response = $this->fetchHtml($url);
                if ($response['http_status'] < 200 || $response['http_status'] >= 400) {
                    CrawlUrl::markDone((int) $next['id'], 'failed', $response['http_status'], 'http error');
                    continue;
                }

                $page = $this->parseHtml($url, $response['body']);
                IndexedPage::upsert($page);
                CrawlUrl::markDone((int) $next['id'], 'indexed', $response['http_status'], null);
                $indexed++;

                foreach ($page['links'] as $link) {
                    $normalized = $this->normalizeUrl($link, $url);
                    if ($normalized === null) {
                        continue;
                    }

                    $parts = parse_url($normalized);
                    $domain = strtolower((string) ($parts['host'] ?? ''));
                    if ($domain !== $seedDomain) {
                        continue;
                    }

                    CrawlUrl::enqueue($jobId, $normalized, hash('sha256', $normalized), $domain, $depth + 1, $url);
                }

                sleep(1);
            } catch (Throwable $exception) {
                CrawlUrl::markDone((int) $next['id'], 'failed', null, mb_substr($exception->getMessage(), 0, 500));
            }
        }

        return [
            'pages_found' => CrawlUrl::countByJob($jobId),
            'pages_indexed' => $indexed,
        ];
    }

    private function fetchHtml(string $url): array
    {
        $this->assertSafeTarget($url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'BrowserBot/0.1 (+' . (string) ($_ENV['APP_URL'] ?? 'http://localhost') . ')',
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_COOKIEJAR => '',
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        if (!is_string($response)) {
            throw new \RuntimeException('Error CURL: ' . curl_error($ch));
        }

        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException('Body exceeds max size.');
        }

        if (!str_contains(strtolower($contentType), 'text/html') && stripos($headers, 'content-type: text/html') === false) {
            throw new \RuntimeException('Non HTML content-type.');
        }

        return ['http_status' => $httpStatus, 'body' => $body];
    }

    private function parseHtml(string $url, string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode?->textContent !== null ? trim($titleNode->textContent) : null;

        $descNode = $xpath->query('//meta[@name="description"]')->item(0);
        $descriptionRaw = $descNode?->attributes?->getNamedItem('content')?->textContent;
        $description = is_string($descriptionRaw) ? trim($descriptionRaw) : null;
        if ($description === '') {
            $description = null;
        }

        $langRaw = $dom->documentElement?->getAttribute('lang');
        $lang = is_string($langRaw) ? trim($langRaw) : null;
        if ($lang === '') {
            $lang = null;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
        $text = mb_substr($text, 0, 65000);

        $links = [];
        foreach ($xpath->query('//a[@href]') as $anchor) {
            $href = trim((string) $anchor->attributes?->getNamedItem('href')?->textContent);
            if ($href !== '') {
                $links[] = $href;
            }
        }

        $parts = parse_url($url);

        return [
            'url_hash' => hash('sha256', $url),
            'url' => $url,
            'domain' => strtolower((string) ($parts['host'] ?? '')),
            'title' => $title !== '' ? mb_substr($title, 0, 255) : null,
            'description' => $description !== null ? mb_substr($description, 0, 2000) : null,
            'content_text' => $text,
            'language' => $lang !== null ? mb_substr($lang, 0, 10) : null,
            'links' => array_values(array_unique($links)),
        ];
    }

    private function normalizeUrl(string $url, ?string $base = null): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return null;
        }

        if (preg_match('/^(javascript|data|file|ftp):/i', $url) === 1) {
            return null;
        }

        if ($base !== null && !preg_match('/^https?:\/\//i', $url)) {
            $baseParts = parse_url($base);
            if (
                $baseParts === false
                || empty($baseParts['scheme'])
                || empty($baseParts['host'])
            ) {
                return null;
            }

            $scheme = strtolower((string) $baseParts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $host = strtolower((string) $baseParts['host']);
            $basePath = (string) ($baseParts['path'] ?? '/');

            if (str_starts_with($url, '//')) {
                $url = $scheme . ':' . $url;
            } elseif (str_starts_with($url, '/')) {
                $url = $scheme . '://' . $host . $url;
            } elseif (str_starts_with($url, '?')) {
                $url = $scheme . '://' . $host . $basePath . $url;
            } else {
                $baseDir = str_ends_with($basePath, '/')
                    ? $basePath
                    : (string) preg_replace('/\/[^\/]*$/', '/', $basePath);
                if ($baseDir === '') {
                    $baseDir = '/';
                }

                $url = $scheme . '://' . $host . $baseDir . ltrim($url, '/');
            }
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        $path = $this->encodePathSegments($path);
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        if ($query !== '') {
            $query = '?' . str_replace(' ', '%20', ltrim($query, '?'));
        }

        return $scheme . '://' . $host . $path . $query;
    }

    private function encodePathSegments(string $path): string
    {
        $segments = explode('/', $path);
        $encoded = array_map(
            static function (string $segment): string {
                return preg_replace_callback(
                    '/(?:%[0-9A-Fa-f]{2})|[^%]+/',
                    static function (array $matches): string {
                        $part = $matches[0];
                        if (preg_match('/^%[0-9A-Fa-f]{2}$/', $part) === 1) {
                            return strtoupper($part);
                        }

                        return rawurlencode($part);
                    },
                    $segment
                ) ?? '';
            },
            $segments
        );

        return implode('/', $encoded);
    }

    private function assertSafeTarget(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new \RuntimeException('Blocked local host.');
        }

        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('Blocked private/reserved IP.');
            }
        }
    }
}

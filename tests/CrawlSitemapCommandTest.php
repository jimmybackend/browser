<?php

declare(strict_types=1);

use Browser\Services\SitemapDiscoveryService;
use PHPUnit\Framework\TestCase;

final class CrawlSitemapCommandTest extends TestCase
{
    private SitemapDiscoveryService $service;

    protected function setUp(): void
    {
        $this->service = new SitemapDiscoveryService();
    }

    public function testUrlsetCreatesOneJobPerUrl(): void
    {
        $parsed = $this->service->parseSitemapUrls($this->fixture('urlset_valid.xml'));
        $jobs = [];

        $result = $this->service->createJobsFromParsedSitemap($parsed, 1, 10, 50, fn (string $u): ?string => $u, function (string $url, int $depth, int $pages) use (&$jobs): int {
            $jobs[] = [$url, $depth, $pages];
            return count($jobs);
        });

        $this->assertSame('urlset', $parsed['type']);
        $this->assertCount(3, $jobs);
        $this->assertSame(['https://example.com/', 1, 10], $jobs[0]);
        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['duplicates']);
    }

    public function testLimitRestrictsCreatedJobs(): void
    {
        $parsed = $this->service->parseSitemapUrls($this->fixture('urlset_over_limit.xml'));
        $jobs = [];

        $result = $this->service->createJobsFromParsedSitemap($parsed, 1, 10, 2, fn (string $u): ?string => $u, function (string $url, int $depth, int $pages) use (&$jobs): int {
            $jobs[] = [$url, $depth, $pages];
            return count($jobs);
        });

        $this->assertCount(2, $jobs);
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testInvalidXmlFailsInControlledWay(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XML inválido.');

        $this->service->parseSitemapUrls($this->fixture('urlset_invalid.xml'));
    }

    public function testDisallowedUrlsAreSkippedWithoutFatalError(): void
    {
        $parsed = $this->service->parseSitemapUrls($this->fixture('urlset_invalid_urls.xml'));
        $jobs = [];

        $result = $this->service->createJobsFromParsedSitemap(
            $parsed,
            3,
            7,
            50,
            fn (string $u): ?string => preg_match('#^(https?://)(localhost|127\.0\.0\.1)#i', $u) || str_starts_with(strtolower($u), 'ftp://') ? null : $u,
            function (string $url, int $depth, int $pages) use (&$jobs): int {
                $jobs[] = [$url, $depth, $pages];
                return count($jobs);
            }
        );

        $this->assertCount(1, $jobs);
        $this->assertSame(['https://example.com/ok', 3, 7], $jobs[0]);
        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['skipped']);
    }


    public function testDuplicateUrlsAreCountedSeparately(): void
    {
        $parsed = $this->service->parseSitemapUrls($this->fixture('urlset_valid.xml'));

        $result = $this->service->createJobsFromParsedSitemap(
            $parsed,
            1,
            10,
            50,
            fn (string $u): ?string => $u,
            function (string $url, int $depth, int $pages): int {
                if ($url === 'https://example.com/about') {
                    throw new RuntimeException('[DUPLICATE] ' . $url);
                }

                return 1;
            }
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(0, $result['invalid']);
    }

    public function testSitemapindexCurrentBehaviorUsesDirectLocOnly(): void
    {
        $parsed = $this->service->parseSitemapUrls($this->fixture('sitemapindex_basic.xml'));

        $this->assertSame('sitemapindex (direct loc only)', $parsed['type']);
        $this->assertSame([
            'https://example.com/sitemap-pages.xml',
            'https://example.com/sitemap-blog.xml',
        ], $parsed['urls']);
    }

    public function testSecureXmlOptionsDoNotIncludeNoent(): void
    {
        $options = $this->service->sitemapLibxmlOptions();

        $this->assertSame(0, $options & LIBXML_NOENT);
        $this->assertNotSame(0, $options & LIBXML_NONET);
    }

    private function fixture(string $name): string
    {
        $path = __DIR__ . '/Fixtures/sitemaps/' . $name;
        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }
}

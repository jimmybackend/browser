<?php

declare(strict_types=1);

use Browser\Services\RobotsTxtSitemapDiscoveryService;
use Browser\Services\SitemapDiscoveryService;
use PHPUnit\Framework\TestCase;

final class RobotsTxtSitemapDiscoveryServiceTest extends TestCase
{
    public function testExtractsSingleSitemap(): void
    {
        $service = new RobotsTxtSitemapDiscoveryService();
        $result = $service->extractSitemaps("User-agent: *\nSitemap: https://example.com/sitemap.xml\n");

        $this->assertSame(['https://example.com/sitemap.xml'], $result['sitemaps']);
    }

    public function testExtractsMultipleSitemapsAndCaseInsensitiveWithSpaces(): void
    {
        $service = new RobotsTxtSitemapDiscoveryService();
        $result = $service->extractSitemaps(" sitemap : https://example.com/a.xml\nSitemap: https://example.com/b.xml\n");

        $this->assertSame(['https://example.com/a.xml', 'https://example.com/b.xml'], $result['sitemaps']);
    }

    public function testNoSitemapLinesReturnsEmpty(): void
    {
        $service = new RobotsTxtSitemapDiscoveryService();
        $result = $service->extractSitemaps("User-agent: *\nDisallow: /admin\n");

        $this->assertSame([], $result['sitemaps']);
    }

    public function testNormalizeRobotsUrlSupportsBaseOrRobotsPath(): void
    {
        $service = new RobotsTxtSitemapDiscoveryService();

        $this->assertSame('https://example.com/robots.txt', $service->normalizeRobotsTxtUrl('https://example.com'));
        $this->assertSame('https://example.com/robots.txt', $service->normalizeRobotsTxtUrl('https://example.com/'));
        $this->assertSame('https://example.com/robots.txt', $service->normalizeRobotsTxtUrl('https://example.com/robots.txt'));
        $this->assertNull($service->normalizeRobotsTxtUrl('ftp://example.com/robots.txt'));
    }

    public function testLimitAcrossMultipleSitemapsCanBeAppliedByCaller(): void
    {
        $sitemapService = new SitemapDiscoveryService();
        $jobs = [];

        $parsedA = ['type' => 'urlset', 'urls' => ['https://example.com/1', 'https://example.com/2']];
        $parsedB = ['type' => 'urlset', 'urls' => ['https://example.com/3', 'https://example.com/4']];

        $created = 0;
        foreach ([$parsedA, $parsedB] as $parsed) {
            $remaining = 3 - $created;
            if ($remaining <= 0) {
                break;
            }

            $result = $sitemapService->createJobsFromParsedSitemap(
                $parsed,
                1,
                10,
                $remaining,
                fn (string $url): ?string => $url,
                function (string $url, int $depth, int $pages) use (&$jobs): int {
                    $jobs[] = [$url, $depth, $pages];
                    return count($jobs);
                }
            );
            $created += (int) $result['created'];
        }

        $this->assertCount(3, $jobs);
        $this->assertSame(['https://example.com/3', 1, 10], $jobs[2]);
    }
}

<?php

declare(strict_types=1);

use Browser\Console\Kernel;
use PHPUnit\Framework\TestCase;

final class CrawlSitemapCommandTest extends TestCase
{
    public function testUrlsetCreatesOneJobPerUrl(): void
    {
        $kernel = new TestableKernel($this->fixture('urlset_valid.xml'));

        $code = $kernel->handle(['bin/browser', 'crawl:sitemap', '--url=https://example.com/sitemap.xml', '--max-depth=1', '--max-pages=10', '--limit=50']);

        $this->assertSame(0, $code);
        $this->assertCount(3, $kernel->createdJobs);
        $this->assertSame(['https://example.com/', 1, 10], $kernel->createdJobs[0]);
    }

    public function testLimitRestrictsCreatedJobs(): void
    {
        $kernel = new TestableKernel($this->fixture('urlset_over_limit.xml'));

        $code = $kernel->handle(['bin/browser', 'crawl:sitemap', '--url=https://example.com/sitemap.xml', '--max-depth=1', '--max-pages=10', '--limit=2']);

        $this->assertSame(0, $code);
        $this->assertCount(2, $kernel->createdJobs);
    }

    public function testInvalidXmlFailsInControlledWay(): void
    {
        $kernel = new TestableKernel($this->fixture('urlset_invalid.xml'));

        $code = $kernel->handle(['bin/browser', 'crawl:sitemap', '--url=https://example.com/sitemap.xml']);

        $this->assertSame(1, $code);
        $this->assertCount(0, $kernel->createdJobs);
    }

    public function testDisallowedUrlsAreSkippedWithoutFatalError(): void
    {
        $kernel = new TestableKernel($this->fixture('urlset_invalid_urls.xml'));

        $code = $kernel->handle(['bin/browser', 'crawl:sitemap', '--url=https://example.com/sitemap.xml', '--max-depth=3', '--max-pages=7', '--limit=50']);

        $this->assertSame(0, $code);
        $this->assertCount(1, $kernel->createdJobs);
        $this->assertSame(['https://example.com/ok', 3, 7], $kernel->createdJobs[0]);
    }

    public function testSitemapindexCurrentBehaviorUsesDirectLocOnly(): void
    {
        $kernel = new TestableKernel($this->fixture('sitemapindex_basic.xml'));

        $code = $kernel->handle(['bin/browser', 'crawl:sitemap', '--url=https://example.com/sitemap.xml']);

        $this->assertSame(0, $code);
        $this->assertCount(2, $kernel->createdJobs);
        $this->assertSame('https://example.com/sitemap-pages.xml', $kernel->createdJobs[0][0]);
    }

    public function testSecureXmlOptionsDoNotIncludeNoent(): void
    {
        $kernel = new TestableKernel($this->fixture('urlset_valid.xml'));

        $options = $kernel->readSitemapLibxmlOptions();

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

final class TestableKernel extends Kernel
{
    /** @var list<array{0:string,1:int,2:int}> */
    public array $createdJobs = [];

    public function __construct(private readonly string $xmlFixture)
    {
    }

    protected function fetchSitemapXml(string $url): string
    {
        return $this->xmlFixture;
    }

    protected function createCrawlJob(string $url, int $maxDepth, int $maxPages): int
    {
        $this->createdJobs[] = [$url, $maxDepth, $maxPages];

        return count($this->createdJobs);
    }

    public function readSitemapLibxmlOptions(): int
    {
        return $this->sitemapLibxmlOptions();
    }
}

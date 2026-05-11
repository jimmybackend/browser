<?php

declare(strict_types=1);

use Browser\Services\CrawlReportShowService;
use PHPUnit\Framework\TestCase;

final class CrawlReportShowServiceTest extends TestCase
{
    public function testShowByFileReturnsSnapshot(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-show-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        $filename = 'crawl-report-20260511-120000.json';
        file_put_contents($dir . '/' . $filename, "{\n\"jobs\":{\"queued\":1}\n}\n");

        $service = new CrawlReportShowService($dir);
        $snapshot = $service->showByFilename($filename);

        $this->assertSame($filename, $snapshot['filename']);
        $this->assertSame(1, $snapshot['report']['jobs']['queued']);
    }

    public function testShowLatestUsesMostRecentAndDomainFilter(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-show-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-example.com-20260511-120000.json', '{"filters":{"domain":"example.com"}}');
        sleep(1);
        file_put_contents($dir . '/crawl-report-example.com-20260511-120001.json', '{"filters":{"domain":"example.com"}}');
        sleep(1);
        file_put_contents($dir . '/crawl-report-other.com-20260511-120002.json', '{"filters":{"domain":"other.com"}}');

        $service = new CrawlReportShowService($dir);
        $latestGlobal = $service->showLatest(null);
        $latestDomain = $service->showLatest('../../Example.COM');

        $this->assertSame('crawl-report-other.com-20260511-120002.json', $latestGlobal['filename']);
        $this->assertSame('crawl-report-example.com-20260511-120001.json', $latestDomain['filename']);
    }

    public function testRejectsUnsafeOrInvalidFilenamesAndInvalidJson(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-show-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-20260511-120000.json', '{invalid json');

        $service = new CrawlReportShowService($dir);

        $this->expectException(RuntimeException::class);
        $service->showByFilename('../crawl-report-20260511-120000.json');
    }

    public function testFailsOnInvalidJsonAndMissingFile(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-show-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/crawl-report-20260511-120000.json', '{oops');

        $service = new CrawlReportShowService($dir);

        try {
            $service->showByFilename('crawl-report-20260511-120000.json');
            $this->fail('Expected invalid json failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('JSON inválido', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $service->showByFilename('crawl-report-20260511-130000.json');
    }

    public function testKernelRegistersShowCommandAsReadOnly(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:report-show', $kernel);

        $this->assertStringNotContainsString('crawlRun(', $kernel);
        $this->assertStringNotContainsString('CrawlJob::create(', $kernel);
    }
}

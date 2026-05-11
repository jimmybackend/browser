<?php

declare(strict_types=1);

use Browser\Services\CrawlReportDiffService;
use Browser\Services\CrawlReportShowService;
use Browser\Services\CrawlReportHistoryService;
use PHPUnit\Framework\TestCase;

final class CrawlReportDiffServiceTest extends TestCase
{
    public function testDiffCalculatesNumericAndListChanges(): void
    {
        $service = new CrawlReportDiffService();
        $from = [
            '_meta' => ['filename' => 'crawl-report-20260511-100000.json'],
            'jobs' => ['queued' => 4],
            'urls' => ['failed' => 10],
            'indexed_pages' => ['total' => 120],
            'paused_domains' => [['domain' => 'a.com']],
            'domain_advice' => [['domain' => 'a.com', 'recommendation' => 'pause']],
            'recent_errors' => [['domain' => 'a.com', 'http_status' => 500, 'error_message' => 'x']],
        ];
        $to = [
            '_meta' => ['filename' => 'crawl-report-20260511-120000.json'],
            'jobs' => ['queued' => 7],
            'urls' => ['failed' => 8],
            'indexed_pages' => ['total' => 125],
            'paused_domains' => [['domain' => 'b.com']],
            'domain_advice' => [['domain' => 'b.com', 'recommendation' => 'watch']],
            'recent_errors' => [['domain' => 'b.com', 'http_status' => 429, 'error_message' => 'y']],
        ];

        $diff = $service->diff($from, $to);

        $this->assertSame(3, $diff['jobs']['queued']['delta']);
        $this->assertSame(-2, $diff['urls']['failed']['delta']);
        $this->assertSame(5, $diff['indexed_pages_total']['delta']);
        $this->assertSame(['b.com'], $diff['paused_domains']['added']);
        $this->assertSame(['a.com'], $diff['paused_domains']['removed']);
    }

    public function testShowServiceRejectsUnsafeNamesAndInvalidJsonAndMissingFile(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-diff-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/crawl-report-20260511-100000.json', '{bad');

        $show = new CrawlReportShowService($dir);

        $this->expectException(RuntimeException::class);
        $show->showByFilename('../x.json');
    }

    public function testHistoryLatestAndDomainSanitizedSelection(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-diff-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-example.com-20260511-100000.json', '{}');
        sleep(1);
        file_put_contents($dir . '/crawl-report-example.com-20260511-120000.json', '{}');
        sleep(1);
        file_put_contents($dir . '/crawl-report-20260511-130000.json', '{}');

        $history = new CrawlReportHistoryService($dir);
        $domainItems = $history->list(2, '../../example.com');

        $this->assertCount(2, $domainItems);
        $this->assertSame('crawl-report-example.com-20260511-120000.json', $domainItems[0]['filename']);
    }

    public function testKernelRegistersDiffCommandAsReadOnly(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:report-diff', $kernel);

        $body = $this->extractMethodBody($kernel, 'crawlReportDiff');
        $this->assertStringContainsString('storage/crawler/reports', $body);
        $this->assertStringNotContainsString('crawlRun(', $body);
        $this->assertStringNotContainsString('CrawlJob::create(', $body);
        $this->assertStringNotContainsString('domain-policy.json', $body);
    }

    private function extractMethodBody(string $source, string $methodName): string
    {
        $signature = 'private function ' . $methodName . '(';
        $start = strpos($source, $signature);
        $this->assertNotFalse($start);
        $braceStart = strpos($source, '{', $start);
        $this->assertNotFalse($braceStart);

        $depth = 0;
        for ($i = $braceStart; $i < strlen($source); $i++) {
            if ($source[$i] === '{') { $depth++; }
            if ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $braceStart + 1, $i - $braceStart - 1);
                }
            }
        }

        self::fail('No se pudo extraer método.');
    }
}

<?php

declare(strict_types=1);

use Browser\Services\CrawlReportHistoryService;
use Browser\Services\CrawlReportStorageService;
use PHPUnit\Framework\TestCase;

final class CrawlReportHistoryServiceTest extends TestCase
{
    public function testListSnapshotsSortedAndLimited(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-history-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-example.com-20260511-100000.json', '{}');
        sleep(1);
        file_put_contents($dir . '/crawl-report-other.com-20260511-100001.json', '{}');

        $service = new CrawlReportHistoryService($dir);
        $items = $service->list(1, null);

        $this->assertCount(1, $items);
        $this->assertSame('crawl-report-other.com-20260511-100001.json', $items[0]['filename']);
    }

    public function testDomainFilterUsesSafeSanitization(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-history-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-example.com-evil-20260511-100000.json', '{}');
        file_put_contents($dir . '/crawl-report-example.net-20260511-100000.json', '{}');

        $service = new CrawlReportHistoryService($dir);
        $items = $service->list(10, '../../Example.COM/evil');

        $this->assertCount(1, $items);
        $this->assertSame('example.com-evil', $items[0]['domain']);
        $this->assertSame('example.com-evil', CrawlReportStorageService::sanitizeDomainForFilename('../../Example.COM/evil'));
    }

    public function testIgnoresUnexpectedFilesAndMissingDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-history-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/.hidden.json', '{}');
        file_put_contents($dir . '/crawl-report-note.txt', '{}');
        file_put_contents($dir . '/crawl-report-example.com-20260511-100000.json.tmp', '{}');
        file_put_contents($dir . '/something-else.json', '{}');
        file_put_contents($dir . '/crawl-report-20260511-100000.json', '{}');

        $service = new CrawlReportHistoryService($dir);
        $items = $service->list(10, null);
        $this->assertCount(1, $items);

        $missing = new CrawlReportHistoryService($dir . '-missing');
        $this->assertSame([], $missing->list(10, null));
    }

    public function testKernelRegistersHistoryCommandAsReadOnly(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:report-history', $kernel);

        $body = $this->extractMethodBody($kernel, 'crawlReportHistory');
        $this->assertStringContainsString("storage/crawler/reports", $body);
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|ALTER|DROP)\b/i', $body);
        $this->assertStringNotContainsString('crawlRun(', $body);
        $this->assertStringNotContainsString('CrawlJob::create(', $body);
        $this->assertStringNotContainsString('pause(', $body);
    }


    public function testKernelRegistersPruneCommandAsSafe(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:report-prune', $kernel);

        $body = $this->extractMethodBody($kernel, 'crawlReportPrune');
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

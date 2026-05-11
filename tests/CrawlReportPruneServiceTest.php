<?php

declare(strict_types=1);

use Browser\Services\CrawlReportPruneService;
use PHPUnit\Framework\TestCase;

final class CrawlReportPruneServiceTest extends TestCase
{
    public function testPruneDaysDryRunDoesNotDelete(): void
    {
        $dir = $this->makeDir();
        $old = $dir . '/crawl-report-example.com-20240101-000000.json';
        file_put_contents($old, '{}');
        touch($old, time() - 40 * 86400);

        $service = new CrawlReportPruneService($dir);
        $listed = $service->listSafeFiles(null);
        $targets = $service->selectByDays($listed['files'], 30);

        $this->assertCount(1, $targets);
        $this->assertFileExists($old);
    }

    public function testKeepPreservesMostRecent(): void
    {
        $dir = $this->makeDir();
        for ($i = 0; $i < 3; $i++) {
            $f = $dir . '/crawl-report-20260511-10000' . $i . '.json';
            file_put_contents($f, '{}');
            touch($f, time() - (10 - $i));
        }
        $service = new CrawlReportPruneService($dir);
        $targets = $service->selectByKeep($service->listSafeFiles(null)['files'], 1);
        $this->assertCount(2, $targets);
    }

    public function testIgnoresUnsafeFilenamesAndDomainFilterIsSanitized(): void
    {
        $dir = $this->makeDir();
        file_put_contents($dir . '/crawl-report-example.com-evil-20260511-100000.json', '{}');
        file_put_contents($dir . '/crawl-report-note.txt', '{}');
        file_put_contents($dir . '/.hidden.json', '{}');
        file_put_contents($dir . '/crawl-report-20260511-100000.json.tmp', '{}');

        $service = new CrawlReportPruneService($dir);
        $items = $service->listSafeFiles('../../Example.COM/evil');
        $this->assertCount(1, $items['files']);
        $this->assertSame('crawl-report-example.com-evil-20260511-100000.json', $items['files'][0]['filename']);
    }

    private function makeDir(): string
    {
        $dir = sys_get_temp_dir() . '/crawler-prune-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        return $dir;
    }
}

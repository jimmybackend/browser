<?php

declare(strict_types=1);

use Browser\Services\CrawlReportDiffService;
use Browser\Services\CrawlReportHistoryService;
use Browser\Services\CrawlReportShowService;
use Browser\Services\CrawlReportSummaryService;
use PHPUnit\Framework\TestCase;

final class CrawlReportSummaryServiceTest extends TestCase
{
    public function testSummarizesSnapshotsAndHandlesInvalidAndIgnoredFiles(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-summary-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        file_put_contents($dir . '/crawl-report-example.com-20260511-100000.json', json_encode([
            'snapshot_at' => '2026-05-11T10:00:00Z',
            'jobs' => ['queued' => 1],
            'urls' => ['failed' => 5],
            'indexed_pages' => ['total' => 100],
            'paused_domains' => [['domain' => 'example.com']],
            'domain_advice' => [['domain' => 'example.com', 'recommendation' => 'watch']],
            'recent_errors' => [['domain' => 'example.com', 'http_status' => 500, 'error_message' => 'x']],
        ], JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . '/crawl-report-example.com-20260511-110000.json', '{bad');
        file_put_contents($dir . '/crawl-report-example.com-20260511-120000.json', json_encode([
            'snapshot_at' => '2026-05-11T12:00:00Z',
            'jobs' => ['queued' => 3],
            'urls' => ['failed' => 2],
            'indexed_pages' => ['total' => 107],
            'paused_domains' => [],
            'domain_advice' => [],
            'recent_errors' => [],
        ], JSON_UNESCAPED_SLASHES));

        file_put_contents($dir . '/crawl-report-example.com-20260511-130000.json.tmp', '{}');
        file_put_contents($dir . '/.hidden.json', '{}');
        file_put_contents($dir . '/note.txt', '{}');

        $service = new CrawlReportSummaryService(new CrawlReportHistoryService($dir), new CrawlReportShowService($dir), new CrawlReportDiffService());
        $summary = $service->summarize(10, '../../Example.COM');

        $this->assertSame(2, $summary['analyzed_count']);
        $this->assertSame(1, $summary['invalid_count']);
        $this->assertSame('example.com', $summary['domain']);
        $this->assertSame(107, $summary['indexed_pages']['latest_total']);
        $this->assertSame(7, $summary['indexed_pages']['delta']);
        $this->assertSame(2, $summary['jobs_by_status_delta']['queued']['delta']);
        $this->assertSame(-3, $summary['urls_by_status_delta']['failed']['delta']);
    }

    public function testSingleSnapshotAddsNoTrendWarning(): void
    {
        $dir = sys_get_temp_dir() . '/crawler-summary-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/crawl-report-20260511-120000.json', '{"snapshot_at":"2026-05-11T12:00:00Z","jobs":{},"urls":{},"indexed_pages":{"total":9},"paused_domains":[],"domain_advice":[],"recent_errors":[]}');

        $service = new CrawlReportSummaryService(new CrawlReportHistoryService($dir), new CrawlReportShowService($dir), new CrawlReportDiffService());
        $summary = $service->summarize(10, null);

        $this->assertSame(1, $summary['analyzed_count']);
        $this->assertSame([], $summary['jobs_by_status_delta']);
        $this->assertNotEmpty($summary['warnings']);
    }
}

<?php

declare(strict_types=1);

use Browser\Services\CrawlDomainStatusService;
use PHPUnit\Framework\TestCase;

final class CrawlDomainStatusServiceTest extends TestCase
{
    public function testServiceReturnsEmptySummaryWhenNoData(): void
    {
        $pdo = $this->buildPdo();
        $service = new CrawlDomainStatusService($pdo);

        $this->assertSame([], $service->summarize());
    }

    public function testServiceGroupsDomainsAndCountsCorrectly(): void
    {
        $pdo = $this->buildPdo();
        $pdo->exec("INSERT INTO crawl_urls (domain,status,http_status,error_message,created_at,updated_at) VALUES
            ('example.com','queued',NULL,NULL,'2026-05-01 10:00:00','2026-05-01 10:00:00'),
            ('example.com','running',NULL,NULL,'2026-05-01 10:01:00','2026-05-01 10:01:00'),
            ('example.com','failed',503,'timeout','2026-05-01 10:02:00','2026-05-01 10:02:00'),
            ('other.com','indexed',200,NULL,'2026-05-01 10:03:00','2026-05-01 10:03:00')");
        $pdo->exec("INSERT INTO indexed_pages (domain) VALUES ('example.com'), ('example.com'), ('other.com')");

        $service = new CrawlDomainStatusService($pdo);
        $rows = $service->summarize(20);

        $example = $this->findDomain($rows, 'example.com');
        $this->assertNotNull($example);
        $this->assertSame(1, $example['queued']);
        $this->assertSame(1, $example['running']);
        $this->assertSame(0, $example['indexed']);
        $this->assertSame(0, $example['skipped']);
        $this->assertSame(1, $example['failed']);
        $this->assertSame(2, $example['indexed_pages_count']);
    }

    public function testServiceFiltersByDomain(): void
    {
        $pdo = $this->buildPdo();
        $pdo->exec("INSERT INTO crawl_urls (domain,status,created_at,updated_at) VALUES
            ('example.com','queued','2026-05-01 10:00:00','2026-05-01 10:00:00'),
            ('other.com','queued','2026-05-01 10:00:00','2026-05-01 10:00:00')");

        $service = new CrawlDomainStatusService($pdo);
        $rows = $service->summarize(20, 'example.com');

        $this->assertCount(1, $rows);
        $this->assertSame('example.com', $rows[0]['domain']);
    }

    public function testServiceIncludesRecentErrorsWhenRequested(): void
    {
        $pdo = $this->buildPdo();
        $pdo->exec("INSERT INTO crawl_urls (domain,status,http_status,error_message,created_at,updated_at) VALUES
            ('example.com','failed',429,'rate limited','2026-05-01 10:00:00','2026-05-01 10:00:00'),
            ('example.com','failed',403,'forbidden','2026-05-01 10:01:00','2026-05-01 10:01:00')");

        $service = new CrawlDomainStatusService($pdo);
        $rows = $service->summarize(20, null, true);

        $this->assertNotEmpty($rows[0]['recent_errors']);
        $this->assertStringContainsString('http=403', (string) $rows[0]['recent_errors'][0]);
    }

    public function testCrawlDomainsCommandIsReadOnlyAndDoesNotExecuteCrawler(): void
    {
        $kernelSource = file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertNotFalse($kernelSource);

        $this->assertStringContainsString('private function crawlDomains(array $argv): int', $kernelSource);
        preg_match('/private function crawlDomains\(array \$argv\): int\s*\{(?P<body>.*?)\n    \}\n\n    private function classifyCrawlerError/s', (string) $kernelSource, $matches);
        $body = (string) ($matches['body'] ?? '');

        $this->assertNotSame('', $body);
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|ALTER|DROP)\b/i', $body);
        $this->assertStringNotContainsString('runJob(', $body);
        $this->assertStringNotContainsString('crawlRun(', $body);
    }

    private function buildPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE crawl_urls (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL, status TEXT NOT NULL, http_status INTEGER NULL, error_message TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE indexed_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL)');

        return $pdo;
    }

    /** @param array<int, array<string,mixed>> $rows */
    private function findDomain(array $rows, string $domain): ?array
    {
        foreach ($rows as $row) {
            if (($row['domain'] ?? null) === $domain) {
                return $row;
            }
        }

        return null;
    }
}

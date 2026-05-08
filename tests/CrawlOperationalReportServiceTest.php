<?php

declare(strict_types=1);

use Browser\Services\CrawlDomainAdviceService;
use Browser\Services\CrawlDomainPolicyService;
use Browser\Services\CrawlDomainStatusService;
use Browser\Services\CrawlOperationalReportService;
use PHPUnit\Framework\TestCase;

final class CrawlOperationalReportServiceTest extends TestCase
{
    public function testEmptyReportDoesNotFail(): void
    {
        $pdo = $this->buildPdo();
        $service = $this->buildService($pdo, []);

        $report = $service->build(null, 10);

        $this->assertSame(0, $report['jobs']['queued']);
        $this->assertSame(0, $report['urls']['queued']);
        $this->assertSame(0, $report['indexed_pages']['total']);
        $this->assertFalse((bool) $report['has_data']);
    }

    public function testAggregatesJobsAndUrlsAndSupportsDomainAndLimit(): void
    {
        $pdo = $this->buildPdo();
        $pdo->exec("INSERT INTO crawl_jobs (seed_url,status) VALUES
            ('https://example.com','queued'),('https://example.com/a','failed'),('https://other.com','running')");
        $pdo->exec("INSERT INTO crawl_urls (domain,url,status,http_status,error_message,created_at,updated_at) VALUES
            ('example.com','https://example.com/1','queued',NULL,NULL,'2026-05-08 10:00:00','2026-05-08 10:00:00'),
            ('example.com','https://example.com/2','failed',503,'timeout','2026-05-08 11:00:00','2026-05-08 11:00:00'),
            ('other.com','https://other.com/1','indexed',200,NULL,'2026-05-08 09:00:00','2026-05-08 09:00:00')");
        $pdo->exec("INSERT INTO indexed_pages (domain) VALUES ('example.com'),('other.com')");

        $service = $this->buildService($pdo, [
            'example.com' => ['domain' => 'example.com', 'paused' => true, 'reason' => 'manual', 'paused_at' => '2026-05-08T00:00:00Z'],
        ]);

        $filtered = $service->build('example.com', 1);
        $this->assertSame(1, $filtered['jobs']['queued']);
        $this->assertSame(1, $filtered['jobs']['failed']);
        $this->assertSame(1, $filtered['urls']['queued']);
        $this->assertSame(1, $filtered['urls']['failed']);
        $this->assertSame(1, $filtered['indexed_pages']['total']);
        $this->assertCount(1, $filtered['paused_domains']);
        $this->assertLessThanOrEqual(1, count($filtered['recent_errors']));
    }

    public function testCommandRegistrationAndReadOnlyContractChecks(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:report', $kernel);

        $body = $this->extractMethodBody($kernel, 'crawlReport');

        $this->assertNotSame('', $body);
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|ALTER|DROP)\b/i', $body);
        $this->assertStringNotContainsString('crawlRun(', $body);
        $this->assertStringNotContainsString('runJob(', $body);
        $this->assertStringNotContainsString('pause(', $body);
        $this->assertStringNotContainsString('resume(', $body);
        $this->assertStringNotContainsString('CrawlJob::create(', $body);
    }

    private function buildService(PDO $pdo, array $policies): CrawlOperationalReportService
    {
        $policyPath = tempnam(sys_get_temp_dir(), 'policy_');
        file_put_contents($policyPath, json_encode($policies, JSON_PRETTY_PRINT));
        $policy = new CrawlDomainPolicyService($policyPath);

        return new CrawlOperationalReportService(
            $pdo,
            new CrawlDomainStatusService($pdo, $policy),
            new CrawlDomainAdviceService($pdo, $policy),
            $policy,
        );
    }

    private function buildPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE crawl_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, seed_url TEXT NOT NULL, status TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE crawl_urls (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL, url TEXT NOT NULL, status TEXT NOT NULL, http_status INTEGER NULL, error_message TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE indexed_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL)');

        return $pdo;
    }

    private function extractMethodBody(string $source, string $methodName): string
    {
        $signature = 'private function ' . $methodName . '(';
        $start = strpos($source, $signature);
        $this->assertNotFalse($start, 'No se encontró método ' . $methodName . ' en Kernel.php');

        $braceStart = strpos($source, '{', $start);
        $this->assertNotFalse($braceStart, 'No se encontró apertura de método ' . $methodName);

        $length = strlen($source);
        $depth = 0;
        for ($i = $braceStart; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
                continue;
            }

            if ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $braceStart + 1, $i - $braceStart - 1);
                }
            }
        }

        self::fail('No se pudo extraer el cuerpo del método ' . $methodName);
    }
}

<?php

declare(strict_types=1);

use Browser\Services\CrawlDomainAdviceService;
use Browser\Services\CrawlDomainPolicyService;
use PHPUnit\Framework\TestCase;

final class CrawlDomainAdviceServiceTest extends TestCase
{
    public function testReturnsEmptyWhenNoSignals(): void
    {
        $service = new CrawlDomainAdviceService($this->buildPdo(), new CrawlDomainPolicyService('/tmp/non-existent-policy.json'));
        $this->assertSame([], $service->recommend());
    }

    public function testDetectsRepeated429AndSuggestsPause(): void
    {
        $pdo = $this->buildPdo();
        $this->seedRows($pdo, 'example.com', 6, 429, 'rate limit');
        $rows = (new CrawlDomainAdviceService($pdo, new CrawlDomainPolicyService('/tmp/non-existent-policy.json')))->recommend(5, 20);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('429', (string) $rows[0]['recommendation']);
        $this->assertStringContainsString('crawl:domain-policy pause', (string) $rows[0]['suggested_command']);
    }

    public function testDetects403And503TimeoutAndRobotsSignals(): void
    {
        $pdo = $this->buildPdo();
        $this->seedRows($pdo, 'forbidden.com', 5, 403, 'forbidden');
        $this->seedRows($pdo, 'unstable.com', 5, 503, 'timeout');
        $this->seedRows($pdo, 'robots.com', 5, null, 'robots disallow path');

        $rows = (new CrawlDomainAdviceService($pdo, new CrawlDomainPolicyService('/tmp/non-existent-policy.json')))->recommend(5, 20);
        $domains = array_column($rows, 'domain');
        $this->assertContains('forbidden.com', $domains);
        $this->assertContains('unstable.com', $domains);
        $this->assertContains('robots.com', $domains);
    }

    public function testRespectsThresholdAndDomainFilter(): void
    {
        $pdo = $this->buildPdo();
        $this->seedRows($pdo, 'a.com', 4, 429, 'rate limit');
        $this->seedRows($pdo, 'b.com', 6, 429, 'rate limit');

        $service = new CrawlDomainAdviceService($pdo, new CrawlDomainPolicyService('/tmp/non-existent-policy.json'));
        $this->assertCount(1, $service->recommend(5, 20));
        $filtered = $service->recommend(1, 20, 'a.com');
        $this->assertCount(1, $filtered);
        $this->assertSame('a.com', $filtered[0]['domain']);
    }

    public function testPausedDomainDoesNotSuggestDuplicatePause(): void
    {
        $pdo = $this->buildPdo();
        $this->seedRows($pdo, 'paused.com', 6, 429, 'rate limit');

        $policyFile = tempnam(sys_get_temp_dir(), 'policy_');
        file_put_contents($policyFile, json_encode(['paused.com' => ['domain' => 'paused.com', 'paused' => true, 'reason' => 'manual', 'paused_at' => '2026-05-08T00:00:00Z']], JSON_PRETTY_PRINT));
        $service = new CrawlDomainAdviceService($pdo, new CrawlDomainPolicyService($policyFile));

        $rows = $service->recommend(5, 20, 'paused.com');
        $this->assertTrue((bool) $rows[0]['paused']);
        $this->assertNull($rows[0]['suggested_command']);
    }

    public function testCommandReadOnlyContractTextualChecks(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__) . '/app/Console/Kernel.php');
        $this->assertStringContainsString('crawl:domain-advice', $kernel);
        preg_match('/private function crawlDomainAdvice\(array \$argv\): int\s*\{(?P<body>.*?)\n    \}\n\n    private function classifyCrawlerError/s', $kernel, $m);
        $body = (string) ($m['body'] ?? '');
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|ALTER|DROP|runJob\(|crawlRun\()\b/i', $body);
        $this->assertStringNotContainsString('pause(', $body);
        $this->assertStringNotContainsString('resume(', $body);
    }

    private function buildPdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE crawl_urls (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT NOT NULL, status TEXT NOT NULL, http_status INTEGER NULL, error_message TEXT NULL, updated_at TEXT NULL)');

        return $pdo;
    }

    private function seedRows(\PDO $pdo, string $domain, int $times, ?int $httpStatus, string $error): void
    {
        $stmt = $pdo->prepare('INSERT INTO crawl_urls (domain,status,http_status,error_message,updated_at) VALUES (:domain,:status,:http_status,:error_message,:updated_at)');
        for ($i = 0; $i < $times; $i++) {
            $stmt->execute([
                'domain' => $domain,
                'status' => 'failed',
                'http_status' => $httpStatus,
                'error_message' => $error,
                'updated_at' => '2026-05-08 10:00:00',
            ]);
        }
    }
}

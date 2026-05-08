<?php

declare(strict_types=1);

use Browser\Services\CrawlDomainPolicyService;
use PHPUnit\Framework\TestCase;

final class CrawlDomainPolicyServiceTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/domain-policy-' . uniqid('', true) . '.json';
    }

    public function testReturnsEmptyWhenMissingFile(): void
    {
        $service = new CrawlDomainPolicyService($this->file);
        $this->assertSame([], $service->all());
    }

    public function testPauseAndStatusAndResume(): void
    {
        $service = new CrawlDomainPolicyService($this->file);
        $entry = $service->pause('https://www.example.com/path', '429 Too Many Requests');
        $this->assertSame('example.com', $entry['domain']);
        $this->assertTrue($entry['paused']);
        $this->assertNotEmpty($entry['paused_at']);

        $status = $service->status('example.com');
        $this->assertTrue($status['paused']);

        $service->resume('http://example.com');
        $status2 = $service->status('example.com');
        $this->assertFalse($status2['paused']);
    }

    public function testCorruptJsonDoesNotFatal(): void
    {
        file_put_contents($this->file, '{broken');
        $service = new CrawlDomainPolicyService($this->file);
        $this->assertSame([], $service->all());
        $this->assertNotNull($service->getLastWarning());
    }
}

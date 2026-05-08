<?php

declare(strict_types=1);

use Browser\Services\CrawlRateLimiter;
use PHPUnit\Framework\TestCase;

final class CrawlRateLimiterTest extends TestCase
{
    public function testAllowsDifferentDomainsWithoutCooldownCollision(): void
    {
        $limiter = new CrawlRateLimiter();

        $limiter->markProcessed('example.com', 1000);

        $this->assertTrue($limiter->canProcessDomain('other.com', 1001));
    }

    public function testBlocksSameDomainInsideCooldownAndAllowsAfterCooldown(): void
    {
        $limiter = new CrawlRateLimiter();
        $limiter->markProcessed('example.com', 1000);

        $this->assertFalse($limiter->canProcessDomain('example.com', 1005));
        $this->assertTrue($limiter->canProcessDomain('example.com', 1015));
    }

    public function testTreatsDomainCaseInsensitively(): void
    {
        $limiter = new CrawlRateLimiter();
        $limiter->markProcessed('EXAMPLE.COM', 2000);

        $this->assertFalse($limiter->canProcessDomain('example.com', 2001));
    }
}

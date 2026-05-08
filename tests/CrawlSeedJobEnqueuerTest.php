<?php

declare(strict_types=1);

use Browser\Services\CrawlSeedJobEnqueuer;
use PHPUnit\Framework\TestCase;

final class CrawlSeedJobEnqueuerTest extends TestCase
{
    public function testSkipsDuplicateQueuedOrRunningUrls(): void
    {
        $service = new CrawlSeedJobEnqueuer();
        $created = [];

        $result = $service->enqueueMany(
            ['https://example.com', 'https://a.com'],
            1,
            10,
            10,
            fn (string $u): ?string => $u,
            fn (string $u): bool => $u === 'https://example.com',
            function (string $url, int $depth, int $pages) use (&$created): int {
                $created[] = [$url, $depth, $pages];
                return count($created);
            }
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(0, $result['invalid']);
    }

    public function testCountsInvalidAndDuplicateSeparately(): void
    {
        $service = new CrawlSeedJobEnqueuer();

        $result = $service->enqueueMany(
            ['nota-url', 'https://dup.com', 'https://ok.com'],
            1,
            10,
            10,
            fn (string $u): ?string => str_starts_with($u, 'https://') ? $u : null,
            fn (string $u): bool => $u === 'https://dup.com',
            fn (string $url, int $depth, int $pages): int => 1
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['invalid']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(0, $result['errors']);
    }

    public function testLimitRespectedDespiteDuplicates(): void
    {
        $service = new CrawlSeedJobEnqueuer();
        $created = [];

        $result = $service->enqueueMany(
            ['https://dup.com', 'https://ok1.com', 'https://ok2.com', 'https://ok3.com'],
            1,
            10,
            2,
            fn (string $u): ?string => $u,
            fn (string $u): bool => $u === 'https://dup.com',
            function (string $url, int $depth, int $pages) use (&$created): int {
                $created[] = $url;
                return count($created);
            }
        );

        $this->assertSame(['https://ok1.com', 'https://ok2.com'], $created);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['duplicates']);
    }
}

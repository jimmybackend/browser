<?php

declare(strict_types=1);

namespace Browser\Services;

final class CrawlRateLimiter
{
    private const DOMAIN_COOLDOWN_SECONDS = 15;

    /** @var array<string, int> */
    private array $lastProcessedAtByDomain = [];

    public function canProcessDomain(string $domain, ?int $now = null): bool
    {
        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return true;
        }

        $time = $now ?? time();
        $lastProcessedAt = $this->lastProcessedAtByDomain[$normalized] ?? null;

        if ($lastProcessedAt === null) {
            return true;
        }

        return ($time - $lastProcessedAt) >= self::DOMAIN_COOLDOWN_SECONDS;
    }

    public function markProcessed(string $domain, ?int $now = null): void
    {
        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return;
        }

        $this->lastProcessedAtByDomain[$normalized] = $now ?? time();
    }

    public static function cooldownSeconds(): int
    {
        return self::DOMAIN_COOLDOWN_SECONDS;
    }
}

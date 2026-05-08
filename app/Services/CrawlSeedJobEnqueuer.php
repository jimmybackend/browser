<?php

declare(strict_types=1);

namespace Browser\Services;

use Throwable;

final class CrawlSeedJobEnqueuer
{
    /**
     * @param callable(string):?string $normalizeUrl
     * @param callable(string):bool $hasPendingDuplicate
     * @param callable(string,int,int):int $createJob
     * @param callable(string):void|null $logger
     * @return array{created:int,invalid:int,duplicates:int,errors:int}
     */
    public function enqueueMany(
        array $candidates,
        int $maxDepth,
        int $maxPages,
        int $limit,
        callable $normalizeUrl,
        callable $hasPendingDuplicate,
        callable $createJob,
        ?callable $logger = null
    ): array {
        $created = 0;
        $invalid = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($candidates as $candidate) {
            if ($created >= $limit) {
                break;
            }

            $normalized = $normalizeUrl((string) $candidate);
            if (!is_string($normalized) || $normalized === '') {
                $invalid++;
                if ($logger !== null) {
                    $logger('[SKIP] URL inválida.');
                }
                continue;
            }

            if ($hasPendingDuplicate($normalized)) {
                $duplicates++;
                if ($logger !== null) {
                    $logger('[SKIP] Job duplicado: ' . $normalized);
                }
                continue;
            }

            try {
                $createJob($normalized, $maxDepth, $maxPages);
                $created++;
            } catch (Throwable $exception) {
                $errors++;
                if ($logger !== null) {
                    $logger('[SKIP] Error controlado al crear job.');
                }
            }
        }

        return [
            'created' => $created,
            'invalid' => $invalid,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }
}

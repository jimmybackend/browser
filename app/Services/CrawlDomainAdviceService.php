<?php

declare(strict_types=1);

namespace Browser\Services;

use PDO;

final class CrawlDomainAdviceService
{
    public function __construct(private readonly ?PDO $pdo = null, private readonly ?CrawlDomainPolicyService $policyService = null)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function recommend(int $threshold = 5, int $limit = 20, ?string $domain = null): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $safeThreshold = max(1, min($threshold, 100));
        $safeLimit = max(1, min($limit, 200));
        $safeDomain = $this->normalizeDomain($domain);

        $rows = $this->fetchDomainSignals($safeDomain);
        if ($rows === []) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $domainName = strtolower((string) ($row['domain'] ?? ''));
            if ($domainName === '') {
                continue;
            }

            if (!isset($grouped[$domainName])) {
                $grouped[$domainName] = [
                    'domain' => $domainName,
                    'signals' => ['http_429' => 0, 'http_403' => 0, 'http_503' => 0, 'timeouts' => 0, 'robots_disallow' => 0, 'generic_errors' => 0],
                    'error_count' => 0,
                ];
            }

            $signal = $this->signalFromRow($row);
            $grouped[$domainName]['signals'][$signal]++;
            $grouped[$domainName]['error_count']++;
        }

        $advice = [];
        foreach ($grouped as $item) {
            if ((int) $item['error_count'] < $safeThreshold) {
                continue;
            }

            $dominant = $this->dominantSignal($item['signals']);
            $paused = $this->policyService?->isPaused($item['domain']) ?? false;
            $reason = $this->reasonForSignal($dominant);

            $advice[] = [
                'domain' => $item['domain'],
                'signals' => $item['signals'],
                'error_count' => (int) $item['error_count'],
                'recommendation' => $paused
                    ? 'Dominio ya pausado manualmente; monitorear y reactivar cuando sea seguro.'
                    : $this->recommendationForSignal($dominant),
                'paused' => $paused,
                'suggested_command' => $paused
                    ? null
                    : sprintf('php bin/browser crawl:domain-policy pause --domain=%s --reason="%s"', $item['domain'], $reason),
            ];
        }

        usort($advice, static function (array $a, array $b): int {
            $countSort = ((int) ($b['error_count'] ?? 0)) <=> ((int) ($a['error_count'] ?? 0));
            if ($countSort !== 0) {
                return $countSort;
            }

            return strcmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? ''));
        });

        return array_slice($advice, 0, $safeLimit);
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $clean = strtolower(trim($domain));
        return $clean === '' ? null : $clean;
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchDomainSignals(?string $domain): array
    {
        $sql = 'SELECT domain, http_status, error_message
                FROM crawl_urls
                WHERE (
                    http_status IN (429, 403, 503)
                    OR (error_message IS NOT NULL AND error_message <> "")
                    OR status = :failedStatus
                )';
        $params = ['failedStatus' => 'failed'];

        if ($domain !== null) {
            $sql .= ' AND domain = :domain';
            $params['domain'] = $domain;
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 1000';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        /** @var array<int,array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function signalFromRow(array $row): string
    {
        $status = (int) ($row['http_status'] ?? 0);
        $error = strtolower((string) ($row['error_message'] ?? ''));

        if ($status === 429 || str_contains($error, '429') || str_contains($error, 'rate limit')) {
            return 'http_429';
        }
        if ($status === 403 || str_contains($error, '403') || str_contains($error, 'forbidden')) {
            return 'http_403';
        }
        if ($status === 503 || str_contains($error, '503') || str_contains($error, 'service unavailable')) {
            return 'http_503';
        }
        if (str_contains($error, 'timeout') || str_contains($error, 'timed out')) {
            return 'timeouts';
        }
        if (str_contains($error, 'robots') || str_contains($error, 'disallow')) {
            return 'robots_disallow';
        }

        return 'generic_errors';
    }

    /** @param array<string,int> $signals */
    private function dominantSignal(array $signals): string
    {
        arsort($signals);
        return (string) array_key_first($signals);
    }

    private function recommendationForSignal(string $signal): string
    {
        return match ($signal) {
            'http_429' => 'Recomendado pausar temporalmente por rate limit (429 repetidos).',
            'http_403' => 'Recomendado revisar bloqueo/permiso y pausar si persiste (403 repetidos).',
            'http_503', 'timeouts' => 'Recomendado pausar temporalmente por inestabilidad del dominio.',
            'robots_disallow' => 'Recomendado revisar robots/disallow y política antes de continuar.',
            default => 'Recomendado revisar errores repetidos y pausar manualmente si aplica.',
        };
    }

    private function reasonForSignal(string $signal): string
    {
        return match ($signal) {
            'http_429' => '429 repetidos',
            'http_403' => '403 repetidos',
            'http_503' => '503 repetidos',
            'timeouts' => 'timeouts repetidos',
            'robots_disallow' => 'robots/disallow repetidos',
            default => 'errores repetidos',
        };
    }
}

<?php

declare(strict_types=1);

namespace Browser\Services;

final class CrawlReportSummaryService
{
    public function __construct(
        private readonly CrawlReportHistoryService $historyService,
        private readonly CrawlReportShowService $showService,
        private readonly CrawlReportDiffService $diffService,
    ) {
    }

    /** @return array<string,mixed> */
    public function summarize(int $limit, ?string $domain = null): array
    {
        $items = $this->historyService->list($limit, $domain);
        $warnings = [];
        $valid = [];
        $invalidCount = 0;

        foreach ($items as $item) {
            $filename = (string) ($item['filename'] ?? '');
            if ($filename === '') {
                continue;
            }

            try {
                $snapshot = $this->showService->showByFilename($filename);
                $report = (array) ($snapshot['report'] ?? []);
                $report['_meta'] = [
                    'filename' => $filename,
                    'order_key' => $this->resolveOrderKey($report, $item),
                ];
                $valid[] = $report;
            } catch (\RuntimeException $exception) {
                if (str_contains($exception->getMessage(), 'JSON inválido')) {
                    $invalidCount++;
                    continue;
                }
                throw $exception;
            }
        }

        if ($valid === []) {
            return [
                'analyzed_count' => 0,
                'invalid_count' => $invalidCount,
                'domain' => CrawlReportStorageService::sanitizeDomainForFilename($domain),
                'limit' => $limit,
                'first_snapshot' => null,
                'last_snapshot' => null,
                'indexed_pages' => ['first_total' => null, 'latest_total' => null, 'delta' => null],
                'jobs_by_status_delta' => [],
                'urls_by_status_delta' => [],
                'paused_domains_count' => null,
                'recommendations_count' => null,
                'recent_errors_count' => null,
                'warnings' => $warnings,
            ];
        }

        usort($valid, static fn (array $a, array $b): int => strcmp((string) (($a['_meta']['order_key'] ?? '')), (string) (($b['_meta']['order_key'] ?? ''))));

        $first = $valid[0];
        $last = $valid[count($valid) - 1];

        $indexedLatest = $this->extractIndexedTotal($last, $warnings, 'último');
        $indexedFirst = $this->extractIndexedTotal($first, $warnings, 'primero');

        $diff = null;
        if (count($valid) >= 2) {
            $diff = $this->diffService->diff($first, $last);
        } else {
            $warnings[] = 'No hay suficiente historial para calcular tendencia (se requiere >= 2 snapshots válidos).';
        }

        return [
            'analyzed_count' => count($valid),
            'invalid_count' => $invalidCount,
            'domain' => CrawlReportStorageService::sanitizeDomainForFilename($domain),
            'limit' => $limit,
            'first_snapshot' => $this->snapshotMeta($first),
            'last_snapshot' => $this->snapshotMeta($last),
            'indexed_pages' => [
                'first_total' => $indexedFirst,
                'latest_total' => $indexedLatest,
                'delta' => ($indexedLatest !== null && $indexedFirst !== null) ? ($indexedLatest - $indexedFirst) : null,
            ],
            'jobs_by_status_delta' => (array) ($diff['jobs'] ?? []),
            'urls_by_status_delta' => (array) ($diff['urls'] ?? []),
            'paused_domains_count' => $this->listCount($last, 'paused_domains', $warnings),
            'recommendations_count' => $this->listCount($last, 'domain_advice', $warnings),
            'recent_errors_count' => $this->listCount($last, 'recent_errors', $warnings),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param array<string,mixed> $report @return array<string,mixed> */
    private function snapshotMeta(array $report): array
    {
        return [
            'filename' => $report['_meta']['filename'] ?? null,
            'snapshot_at' => $report['snapshot_at'] ?? $report['generated_at'] ?? $report['created_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $report */
    private function extractIndexedTotal(array $report, array &$warnings, string $label): ?int
    {
        if (!isset($report['indexed_pages']) || !is_array($report['indexed_pages'])) {
            $warnings[] = 'Falta sección indexed_pages en snapshot ' . $label . '.';
            return null;
        }

        if (!array_key_exists('total', $report['indexed_pages'])) {
            $warnings[] = 'Falta indexed_pages.total en snapshot ' . $label . '.';
            return null;
        }

        return (int) $report['indexed_pages']['total'];
    }



    /** @param array<string,mixed> $report @param array<string,mixed> $item */
    private function resolveOrderKey(array $report, array $item): string
    {
        $snapshotAt = (string) ($report['snapshot_at'] ?? $report['generated_at'] ?? $report['created_at'] ?? '');
        if ($snapshotAt !== '') {
            return $snapshotAt;
        }

        $filenameTimestamp = (string) ($item['timestamp'] ?? '');
        if ($filenameTimestamp !== '') {
            return $filenameTimestamp;
        }

        return (string) ($item['modified_at'] ?? ($item['filename'] ?? ''));
    }
    /** @param array<string,mixed> $report */
    private function listCount(array $report, string $key, array &$warnings): ?int
    {
        if (!isset($report[$key]) || !is_array($report[$key])) {
            $warnings[] = 'Falta sección esperada: ' . $key . '.';
            return null;
        }

        return count($report[$key]);
    }
}

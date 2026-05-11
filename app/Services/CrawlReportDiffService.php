<?php

declare(strict_types=1);

namespace Browser\Services;

final class CrawlReportDiffService
{
    /** @param array<string,mixed> $from @param array<string,mixed> $to @return array<string,mixed> */
    public function diff(array $from, array $to): array
    {
        $jobs = $this->diffNumericMap((array) ($from['jobs'] ?? []), (array) ($to['jobs'] ?? []));
        $urls = $this->diffNumericMap((array) ($from['urls'] ?? []), (array) ($to['urls'] ?? []));

        $fromIndexed = $this->getIndexedTotal($from);
        $toIndexed = $this->getIndexedTotal($to);

        return [
            'from' => [
                'filename' => $from['_meta']['filename'] ?? null,
                'snapshot_at' => $from['snapshot_at'] ?? $from['generated_at'] ?? $from['created_at'] ?? null,
            ],
            'to' => [
                'filename' => $to['_meta']['filename'] ?? null,
                'snapshot_at' => $to['snapshot_at'] ?? $to['generated_at'] ?? $to['created_at'] ?? null,
            ],
            'jobs' => $jobs,
            'urls' => $urls,
            'indexed_pages_total' => $this->deltaItem($fromIndexed, $toIndexed),
            'paused_domains' => $this->diffStringList($this->extractDomains((array) ($from['paused_domains'] ?? [])), $this->extractDomains((array) ($to['paused_domains'] ?? []))),
            'recommendations' => $this->diffStringList($this->extractRecommendations((array) ($from['domain_advice'] ?? [])), $this->extractRecommendations((array) ($to['domain_advice'] ?? []))),
            'recent_errors' => $this->diffStringList($this->extractErrors((array) ($from['recent_errors'] ?? [])), $this->extractErrors((array) ($to['recent_errors'] ?? []))),
        ];
    }

    /** @param array<string,mixed> $from @param array<string,mixed> $to @return array<string,array<string,int>> */
    private function diffNumericMap(array $from, array $to): array
    {
        $keys = array_unique(array_merge(array_keys($from), array_keys($to)));
        sort($keys);
        $out = [];
        foreach ($keys as $key) {
            $out[(string) $key] = $this->deltaItem((int) ($from[$key] ?? 0), (int) ($to[$key] ?? 0));
        }
        return $out;
    }

    /** @return array<string,int> */
    private function deltaItem(int $from, int $to): array
    {
        return ['from' => $from, 'to' => $to, 'delta' => $to - $from];
    }

    /** @param array<int,string> $from @param array<int,string> $to @return array<string,mixed> */
    private function diffStringList(array $from, array $to): array
    {
        $from = array_values(array_unique(array_filter(array_map('strval', $from), static fn (string $v): bool => $v !== '')));
        $to = array_values(array_unique(array_filter(array_map('strval', $to), static fn (string $v): bool => $v !== '')));
        sort($from);
        sort($to);

        $added = array_values(array_diff($to, $from));
        $removed = array_values(array_diff($from, $to));

        return [
            'from_count' => count($from),
            'to_count' => count($to),
            'added_count' => count($added),
            'removed_count' => count($removed),
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /** @param array<string,mixed> $report */
    private function getIndexedTotal(array $report): int
    {
        return (int) ((array) ($report['indexed_pages'] ?? []))['total'] ?? 0;
    }

    /** @param array<int,mixed> $items @return array<int,string> */
    private function extractDomains(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['domain'])) {
                $out[] = (string) $item['domain'];
            }
        }
        return $out;
    }

    /** @param array<int,mixed> $items @return array<int,string> */
    private function extractRecommendations(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $domain = (string) ($item['domain'] ?? '-');
            $recommendation = (string) ($item['recommendation'] ?? '');
            if ($recommendation !== '') {
                $out[] = $domain . '|' . $recommendation;
            }
        }
        return $out;
    }

    /** @param array<int,mixed> $items @return array<int,string> */
    private function extractErrors(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $out[] = sprintf('%s|%s|%s', (string) ($item['domain'] ?? '-'), (string) ($item['http_status'] ?? '-'), (string) ($item['error_message'] ?? '-'));
        }
        return $out;
    }
}

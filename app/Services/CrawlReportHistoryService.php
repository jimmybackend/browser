<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;

final class CrawlReportHistoryService
{
    public function __construct(
        private readonly string $reportsDirectory,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(int $limit, ?string $domain = null): array
    {
        if (!is_dir($this->reportsDirectory)) {
            return [];
        }

        if (!is_readable($this->reportsDirectory)) {
            throw new RuntimeException('No se puede leer el directorio de snapshots del crawler.');
        }

        $domainFilter = CrawlReportStorageService::sanitizeDomainForFilename($domain);
        $pattern = $this->reportsDirectory . DIRECTORY_SEPARATOR . 'crawl-report-*.json';
        $paths = glob($pattern);

        if ($paths === false) {
            throw new RuntimeException('No se pudo listar snapshots del crawler.');
        }

        $items = [];
        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $filename = basename($path);
            if (!$this->isExpectedSnapshotName($filename)) {
                continue;
            }

            $meta = $this->parseFilename($filename);
            if ($domainFilter !== null && ($meta['domain'] ?? null) !== $domainFilter) {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime === false) {
                throw new RuntimeException('No se pudo leer metadata de snapshot: ' . $filename);
            }

            $size = @filesize($path);
            if ($size === false) {
                throw new RuntimeException('No se pudo leer tamaño de snapshot: ' . $filename);
            }

            $items[] = [
                'filename' => $filename,
                'relative_path' => 'storage/crawler/reports/' . $filename,
                'size_bytes' => (int) $size,
                'modified_at' => gmdate('c', $mtime),
                'domain' => $meta['domain'],
                'timestamp' => $meta['timestamp'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));

        return array_slice($items, 0, $limit);
    }

    private function isExpectedSnapshotName(string $filename): bool
    {
        if (str_starts_with($filename, '.')) {
            return false;
        }

        return (bool) preg_match('/^crawl-report-(?:[a-z0-9.-]+-)?\d{8}-\d{6}\.json$/', $filename);
    }

    /** @return array{domain:?string,timestamp:string} */
    private function parseFilename(string $filename): array
    {
        if (preg_match('/^crawl-report-(.+)-(\d{8}-\d{6})\.json$/', $filename, $matches) === 1) {
            return [
                'domain' => $matches[1],
                'timestamp' => $matches[2],
            ];
        }

        preg_match('/^crawl-report-(\d{8}-\d{6})\.json$/', $filename, $matches);

        return [
            'domain' => null,
            'timestamp' => $matches[1] ?? '',
        ];
    }
}

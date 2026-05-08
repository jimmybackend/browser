<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;

final class CrawlReportStorageService
{
    public function __construct(
        private readonly string $reportsDirectory,
    ) {
    }

    /** @param array<string,mixed> $report */
    public function save(array $report, ?string $domain = null): string
    {
        $this->ensureReportsDirectory();

        $domainPart = $this->sanitizeDomainForFilename($domain);
        $timestamp = gmdate('Ymd-His');
        $filename = $domainPart === null
            ? sprintf('crawl-report-%s.json', $timestamp)
            : sprintf('crawl-report-%s-%s.json', $domainPart, $timestamp);

        $finalPath = $this->reportsDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($finalPath)) {
            throw new RuntimeException('Ya existe un snapshot con el mismo nombre, intenta nuevamente en un segundo distinto.');
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('No se pudo serializar el reporte a JSON.');
        }

        $tmpPath = $this->reportsDirectory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(8)) . '.json';

        if (file_put_contents($tmpPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir archivo temporal del snapshot.');
        }

        if (!@rename($tmpPath, $finalPath)) {
            @unlink($tmpPath);
            throw new RuntimeException('No se pudo finalizar escritura atómica del snapshot.');
        }

        return $finalPath;
    }

    private function ensureReportsDirectory(): void
    {
        if (is_dir($this->reportsDirectory)) {
            return;
        }

        if (!@mkdir($this->reportsDirectory, 0775, true) && !is_dir($this->reportsDirectory)) {
            throw new RuntimeException('No se pudo crear el directorio de snapshots del crawler.');
        }
    }

    private function sanitizeDomainForFilename(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $clean = strtolower(trim($domain));
        if ($clean === '') {
            return null;
        }

        $clean = preg_replace('/[^a-z0-9.-]+/', '-', $clean);
        $clean = trim((string) $clean, '.-');

        return $clean !== '' ? $clean : 'domain';
    }
}

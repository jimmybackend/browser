<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;

final class CrawlReportPruneService
{
    public function __construct(private readonly string $reportsDirectory)
    {
    }

    /** @return array{exists:bool,files:array<int,array{filename:string,path:string,mtime:int}>} */
    public function listSafeFiles(?string $domain = null): array
    {
        if (!is_dir($this->reportsDirectory)) {
            return ['exists' => false, 'files' => []];
        }

        if (!is_readable($this->reportsDirectory)) {
            throw new RuntimeException('No se puede leer el directorio de snapshots del crawler.');
        }

        $domainFilter = CrawlReportStorageService::sanitizeDomainForFilename($domain);
        $paths = glob($this->reportsDirectory . DIRECTORY_SEPARATOR . 'crawl-report-*.json');
        if ($paths === false) {
            throw new RuntimeException('No se pudo listar snapshots del crawler.');
        }

        $files = [];
        foreach ($paths as $path) {
            $real = realpath($path);
            $base = realpath($this->reportsDirectory);
            if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $filename = basename($real);
            if (!preg_match('/^crawl-report-(?:[a-z0-9.-]+-)?\d{8}-\d{6}\.json$/', $filename)) {
                continue;
            }

            if ($domainFilter !== null) {
                $meta = $this->parseFilename($filename);
                if (($meta['domain'] ?? null) !== $domainFilter) {
                    continue;
                }
            }

            $mtime = @filemtime($real);
            if ($mtime === false) {
                throw new RuntimeException('No se pudo leer fecha de snapshot: ' . $filename);
            }

            $files[] = ['filename' => $filename, 'path' => $real, 'mtime' => (int) $mtime];
        }

        return ['exists' => true, 'files' => $files];
    }

    /** @param array<int,array{filename:string,path:string,mtime:int}> $files @return array<int,array{filename:string,path:string,mtime:int}> */
    public function selectByDays(array $files, int $days): array
    {
        $threshold = time() - ($days * 86400);
        return array_values(array_filter($files, static fn (array $f): bool => $f['mtime'] < $threshold));
    }

    /** @param array<int,array{filename:string,path:string,mtime:int}> $files @return array<int,array{filename:string,path:string,mtime:int}> */
    public function selectByKeep(array $files, int $keep): array
    {
        usort($files, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return array_slice($files, $keep);
    }

    /** @param array<int,array{filename:string,path:string,mtime:int}> $files */
    public function delete(array $files): int
    {
        $deleted = 0;
        $base = realpath($this->reportsDirectory);
        if ($base === false) {
            throw new RuntimeException('Directorio de snapshots no disponible.');
        }

        foreach ($files as $file) {
            $real = realpath($file['path']);
            if ($real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Ruta fuera del directorio permitido: ' . $file['filename']);
            }

            if (!@unlink($real)) {
                throw new RuntimeException('No se pudo borrar snapshot: ' . $file['filename']);
            }
            $deleted++;
        }

        return $deleted;
    }

    /** @return array{domain:?string} */
    private function parseFilename(string $filename): array
    {
        if (preg_match('/^crawl-report-(.+)-\d{8}-\d{6}\.json$/', $filename, $m) === 1) {
            return ['domain' => $m[1]];
        }

        return ['domain' => null];
    }
}

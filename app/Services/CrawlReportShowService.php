<?php

declare(strict_types=1);

namespace Browser\Services;

use RuntimeException;

final class CrawlReportShowService
{
    public function __construct(
        private readonly string $reportsDirectory,
    ) {
    }

    /** @return array{filename:string,path:string,report:array<string,mixed>} */
    public function showByFilename(string $filename): array
    {
        $safeName = $this->validateFilename($filename);
        $baseDir = $this->canonicalReportsDirectory();
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new RuntimeException('Snapshot no encontrado: ' . $safeName);
        }

        return [
            'filename' => $safeName,
            'path' => $fullPath,
            'report' => $this->readJsonFile($fullPath, $safeName),
        ];
    }

    /** @return array{filename:string,path:string,report:array<string,mixed>} */
    public function showLatest(?string $domain = null): array
    {
        $history = new CrawlReportHistoryService($this->reportsDirectory);
        $items = $history->list(1, $domain);

        if ($items === []) {
            throw new RuntimeException('No saved crawler reports found.');
        }

        $filename = (string) ($items[0]['filename'] ?? '');
        if ($filename === '') {
            throw new RuntimeException('No se pudo resolver el último snapshot.');
        }

        return $this->showByFilename($filename);
    }

    private function canonicalReportsDirectory(): string
    {
        if (!is_dir($this->reportsDirectory)) {
            throw new RuntimeException('No saved crawler reports found.');
        }

        if (!is_readable($this->reportsDirectory)) {
            throw new RuntimeException('No se puede leer el directorio de snapshots del crawler.');
        }

        $real = realpath($this->reportsDirectory);
        if ($real === false) {
            throw new RuntimeException('No se pudo resolver la ruta de snapshots del crawler.');
        }

        return $real;
    }

    private function validateFilename(string $filename): string
    {
        $name = trim($filename);
        if ($name === '') {
            throw new RuntimeException('Debes indicar --file con un nombre de snapshot válido.');
        }

        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..') || str_starts_with($name, '.')) {
            throw new RuntimeException('Nombre de snapshot inválido: no se permiten rutas ni path traversal.');
        }

        if (!preg_match('/^crawl-report-(?:[a-z0-9.-]+-)?\d{8}-\d{6}\.json$/', $name)) {
            throw new RuntimeException('Nombre de snapshot inválido. Formato esperado: crawl-report-*.json');
        }

        return $name;
    }

    /** @return array<string,mixed> */
    private function readJsonFile(string $path, string $filename): array
    {
        $json = @file_get_contents($path);
        if (!is_string($json)) {
            throw new RuntimeException('No se pudo leer snapshot: ' . $filename);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Snapshot JSON inválido: ' . $filename);
        }

        return $decoded;
    }
}

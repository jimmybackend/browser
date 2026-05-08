<?php

declare(strict_types=1);

namespace Browser\Services;

use DateTimeImmutable;
use Throwable;

final class CrawlDomainPolicyService
{
    private ?string $lastWarning = null;

    public function __construct(private readonly string $filePath)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $data = $this->readPolicies();

        return $data['policies'];
    }

    public function pause(string $input, string $reason): array
    {
        $domain = $this->normalizeDomainInput($input);
        if ($domain === null) {
            throw new \RuntimeException('Dominio inválido. Usa dominio o URL HTTP/HTTPS válida.');
        }

        $data = $this->readPolicies();
        $data['policies'][$domain] = [
            'domain' => $domain,
            'paused' => true,
            'reason' => trim($reason),
            'paused_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
        $this->writePolicies($data['policies']);

        return $data['policies'][$domain];
    }

    public function resume(string $input): bool
    {
        $domain = $this->normalizeDomainInput($input);
        if ($domain === null) {
            throw new \RuntimeException('Dominio inválido.');
        }

        $data = $this->readPolicies();
        if (!isset($data['policies'][$domain])) {
            return false;
        }

        unset($data['policies'][$domain]);
        $this->writePolicies($data['policies']);

        return true;
    }

    public function isPaused(string $input): bool
    {
        $status = $this->status($input);

        return (bool) ($status['paused'] ?? false);
    }

    /** @return array{domain:string,paused:bool,reason:?string,paused_at:?string} */
    public function status(string $input): array
    {
        $domain = $this->normalizeDomainInput($input);
        if ($domain === null) {
            throw new \RuntimeException('Dominio inválido.');
        }

        $data = $this->readPolicies();
        $entry = $data['policies'][$domain] ?? null;

        return [
            'domain' => $domain,
            'paused' => (bool) ($entry['paused'] ?? false),
            'reason' => is_array($entry) ? (string) ($entry['reason'] ?? '') : null,
            'paused_at' => is_array($entry) ? (string) ($entry['paused_at'] ?? '') : null,
        ];
    }

    public function normalizeDomainInput(string $input): ?string
    {
        $value = strtolower(trim($input));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) === 1) {
            $parts = parse_url($value);
            if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
                return null;
            }

            $scheme = strtolower((string) $parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $value = (string) $parts['host'];
        } else {
            $parts = parse_url('http://' . $value);
            if ($parts === false || empty($parts['host'])) {
                return null;
            }
            $value = (string) $parts['host'];
        }

        $value = preg_replace('/^www\./', '', strtolower($value));
        if (!is_string($value) || $value === '' || str_contains($value, '/')) {
            return null;
        }

        return $value;
    }

    public function getLastWarning(): ?string
    {
        return $this->lastWarning;
    }

    /** @return array{policies:array<string,mixed>} */
    private function readPolicies(): array
    {
        $this->lastWarning = null;

        if (!is_file($this->filePath)) {
            return ['policies' => []];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || trim($content) === '') {
            return ['policies' => []];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->lastWarning = 'Archivo de política corrupto: ' . $exception->getMessage();
            return ['policies' => []];
        }

        if (!is_array($decoded)) {
            return ['policies' => []];
        }

        return ['policies' => $decoded];
    }

    /** @param array<string,mixed> $policies */
    private function writePolicies(array $policies): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $tmpPath = $this->filePath . '.tmp';
        $json = json_encode($policies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('No se pudo serializar política de dominios.');
        }

        file_put_contents($tmpPath, $json . PHP_EOL, LOCK_EX);
        @chmod($tmpPath, 0664);
        rename($tmpPath, $this->filePath);
        @chmod($this->filePath, 0664);
    }
}

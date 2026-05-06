<?php

declare(strict_types=1);

namespace Browser\Services;

final class RobotsTxtService
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $cache = [];

    public function isAllowed(string $url, string $userAgent = 'BrowserBot'): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $origin = strtolower($parts['scheme']) . '://' . strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        $rules = $this->cache[$origin] ??= $this->fetchRules($origin);

        return $this->matchAllowed($rules, strtolower($userAgent), $path);
    }

    private function fetchRules(string $origin): array
    {
        $content = @file_get_contents(rtrim($origin, '/') . '/robots.txt');
        if (!is_string($content) || $content === '') {
            return [];
        }

        $rules = [];
        $currentAgent = null;
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $currentAgent = strtolower($value);
                $rules[$currentAgent] ??= [];
                continue;
            }

            if (!in_array($field, ['allow', 'disallow'], true) || $currentAgent === null) {
                continue;
            }

            $rules[$currentAgent][$field][] = $value;
        }

        return $rules;
    }

    private function matchAllowed(array $rules, string $userAgent, string $path): bool
    {
        $agentRules = $rules[$userAgent] ?? $rules['*'] ?? [];
        $disallow = $agentRules['disallow'] ?? [];
        $allow = $agentRules['allow'] ?? [];

        $longestBlock = 0;
        foreach ($disallow as $rule) {
            if ($rule !== '' && str_starts_with($path, $rule)) {
                $longestBlock = max($longestBlock, strlen($rule));
            }
        }

        $longestAllow = 0;
        foreach ($allow as $rule) {
            if ($rule !== '' && str_starts_with($path, $rule)) {
                $longestAllow = max($longestAllow, strlen($rule));
            }
        }

        return $longestAllow >= $longestBlock;
    }
}

<?php

declare(strict_types=1);

namespace Browser\Console;

use Browser\Core\Database;
use Browser\Core\Env;
use Browser\Core\Session;
use Browser\Models\CrawlJob;
use Browser\Services\CrawlerService;
use Browser\Services\CrawlSeedJobEnqueuer;
use Browser\Services\RobotsTxtService;
use Browser\Services\RobotsTxtSitemapDiscoveryService;
use Browser\Services\SearchService;
use Browser\Services\SitemapDiscoveryService;
use PDO;
use RuntimeException;
use Throwable;

final class Kernel
{
    private const MAIN_TABLES = [
        'users',
        'roles',
        'user_roles',
        'user_preferences',
        'ternary_signal_definitions',
        'ternary_signal_events',
    ];

    public function handle(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        return match ($command) {
            'migrate' => $this->migrate(),
            'seed' => $this->seed(),
            'crawl:add' => $this->crawlAdd(),
            'crawl:queue' => $this->crawlQueue($argv),
            'crawl:queue-file' => $this->crawlQueueFile($argv),
            'crawl:sitemap' => $this->crawlSitemap($argv),
            'crawl:robots-sitemaps' => $this->crawlRobotsSitemaps($argv),
            'crawl:run' => $this->crawlRun($argv),
            'crawl:status' => $this->crawlStatus(),
            'crawl:errors' => $this->crawlErrors(),
            'index:status' => $this->indexStatus(),
            'doctor' => $this->doctor(),
            'auth:doctor' => $this->authDoctor(),
            'admin:create' => $this->adminCreate(),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknown($command),
        };
    }

    private function help(): int
    {
        $this->line('Browser CLI');
        $this->line('Uso: php bin/browser <comando>');
        $this->line('Comandos disponibles:');
        $this->line('  migrate       Ejecuta migraciones SQL pendientes');
        $this->line('  seed          Ejecuta seeders SQL');
        $this->line('  doctor        Diagnostica el entorno del servidor');
        $this->line('  auth:doctor   Diagnóstico seguro de autenticación/sesión');
        $this->line('  admin:create  Crea o promueve usuario admin');
        $this->line('  crawl:add     Crea un crawl job en estado queued');
        $this->line('  crawl:queue   Crea un crawl job queued (no interactivo)');
        $this->line('  crawl:queue-file Crea crawl jobs queued leyendo URLs de archivo');
        $this->line('  crawl:sitemap Crea crawl jobs queued leyendo sitemap XML');
        $this->line('  crawl:robots-sitemaps Crea crawl jobs queued desde robots.txt -> Sitemap');
        $this->line('  crawl:run     Ejecuta jobs queued de crawler');
        $this->line('  crawl:status  Muestra resumen de jobs crawler');
        $this->line('  crawl:errors  Diagnóstico de errores recientes del crawler');
        $this->line('  index:status  Diagnóstico de índice y crawler');

        return 0;
    }

    private function unknown(string $command): int
    {
        $this->line("Comando no reconocido: {$command}");
        $this->line('Ejecuta "php bin/browser help" para ver comandos disponibles.');

        return 1;
    }

    private function migrate(): int
    {
        Env::load(BASE_PATH);

        try {
            $pdo = Database::connection();
            $this->ensureSchemaMigrationsTable($pdo);

            $applied = $this->appliedMigrations($pdo);
            $files = $this->sqlFiles('database/migrations');

            if ($files === []) {
                $this->line('[WARN] No hay migraciones encontradas.');

                return 0;
            }

            foreach ($files as $file) {
                $migration = basename($file);
                if (isset($applied[$migration])) {
                    $this->line("[SKIP] {$migration} ya aplicada.");
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException("No se pudo leer la migración {$migration}.");
                }

                $this->line("[RUN] {$migration}");

                $pdo->beginTransaction();
                try {
                    $pdo->exec($sql);
                    $this->runPostMigrationAdjustments($pdo, $migration);
                    $record = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                    $record->execute(['migration' => $migration]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    throw $exception;
                }

                $this->line("[OK] {$migration}");
            }

            $this->line('[OK] Migraciones finalizadas.');
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] Error ejecutando migraciones.');
            return 1;
        }
    }

    private function seed(): int
    {
        Env::load(BASE_PATH);

        try {
            $pdo = Database::connection();
            $files = $this->sqlFiles('database/seeders');

            if ($files === []) {
                $this->line('[WARN] No hay seeders encontrados.');
                return 0;
            }

            foreach ($files as $file) {
                $name = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException("No se pudo leer el seeder {$name}.");
                }

                $this->line("[RUN] {$name}");
                $pdo->exec($sql);
                $this->line("[OK] {$name}");
            }

            $this->line('[OK] Seeders finalizados.');
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] Error ejecutando seeders.');
            return 1;
        }
    }

    private function doctor(): int
    {
        Env::load(BASE_PATH);

        $failed = false;

        $failed = !$this->check(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP version >= 8.3') || $failed;
        $failed = !$this->check(extension_loaded('pdo_mysql'), 'pdo_mysql installed') || $failed;

        $envExists = file_exists(BASE_PATH . '/.env');
        $failed = !$this->check($envExists, '.env exists') || $failed;

        $autoloadExists = file_exists(BASE_PATH . '/vendor/autoload.php');
        $failed = !$this->check($autoloadExists, 'vendor/autoload.php exists') || $failed;

        $storagePath = BASE_PATH . '/storage';
        if (is_dir($storagePath)) {
            $failed = !$this->check(is_writable($storagePath), 'storage writable') || $failed;
        } else {
            $this->line('[WARN] storage directory missing');
        }

        $appEnv = $_ENV['APP_ENV'] ?? 'undefined';
        $this->line('[OK] APP_ENV=' . $appEnv);

        $appDebug = strtolower((string) ($_ENV['APP_DEBUG'] ?? 'false'));
        if ($appEnv === 'production' && $appDebug === 'true') {
            $this->line('[WARN] APP_DEBUG=true in production');
        } else {
            $this->line('[OK] APP_DEBUG=' . $appDebug);
        }

        try {
            $pdo = Database::connection();
            $this->line('[OK] Database connection');
            foreach (self::MAIN_TABLES as $table) {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
                $stmt->execute(['table' => $table]);
                $exists = (bool) $stmt->fetchColumn();
                if ($exists) {
                    $this->line("[OK] table {$table}");
                } else {
                    $this->line("[FAIL] table {$table} missing");
                    $failed = true;
                }
            }
        } catch (Throwable $exception) {
            $this->line('[FAIL] Database connection');
            $failed = true;
        }

        return $failed ? 1 : 0;
    }

    private function crawlAdd(): int
    {
        Env::load(BASE_PATH);
        $seedUrlInput = trim((string) readline('URL inicial: '));
        $maxDepthInput = trim((string) readline('max_depth [2]: '));
        $maxPagesInput = trim((string) readline('max_pages [25]: '));
        if ($seedUrlInput === '') {
            $this->line('[FAIL] URL inicial requerida.');
            return 1;
        }

        $seedUrl = $this->normalizeSafeSeedUrl($seedUrlInput);
        if ($seedUrl === null) {
            $this->line('[FAIL] URL inicial inválida o bloqueada.');
            return 1;
        }

        if (!$this->isIntegerInRange($maxDepthInput, 0, 5, true)) {
            $this->line('[FAIL] max_depth debe ser entero entre 0 y 5.');
            return 1;
        }

        if (!$this->isIntegerInRange($maxPagesInput, 1, 100, true)) {
            $this->line('[FAIL] max_pages debe ser entero entre 1 y 100.');
            return 1;
        }

        $maxDepth = (int) ($maxDepthInput !== '' ? $maxDepthInput : '2');
        $maxPages = (int) ($maxPagesInput !== '' ? $maxPagesInput : '25');

        try {
            $jobId = CrawlJob::create($seedUrl, $maxDepth, $maxPages);
            $this->line("[OK] Job creado con ID {$jobId}.");
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo crear el job.');
            return 1;
        }
    }

    private function crawlRun(array $argv): int
    {
        Env::load(BASE_PATH);
        $limit = null;
        $jobId = null;
        foreach (array_slice($argv, 2) as $arg) {
            if (str_starts_with($arg, '--limit=')) {
                $limit = max(1, (int) substr($arg, 8));
            }
            if (str_starts_with($arg, '--job=')) {
                $jobId = max(1, (int) substr($arg, 6));
            }
        }

        $jobs = $jobId !== null ? array_filter([CrawlJob::find($jobId)]) : CrawlJob::queued($limit);
        $crawler = new CrawlerService(new RobotsTxtService());

        foreach ($jobs as $job) {
            if (($job['status'] ?? '') !== 'queued') {
                continue;
            }
            $id = (int) $job['id'];
            CrawlJob::markRunning($id);
            $this->line("[RUN] crawl job #{$id}");
            try {
                $stats = $crawler->runJob($job);
                CrawlJob::markFinished($id, 'completed', (int) $stats['pages_found'], (int) $stats['pages_indexed']);
                $skips = (int) ($stats['rate_limited_skips'] ?? 0);
                if ($skips > 0) {
                    $cooldown = (int) ($stats['rate_limit_cooldown_seconds'] ?? 0);
                    $this->line("[INFO] crawl job #{$id} rate-limit: {$skips} URL(s) diferidas por dominio (cooldown {$cooldown}s).");
                }
                $this->line("[OK] crawl job #{$id} completed");
            } catch (Throwable $exception) {
                CrawlJob::markFinished($id, 'failed', 0, 0, mb_substr($exception->getMessage(), 0, 500));
                $this->line("[FAIL] crawl job #{$id} failed");
            }
        }

        return 0;
    }

    private function crawlQueue(array $argv): int
    {
        Env::load(BASE_PATH);

        $urlInput = $this->readOptionValue($argv, 'url');
        if ($urlInput === null || trim($urlInput) === '') {
            $this->line('[FAIL] --url es requerido.');
            return 1;
        }

        $maxDepth = $this->parseIntOption($argv, 'max-depth', 1, 0, 5);
        if ($maxDepth === null) {
            $this->line('[FAIL] --max-depth debe ser entero entre 0 y 5.');
            return 1;
        }

        $maxPages = $this->parseIntOption($argv, 'max-pages', 10, 1, 100);
        if ($maxPages === null) {
            $this->line('[FAIL] --max-pages debe ser entero entre 1 y 100.');
            return 1;
        }

        $url = $this->normalizeSafeSeedUrl($urlInput);
        if ($url === null) {
            $this->line('[FAIL] URL inválida o bloqueada.');
            return 1;
        }

        try {
            if (CrawlJob::hasPendingForSeedUrl($url)) {
                $this->line('[SKIP] Job duplicado: ' . $url);
                $this->line('[OK] Resumen: jobs creados=0, URLs inválidas=0, URLs duplicadas=1, errores controlados=0');
                return 0;
            }

            $jobId = CrawlJob::create($url, $maxDepth, $maxPages);
            $this->line("[OK] Job creado con ID {$jobId} para URL {$url}");
            $this->line('[OK] Resumen: jobs creados=1, URLs inválidas=0, URLs duplicadas=0, errores controlados=0');
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo crear el job.');
            return 1;
        }
    }

    private function crawlQueueFile(array $argv): int
    {
        Env::load(BASE_PATH);

        $fileInput = $this->readOptionValue($argv, 'file');
        if ($fileInput === null || trim($fileInput) === '') {
            $this->line('[FAIL] --file es requerido.');
            return 1;
        }

        $filePath = realpath(BASE_PATH . '/' . ltrim($fileInput, '/')) ?: $fileInput;
        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->line('[FAIL] Archivo no encontrado o sin permisos de lectura.');
            return 1;
        }

        $maxDepth = $this->parseIntOption($argv, 'max-depth', 1, 0, 5);
        if ($maxDepth === null) {
            $this->line('[FAIL] --max-depth debe ser entero entre 0 y 5.');
            return 1;
        }

        $maxPages = $this->parseIntOption($argv, 'max-pages', 10, 1, 100);
        if ($maxPages === null) {
            $this->line('[FAIL] --max-pages debe ser entero entre 1 y 100.');
            return 1;
        }

        $limit = $this->parseIntOption($argv, 'limit', 20, 1, 1000);
        if ($limit === null) {
            $this->line('[FAIL] --limit debe ser entero entre 1 y 1000.');
            return 1;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $this->line('[FAIL] No se pudo leer el archivo.');
            return 1;
        }

        $candidates = [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || str_starts_with($candidate, '#')) {
                continue;
            }
            $candidates[] = $candidate;
        }

        $enqueuer = new CrawlSeedJobEnqueuer();
        $result = $enqueuer->enqueueMany(
            $candidates,
            $maxDepth,
            $maxPages,
            $limit,
            fn (string $candidate): ?string => $this->normalizeSafeSeedUrl($candidate),
            fn (string $url): bool => CrawlJob::hasPendingForSeedUrl($url),
            fn (string $url, int $depth, int $pages): int => CrawlJob::create($url, $depth, $pages),
            function (string $message): void {
                $this->line($message);
            }
        );

        $this->line('[OK] Resumen: jobs creados=' . $result['created']
            . ', URLs inválidas=' . $result['invalid']
            . ', URLs duplicadas=' . $result['duplicates']
            . ', errores controlados=' . $result['errors']
            . ', archivo leído=' . $filePath);
        return 0;
    }

    private function crawlSitemap(array $argv): int
    {
        Env::load(BASE_PATH);

        $sitemapInput = $this->readOptionValue($argv, 'url');
        if ($sitemapInput === null || trim($sitemapInput) === '') {
            $this->line('[FAIL] --url es requerido.');
            return 1;
        }

        $sitemapUrl = $this->normalizeSafeSeedUrl($sitemapInput);
        if ($sitemapUrl === null) {
            $this->line('[FAIL] URL de sitemap inválida o bloqueada.');
            return 1;
        }

        $maxDepth = $this->parseIntOption($argv, 'max-depth', 1, 0, 5);
        $maxPages = $this->parseIntOption($argv, 'max-pages', 10, 1, 100);
        $limit = $this->parseIntOption($argv, 'limit', 50, 1, 1000);
        if ($maxDepth === null || $maxPages === null || $limit === null) {
            $this->line('[FAIL] Opciones inválidas. --max-depth(0-5), --max-pages(1-100), --limit(1-1000).');
            return 1;
        }

        $service = new SitemapDiscoveryService();

        try {
            $xmlBody = $service->fetchSitemapXml($sitemapUrl);
            $parsed = $service->parseSitemapUrls($xmlBody);

            $result = $service->createJobsFromParsedSitemap(
                $parsed,
                $maxDepth,
                $maxPages,
                $limit,
                fn (string $candidate): ?string => $this->normalizeSafeSeedUrl($candidate),
                function (string $url, int $depth, int $pages): int {
                    if (CrawlJob::hasPendingForSeedUrl($url)) {
                        throw new RuntimeException('[DUPLICATE] ' . $url);
                    }

                    return CrawlJob::create($url, $depth, $pages);
                },
                function (string $message): void {
                    $this->line($message);
                }
            );

            $this->line('[OK] Sitemap leído: ' . $sitemapUrl);
            $this->line('[OK] Tipo sitemap: ' . $parsed['type']);
            $this->line('[OK] URLs encontradas: ' . count($parsed['urls']));
            $this->line('[OK] Jobs creados: ' . $result['created']);
            $this->line('[OK] URLs inválidas: ' . ($result['invalid'] ?? 0));
            $this->line('[OK] URLs duplicadas: ' . ($result['duplicates'] ?? 0));
            $this->line('[OK] errores controlados: ' . ($result['errors'] ?? 0));
            $this->line('[OK] Límite aplicado: ' . $limit);
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo procesar sitemap: ' . $exception->getMessage());
            return 1;
        }
    }

    private function crawlRobotsSitemaps(array $argv): int
    {
        Env::load(BASE_PATH);

        $urlInput = $this->readOptionValue($argv, 'url');
        if ($urlInput === null || trim($urlInput) === '') {
            $this->line('[FAIL] --url es requerido.');
            return 1;
        }

        $maxDepth = $this->parseIntOption($argv, 'max-depth', 1, 0, 5);
        $maxPages = $this->parseIntOption($argv, 'max-pages', 10, 1, 100);
        $limit = $this->parseIntOption($argv, 'limit', 50, 1, 1000);
        if ($maxDepth === null || $maxPages === null || $limit === null) {
            $this->line('[FAIL] Opciones inválidas. --max-depth(0-5), --max-pages(1-100), --limit(1-1000).');
            return 1;
        }

        $robotsService = new RobotsTxtSitemapDiscoveryService();
        $sitemapService = new SitemapDiscoveryService();
        $robotsUrl = $robotsService->normalizeRobotsTxtUrl($urlInput);
        if ($robotsUrl === null) {
            $this->line('[FAIL] --url debe ser http/https válido.');
            return 1;
        }

        $safeRobotsUrl = $this->normalizeSafeSeedUrl($robotsUrl);
        if ($safeRobotsUrl === null) {
            $this->line('[FAIL] URL de robots.txt inválida o bloqueada.');
            return 1;
        }

        try {
            $robotsResponse = $robotsService->fetchRobotsTxt($safeRobotsUrl);
            if ($robotsResponse['status'] === 404) {
                $this->line('[OK] robots.txt no existe (404). No fatal, sin jobs creados.');
                return 0;
            }
            if ($robotsResponse['status'] < 200 || $robotsResponse['status'] >= 400) {
                $this->line('[OK] robots.txt no disponible (HTTP ' . $robotsResponse['status'] . '). No fatal, sin jobs creados.');
                return 0;
            }

            $parsedRobots = $robotsService->extractSitemaps($robotsResponse['body']);
            $sitemaps = $parsedRobots['sitemaps'];
            if ($sitemaps === []) {
                $this->line('[OK] robots.txt sin líneas Sitemap:. No hay jobs para sembrar.');
                return 0;
            }

            $created = 0;
            $skipped = 0;
            $errors = 0;
            foreach ($sitemaps as $sitemapCandidate) {
                if ($created >= $limit) {
                    break;
                }

                $safeSitemapUrl = $this->normalizeSafeSeedUrl($sitemapCandidate);
                if ($safeSitemapUrl === null) {
                    $skipped++;
                    continue;
                }

                try {
                    $xmlBody = $sitemapService->fetchSitemapXml($safeSitemapUrl);
                    $parsed = $sitemapService->parseSitemapUrls($xmlBody);
                    $remaining = max(0, $limit - $created);
                    $result = $sitemapService->createJobsFromParsedSitemap(
                        $parsed,
                        $maxDepth,
                        $maxPages,
                        $remaining,
                        fn (string $candidate): ?string => $this->normalizeSafeSeedUrl($candidate),
                        function (string $url, int $depth, int $pages): int {
                            if (CrawlJob::hasPendingForSeedUrl($url)) {
                                throw new RuntimeException('[DUPLICATE] ' . $url);
                            }

                            return CrawlJob::create($url, $depth, $pages);
                        },
                        function (string $message): void {
                            $this->line($message);
                        }
                    );
                    $created += (int) $result['created'];
                    $skipped += (int) ($result['invalid'] ?? 0);
                    $skipped += (int) ($result['duplicates'] ?? 0);
                } catch (Throwable $exception) {
                    $errors++;
                }
            }

            $this->line('[OK] robots URL leída: ' . $safeRobotsUrl);
            $this->line('[OK] sitemaps encontrados: ' . count($sitemaps));
            $this->line('[OK] jobs creados: ' . $created);
            $this->line('[OK] URLs omitidas: ' . $skipped);
            $this->line('[OK] errores controlados: ' . $errors);
            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo procesar robots.txt: ' . $exception->getMessage());
            return 1;
        }
    }

    private function crawlStatus(): int
    {
        Env::load(BASE_PATH);
        $summary = CrawlJob::statusSummary();
        foreach ($summary as $row) {
            $this->line(sprintf('%s: %d (último: %s)', $row['status'], $row['total'], $row['last_created_at'] ?? '-'));
        }
        if ($summary === []) {
            $this->line('Sin jobs.');
        }
        return 0;
    }


    private function crawlErrors(): int
    {
        Env::load(BASE_PATH);

        try {
            $pdo = Database::connection();

            $failedUrlsStmt = $pdo->prepare('SELECT url, http_status, error_message, updated_at FROM crawl_urls WHERE status = :status ORDER BY updated_at DESC, id DESC LIMIT 20');
            $failedUrlsStmt->execute(['status' => 'failed']);
            $failedUrls = $failedUrlsStmt->fetchAll(PDO::FETCH_ASSOC);

            $failedJobsStmt = $pdo->prepare('SELECT id, seed_url, error_message, created_at, finished_at FROM crawl_jobs WHERE status = :status ORDER BY created_at DESC, id DESC LIMIT 10');
            $failedJobsStmt->execute(['status' => 'failed']);
            $failedJobs = $failedJobsStmt->fetchAll(PDO::FETCH_ASSOC);

            $summary = [
                'SSL' => 0,
                'HTTP' => 0,
                'malformed URL' => 0,
                'mb_substr/null' => 0,
                'robots/disallowed' => 0,
                'otros' => 0,
            ];

            foreach ($failedUrls as $row) {
                $summary[$this->classifyCrawlerError((string) ($row['error_message'] ?? ''), $row['http_status'] ?? null)]++;
            }

            foreach ($failedJobs as $row) {
                $summary[$this->classifyCrawlerError((string) ($row['error_message'] ?? ''), null)]++;
            }

            $this->line('=== Crawl Errors ===');
            $this->line('');
            $this->line('=== Últimos 20 crawl_urls failed ===');

            if ($failedUrls === []) {
                $this->line('Sin errores recientes en crawl_urls.');
            } else {
                foreach ($failedUrls as $row) {
                    $this->line(sprintf(
                        '  url=%s | http_status=%s | error=%s | updated_at=%s',
                        (string) ($row['url'] ?? '-'),
                        (string) ($row['http_status'] ?? '-'),
                        (string) ($row['error_message'] ?? '-'),
                        (string) ($row['updated_at'] ?? '-')
                    ));
                }
            }

            $this->line('');
            $this->line('=== Últimos 10 crawl_jobs failed ===');

            if ($failedJobs === []) {
                $this->line('Sin errores recientes en crawl_jobs.');
            } else {
                foreach ($failedJobs as $job) {
                    $this->line(sprintf(
                        '  #%d | seed_url=%s | error=%s | created_at=%s | finished_at=%s',
                        (int) ($job['id'] ?? 0),
                        (string) ($job['seed_url'] ?? '-'),
                        (string) ($job['error_message'] ?? '-'),
                        (string) ($job['created_at'] ?? '-'),
                        (string) ($job['finished_at'] ?? '-')
                    ));
                }
            }

            $this->line('');
            $this->line('=== Resumen por tipo aproximado ===');
            foreach ($summary as $label => $total) {
                $this->line(sprintf('  %s: %d', $label, $total));
            }

            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo obtener crawl:errors. Verifica conexión a base de datos.');
            return 1;
        }
    }

    private function classifyCrawlerError(string $errorMessage, mixed $httpStatus): string
    {
        $normalized = strtolower(trim($errorMessage));

        if ($httpStatus !== null || str_contains($normalized, 'http_status')) {
            return 'HTTP';
        }

        if (str_contains($normalized, 'ssl') || str_contains($normalized, 'certificate')) {
            return 'SSL';
        }

        if (str_contains($normalized, 'malformed') || str_contains($normalized, 'url rejected') || str_contains($normalized, 'invalid url')) {
            return 'malformed URL';
        }

        if (str_contains($normalized, 'mb_substr') || str_contains($normalized, 'null')) {
            return 'mb_substr/null';
        }

        if (str_contains($normalized, 'robots') || str_contains($normalized, 'disallow')) {
            return 'robots/disallowed';
        }

        return 'otros';
    }

    private function indexStatus(): int
    {
        Env::load(BASE_PATH);

        try {
            $pdo = Database::connection();

            $totalPagesStmt = $pdo->prepare('SELECT COUNT(*) FROM indexed_pages');
            $totalPagesStmt->execute();
            $totalPages = (int) $totalPagesStmt->fetchColumn();

            $lastIndexedStmt = $pdo->prepare('SELECT id, url, status, created_at, updated_at, last_crawled_at FROM indexed_pages ORDER BY created_at DESC LIMIT 1');
            $lastIndexedStmt->execute();
            $lastIndexed = $lastIndexedStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $jobsByStatusStmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM crawl_jobs GROUP BY status ORDER BY status ASC');
            $jobsByStatusStmt->execute();
            $jobsByStatus = $jobsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);

            $urlsByStatusStmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM crawl_urls GROUP BY status ORDER BY status ASC');
            $urlsByStatusStmt->execute();
            $urlsByStatus = $urlsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);

            $failedJobsStmt = $pdo->prepare('SELECT id, seed_url, error_message, created_at, finished_at FROM crawl_jobs WHERE status = :status ORDER BY id DESC LIMIT 10');
            $failedJobsStmt->execute(['status' => 'failed']);
            $failedJobs = $failedJobsStmt->fetchAll(PDO::FETCH_ASSOC);

            $failedUrlsStmt = $pdo->prepare('SELECT url, error_message, http_status, updated_at FROM crawl_urls WHERE status = :status ORDER BY updated_at DESC, id DESC LIMIT 10');
            $failedUrlsStmt->execute(['status' => 'failed']);
            $failedUrls = $failedUrlsStmt->fetchAll(PDO::FETCH_ASSOC);

            $this->line('=== Index Status ===');
            $this->line('Total indexed_pages: ' . $totalPages);

            if ($lastIndexed === null) {
                $this->line('Última página indexada: Sin páginas indexadas');
            } else {
                $this->line(sprintf(
                    'Última página indexada: #%d | status=%s | created_at=%s | updated_at=%s | last_crawled_at=%s | url=%s',
                    (int) ($lastIndexed['id'] ?? 0),
                    (string) ($lastIndexed['status'] ?? '-'),
                    (string) ($lastIndexed['created_at'] ?? '-'),
                    (string) ($lastIndexed['updated_at'] ?? '-'),
                    (string) ($lastIndexed['last_crawled_at'] ?? '-'),
                    (string) ($lastIndexed['url'] ?? '-')
                ));
            }

            $this->line('');
            $this->line('=== Crawl Jobs por status ===');
            if ($jobsByStatus === []) {
                $this->line('Sin crawl_jobs');
            } else {
                foreach ($jobsByStatus as $row) {
                    $this->line(sprintf('  %s: %d', (string) $row['status'], (int) $row['total']));
                }
            }

            $this->line('');
            $this->line('=== Crawl URLs por status ===');
            if ($urlsByStatus === []) {
                $this->line('Sin crawl_urls');
            } else {
                foreach ($urlsByStatus as $row) {
                    $this->line(sprintf('  %s: %d', (string) $row['status'], (int) $row['total']));
                }
            }

            $this->line('');
            $this->line('=== Últimos 10 crawl_jobs fallidos ===');
            if ($failedJobs === []) {
                $this->line('Sin errores recientes');
            } else {
                foreach ($failedJobs as $job) {
                    $this->line(sprintf(
                        '  #%d | seed_url=%s | error=%s | created_at=%s | finished_at=%s',
                        (int) ($job['id'] ?? 0),
                        (string) ($job['seed_url'] ?? '-'),
                        (string) ($job['error_message'] ?? '-'),
                        (string) ($job['created_at'] ?? '-'),
                        (string) ($job['finished_at'] ?? '-')
                    ));
                }
            }

            $this->line('');
            $this->line('=== Últimos 10 crawl_urls fallidos ===');
            if ($failedUrls === []) {
                $this->line('Sin errores recientes');
            } else {
                foreach ($failedUrls as $row) {
                    $this->line(sprintf(
                        '  url=%s | http_status=%s | error=%s | updated_at=%s',
                        (string) ($row['url'] ?? '-'),
                        (string) ($row['http_status'] ?? '-'),
                        (string) ($row['error_message'] ?? '-'),
                        (string) ($row['updated_at'] ?? '-')
                    ));
                }
            }

            return 0;
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo obtener index:status. Verifica conexión a base de datos.');
            return 1;
        }
    }

    private function adminCreate(): int
    {
        Env::load(BASE_PATH);

        try {
            $pdo = Database::connection();

            $email = strtolower(trim((string) readline('Email: ')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->line('[FAIL] Email inválido.');
                return 1;
            }

            $user = $this->findUserByEmail($pdo, $email);

            $pdo->beginTransaction();
            try {
                $adminRoleId = $this->ensureAdminRole($pdo);

                if ($user !== null) {
                    $this->assignRole($pdo, (int) $user['id'], $adminRoleId);
                    $pdo->commit();
                    $this->line('[OK] Usuario existente promovido a admin.');

                    return 0;
                }

                $username = trim((string) readline('Username: '));
                if ($username === '') {
                    throw new RuntimeException('Username requerido.');
                }

                $password = (string) readline('Password (mínimo 12): ');
                $confirmation = (string) readline('Password confirmation: ');

                if (mb_strlen($password) < 12) {
                    throw new RuntimeException('La contraseña debe tener mínimo 12 caracteres.');
                }

                if (!hash_equals($password, $confirmation)) {
                    throw new RuntimeException('Las contraseñas no coinciden.');
                }

                $userId = $this->createAdminUser($pdo, $username, $email, $password);
                $this->assignRole($pdo, $userId, $adminRoleId);
                $pdo->commit();

                $this->line('[OK] Usuario admin creado correctamente.');
                return 0;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $this->line('[FAIL] No se pudo crear/promover admin.');
                return 1;
            }
        } catch (Throwable $exception) {
            $this->line('[FAIL] No se pudo conectar a la base de datos.');
            return 1;
        }
    }

    private function authDoctor(): int
    {
        Env::load(BASE_PATH);

        $appEnv = (string) ($_ENV['APP_ENV'] ?? 'undefined');
        $appUrl = (string) ($_ENV['APP_URL'] ?? 'undefined');
        $sessionName = (string) ($_ENV['SESSION_NAME'] ?? 'BROWSER_SESSION');
        $savePath = (string) session_save_path();
        $cookieSecure = $appEnv === 'production' ? (Session::isHttpsRequest() ? 'true' : 'false') : 'false';

        $this->line('[OK] APP_ENV=' . $appEnv);
        $this->line('[OK] APP_URL=' . $appUrl);
        $this->line('[OK] SESSION_NAME=' . $sessionName);
        $this->line('[OK] HTTPS detected=' . (Session::isHttpsRequest() ? 'true' : 'false'));
        $this->line('[OK] session.save_path=' . ($savePath !== '' ? $savePath : '(default)'));
        $this->line('[OK] session.save_path writable=' . (is_writable($savePath !== '' ? $savePath : sys_get_temp_dir()) ? 'true' : 'false'));
        $this->line('[OK] cookie.secure=' . $cookieSecure);
        $this->line('[OK] cookie.httponly=true');
        $this->line('[OK] cookie.samesite=Lax');

        if ($appEnv === 'production' && !Session::isHttpsRequest()) {
            $this->line('[WARN] Producción sin HTTPS detectado: la cookie de sesión no se marcará como Secure en esta solicitud.');
        }

        return 0;
    }

    private function ensureSchemaMigrationsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function appliedMigrations(PDO $pdo): array
    {
        $statement = $pdo->query('SELECT migration FROM schema_migrations');

        return array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
    }

    private function sqlFiles(string $relativeDirectory): array
    {
        $baseDirectory = realpath(BASE_PATH . '/' . $relativeDirectory);
        if ($baseDirectory === false || !is_dir($baseDirectory)) {
            return [];
        }

        $files = glob($baseDirectory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $safeFiles = [];
        foreach ($files as $file) {
            $realFile = realpath($file);
            if ($realFile === false) {
                continue;
            }

            if (!str_starts_with($realFile, $baseDirectory . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (!is_file($realFile) || pathinfo($realFile, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }

            $safeFiles[] = $realFile;
        }

        return $safeFiles;
    }

    private function findUserByEmail(PDO $pdo, string $email): ?array
    {
        $statement = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function ensureAdminRole(PDO $pdo): int
    {
        $find = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $find->execute(['name' => 'admin']);
        $roleId = $find->fetchColumn();

        if ($roleId !== false) {
            return (int) $roleId;
        }

        $create = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :description)');
        $create->execute([
            'name' => 'admin',
            'description' => 'Administrador del sistema',
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function createAdminUser(PDO $pdo, string $username, string $email, string $plainPassword): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO users (uuid, username, email, password_hash, display_name, status)
             VALUES (:uuid, :username, :email, :password_hash, :display_name, :status)'
        );

        $statement->execute([
            'uuid' => $this->uuid(),
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'display_name' => $username,
            'status' => 'active',
        ]);

        $userId = (int) $pdo->lastInsertId();

        $pref = $pdo->prepare(
            'INSERT INTO user_preferences (user_id, search_history_enabled, email_notifications_enabled, theme, language, timezone)
             VALUES (:user_id, 0, 1, :theme, :language, :timezone)'
        );
        $pref->execute([
            'user_id' => $userId,
            'theme' => 'system',
            'language' => 'es',
            'timezone' => 'America/Mexico_City',
        ]);

        return $userId;
    }

    private function assignRole(PDO $pdo, int $userId, int $roleId): void
    {
        $statement = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        $statement->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function check(bool $condition, string $label): bool
    {
        $this->line(($condition ? '[OK] ' : '[FAIL] ') . $label);

        return $condition;
    }


    private function runPostMigrationAdjustments(PDO $pdo, string $migration): void
    {
        if ($migration !== '004_crawl_urls.sql') {
            return;
        }

        if ($this->columnExists($pdo, 'crawl_jobs', 'max_pages')) {
            return;
        }

        $pdo->exec('ALTER TABLE crawl_jobs ADD COLUMN max_pages INT UNSIGNED NOT NULL DEFAULT 25 AFTER max_depth');
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (bool) $statement->fetchColumn();
    }

    private function isIntegerInRange(string $value, int $min, int $max, bool $allowEmptyAsDefault): bool
    {
        if ($value === '') {
            return $allowEmptyAsDefault;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }

        $intValue = (int) $value;

        return $intValue >= $min && $intValue <= $max;
    }

    private function normalizeSafeSeedUrl(string $seedUrl): ?string
    {
        $normalized = (new SearchService())->resolveNavigableUrl($seedUrl);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        if ($parts === false) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $normalized;
    }

    private function readOptionValue(array $argv, string $name): ?string
    {
        $prefix = '--' . $name . '=';
        foreach (array_slice($argv, 2) as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return null;
    }

    private function parseIntOption(array $argv, string $name, int $default, int $min, int $max): ?int
    {
        $rawValue = $this->readOptionValue($argv, $name);
        if ($rawValue === null || $rawValue === '') {
            return $default;
        }

        if (!$this->isIntegerInRange($rawValue, $min, $max, false)) {
            return null;
        }

        return (int) $rawValue;
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

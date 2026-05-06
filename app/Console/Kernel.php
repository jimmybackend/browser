<?php

declare(strict_types=1);

namespace Browser\Console;

use Browser\Core\Database;
use Browser\Core\Env;
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
            'doctor' => $this->doctor(),
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
        $this->line('  admin:create  Crea o promueve usuario admin');

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

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use DateTimeImmutable;

final class UserSession
{
    public static function create(int $userId, string $sessionId, string $ipAddress, string $userAgent): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_sessions (user_id, session_token_hash, ip_address, user_agent, expires_at)
             VALUES (:user_id, :session_token_hash, :ip_address, :user_agent, :expires_at)'
        );

        $statement->execute([
            'user_id' => $userId,
            'session_token_hash' => self::hashSessionId($sessionId),
            'ip_address' => self::ipToBinaryOrNull($ipAddress),
            'user_agent' => self::sanitizeUserAgent($userAgent),
            'expires_at' => self::defaultExpiration(),
        ]);
    }

    public static function revokeBySessionId(string $sessionId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE session_token_hash = :session_token_hash
               AND revoked_at IS NULL'
        );

        $statement->execute([
            'session_token_hash' => self::hashSessionId($sessionId),
        ]);
    }

    public static function isActive(string $sessionId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM user_sessions
             WHERE session_token_hash = :session_token_hash
               AND revoked_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );

        $statement->execute([
            'session_token_hash' => self::hashSessionId($sessionId),
        ]);

        return (bool) $statement->fetchColumn();
    }

    public static function hashSessionId(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private static function ipToBinaryOrNull(string $ipAddress): ?string
    {
        $binaryIp = inet_pton($ipAddress);

        return $binaryIp === false ? null : $binaryIp;
    }

    private static function sanitizeUserAgent(string $userAgent): string
    {
        return substr($userAgent, 0, 500);
    }

    private static function defaultExpiration(): string
    {
        return (new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s');
    }
}

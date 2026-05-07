<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class AuditLog
{
    public static function record(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $sanitizedMetadata = self::sanitizeMetadata($metadata);
        $metadataJson = $sanitizedMetadata === []
            ? null
            : json_encode($sanitizedMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($metadataJson === false) {
            $metadataJson = null;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata)
             VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata)'
        );

        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => self::ipToBinaryOrNull($ipAddress),
            'user_agent' => self::sanitizeUserAgent($userAgent),
            'metadata' => $metadataJson,
        ]);
    }

    public static function listRecent(int $limit = 100, array $filters = []): array
    {
        $safeLimit = max(1, min($limit, 200));

        $conditions = [];
        $params = [];

        if (isset($filters['action']) && is_string($filters['action']) && trim($filters['action']) !== '') {
            $conditions[] = 'action = :action';
            $params['action'] = trim($filters['action']);
        }

        if (isset($filters['user_id']) && is_numeric($filters['user_id']) && (int) $filters['user_id'] > 0) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && self::isValidDate($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (isset($filters['date_to']) && is_string($filters['date_to']) && self::isValidDate($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $where = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $sql = 'SELECT id, user_id, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at
                FROM audit_logs
                ' . $where . '
                ORDER BY created_at DESC
                LIMIT ' . $safeLimit;

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['ip_address'] = self::decodeIpAddress($row['ip_address'] ?? null);
            $row['metadata_display'] = self::sanitizeMetadataForDisplay($row['metadata'] ?? null);
        }

        return $rows;
    }

    public static function countByFilters(array $filters = []): int
    {
        $conditions = [];
        $params = [];

        if (isset($filters['action']) && is_string($filters['action']) && trim($filters['action']) !== '') {
            $conditions[] = 'action = :action';
            $params['action'] = trim($filters['action']);
        }

        if (isset($filters['user_id']) && is_numeric($filters['user_id']) && (int) $filters['user_id'] > 0) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && self::isValidDate($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (isset($filters['date_to']) && is_string($filters['date_to']) && self::isValidDate($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $where = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM audit_logs ' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public static function decodeIpAddress(?string $binaryIp): ?string
    {
        if ($binaryIp === null || $binaryIp === '') {
            return null;
        }

        $decoded = inet_ntop($binaryIp);

        return $decoded === false ? null : $decoded;
    }

    public static function sanitizeMetadataForDisplay(?string $metadataJson): array|string|null
    {
        if ($metadataJson === null || $metadataJson === '') {
            return null;
        }

        $decoded = json_decode($metadataJson, true);

        if (!is_array($decoded)) {
            return '[metadata inválida]';
        }

        return self::sanitizeMetadata($decoded);
    }

    public static function sanitizeMetadata(array $metadata): array
    {
        $blockedKeys = [
            'password',
            'password_confirmation',
            '_csrf_token',
            'csrf_token',
            'session_id',
            'session_token_hash',
        ];

        foreach ($blockedKeys as $blockedKey) {
            unset($metadata[$blockedKey]);
        }

        return $metadata;
    }

    public static function encodeMetadata(array $metadata): ?string
    {
        $sanitizedMetadata = self::sanitizeMetadata($metadata);

        if ($sanitizedMetadata === []) {
            return null;
        }

        $json = json_encode($sanitizedMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    private static function ipToBinaryOrNull(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        $binaryIp = inet_pton($ipAddress);

        return $binaryIp === false ? null : $binaryIp;
    }

    private static function sanitizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return substr($userAgent, 0, 500);
    }

    private static function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;

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
}

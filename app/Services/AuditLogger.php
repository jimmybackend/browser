<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\Database;

final class AuditLogger
{
    public function log(?int $userId, string $action, ?string $entityType = null, ?string $entityId = null, array $metadata = []): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :metadata, NOW())'
        );

        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}

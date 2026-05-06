<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class UserPreference
{
    public static function findByUserId(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT user_id, search_history_enabled, email_notifications_enabled, theme, language, timezone
             FROM user_preferences
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);

        $preferences = $statement->fetch(PDO::FETCH_ASSOC);

        return $preferences ?: null;
    }

    public static function createDefault(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_preferences (user_id, search_history_enabled, email_notifications_enabled, theme, language, timezone)
             VALUES (:user_id, 0, 1, :theme, :language, :timezone)'
        );

        $statement->execute([
            'user_id' => $userId,
            'theme' => 'system',
            'language' => 'es',
            'timezone' => 'America/Mexico_City',
        ]);
    }

    public static function updateForUser(int $userId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE user_preferences
             SET search_history_enabled = :search_history_enabled,
                 email_notifications_enabled = :email_notifications_enabled,
                 theme = :theme,
                 language = :language,
                 timezone = :timezone
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'user_id' => $userId,
            'search_history_enabled' => (int) ($data['search_history_enabled'] ?? 0),
            'email_notifications_enabled' => (int) ($data['email_notifications_enabled'] ?? 1),
            'theme' => (string) $data['theme'],
            'language' => (string) $data['language'],
            'timezone' => (string) $data['timezone'],
        ]);
    }
}

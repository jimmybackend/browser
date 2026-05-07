<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecuritySessionsFeatureTest extends TestCase
{
    public function testSecurityControllerAndViewExist(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/app/Controllers/SecurityController.php');
        $this->assertFileExists(dirname(__DIR__) . '/app/Views/security/sessions.php');
    }

    public function testUserSessionIncludesNewManagementMethods(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Models/UserSession.php');

        $this->assertNotFalse($content);
        $this->assertStringContainsString('public static function listForUser(int $userId): array', $content);
        $this->assertStringContainsString('public static function revokeForUserById(int $userId, int $sessionRecordId): void', $content);
        $this->assertStringContainsString('public static function revokeOtherSessions(int $userId, string $currentSessionId): void', $content);
    }

    public function testSessionsViewDoesNotRenderSessionTokenHashLabel(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Views/security/sessions.php');

        $this->assertNotFalse($content);
        $this->assertStringNotContainsString('session_token_hash', strtolower($content));
    }
}

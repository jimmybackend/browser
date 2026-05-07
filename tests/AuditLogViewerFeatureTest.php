<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuditLogViewerFeatureTest extends TestCase
{
    public function testAuditLogControllerAndViewExist(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/app/Controllers/AuditLogController.php');
        $this->assertFileExists(dirname(__DIR__) . '/app/Views/audit/index.php');
    }

    public function testAuditLogModelHasListRecentMethod(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Models/AuditLog.php');

        $this->assertNotFalse($content);
        $this->assertStringContainsString('public static function listRecent(int $limit = 100, array $filters = []): array', $content);
    }

    public function testAuditViewDoesNotExposeSensitiveLabelsDirectly(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Views/audit/index.php');

        $this->assertNotFalse($content);
        $lower = strtolower($content);
        $this->assertStringNotContainsString('session_token_hash', $lower);
        $this->assertStringNotContainsString('session_id', $lower);
        $this->assertStringNotContainsString('password', $lower);
        $this->assertStringNotContainsString('_csrf_token', $lower);
    }
}

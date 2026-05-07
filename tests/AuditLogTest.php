<?php

declare(strict_types=1);

use Browser\Models\AuditLog;
use PHPUnit\Framework\TestCase;

final class AuditLogTest extends TestCase
{
    public function testAuditLogModelFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/app/Models/AuditLog.php');
    }

    public function testAuditLogUsesAuditLogsTable(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Models/AuditLog.php');

        $this->assertNotFalse($content);
        $this->assertStringContainsString('INSERT INTO audit_logs', $content);
    }

    public function testSanitizeMetadataRemovesSensitiveFields(): void
    {
        $sanitized = AuditLog::sanitizeMetadata([
            'password' => 'secret',
            '_csrf_token' => 'csrf-value',
            'session_id' => 'session-value',
            'session_token_hash' => 'hash-value',
            'email' => 'user@example.com',
        ]);

        $this->assertArrayNotHasKey('password', $sanitized);
        $this->assertArrayNotHasKey('_csrf_token', $sanitized);
        $this->assertArrayNotHasKey('session_id', $sanitized);
        $this->assertArrayNotHasKey('session_token_hash', $sanitized);
        $this->assertSame('user@example.com', $sanitized['email']);
    }

    public function testEncodeMetadataReturnsJsonOrNull(): void
    {
        $encoded = AuditLog::encodeMetadata(['event' => 'login_success']);

        $this->assertNotNull($encoded);
        $this->assertJson($encoded);

        $emptyEncoded = AuditLog::encodeMetadata([]);
        $this->assertNull($emptyEncoded);
    }
}

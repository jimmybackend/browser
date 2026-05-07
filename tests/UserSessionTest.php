<?php

declare(strict_types=1);

use Browser\Models\UserSession;
use PHPUnit\Framework\TestCase;

final class UserSessionTest extends TestCase
{
    public function testUserSessionModelFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/app/Models/UserSession.php');
    }

    public function testHashSessionIdReturnsSha256HexString(): void
    {
        $hash = UserSession::hashSessionId('session-123');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}

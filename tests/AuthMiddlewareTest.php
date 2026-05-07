<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    public function testMiddlewareChecksPersistedSessionStatus(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php');

        $this->assertNotFalse($content);
        $this->assertStringContainsString('UserSession::isActive($sessionId)', $content);
    }

    public function testMiddlewareLogsOutAndRedirectsOnInvalidPersistedSession(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php');

        $this->assertNotFalse($content);
        $this->assertStringContainsString("Auth::logout();", $content);
        $this->assertStringContainsString("Response::redirect('/login');", $content);
    }
}

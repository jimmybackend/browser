<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function testProjectHasBasePath(): void
    {
        $this->assertDirectoryExists(__DIR__ . '/../app');
        $this->assertDirectoryExists(__DIR__ . '/../database/migrations');
    }
}

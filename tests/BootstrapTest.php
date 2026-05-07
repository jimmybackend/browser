<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__);
    }

    public function testProjectHasRequiredDirectories(): void
    {
        $this->assertDirectoryExists($this->rootPath . '/app');
        $this->assertDirectoryExists($this->rootPath . '/database/migrations');
    }

    public function testProjectHasRequiredEntryPointFiles(): void
    {
        $this->assertFileExists($this->rootPath . '/public/index.php');
        $this->assertFileExists($this->rootPath . '/bin/browser');
        $this->assertFileExists($this->rootPath . '/.env.example');
        $this->assertFileExists($this->rootPath . '/AGENTS.md');
        $this->assertFileExists($this->rootPath . '/phpunit.xml.dist');
        $this->assertFileExists($this->rootPath . '/scripts/validate.sh');
    }

    public function testComposerAutoloadMapsBrowserNamespaceToAppDirectory(): void
    {
        $composerJson = $this->readComposerJson();

        $this->assertArrayHasKey('autoload', $composerJson);
        $this->assertArrayHasKey('psr-4', $composerJson['autoload']);
        $this->assertArrayHasKey('Browser\\', $composerJson['autoload']['psr-4']);
        $this->assertSame('app/', $composerJson['autoload']['psr-4']['Browser\\']);
    }

    public function testComposerTestScriptUsesPhpunitXmlDistConfiguration(): void
    {
        $composerJson = $this->readComposerJson();

        $this->assertArrayHasKey('scripts', $composerJson);
        $this->assertArrayHasKey('test', $composerJson['scripts']);
        $this->assertSame(
            'vendor/bin/phpunit --configuration phpunit.xml.dist',
            $composerJson['scripts']['test']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(): array
    {
        $composerJsonPath = $this->rootPath . '/composer.json';
        $this->assertFileExists($composerJsonPath);

        $composerJsonContent = file_get_contents($composerJsonPath);
        $this->assertNotFalse($composerJsonContent);

        $decodedComposerJson = json_decode($composerJsonContent, true);
        $this->assertIsArray($decodedComposerJson);

        return $decodedComposerJson;
    }
}

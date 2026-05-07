<?php

declare(strict_types=1);

use Browser\Services\CrawlerService;
use Browser\Services\RobotsTxtService;
use PHPUnit\Framework\TestCase;

final class CrawlerServiceTest extends TestCase
{
    private CrawlerService $service;

    protected function setUp(): void
    {
        $this->service = new CrawlerService(new RobotsTxtService());
    }

    public function testNormalizeUrlResolvesRootRelativePathFromFileBase(): void
    {
        $normalized = $this->normalize('/signup.php', 'https://pay2dl.com/?lang=es');

        $this->assertSame('https://pay2dl.com/signup.php', $normalized);
    }

    public function testNormalizeUrlResolvesRelativePathFromFileBaseAtRoot(): void
    {
        $normalized = $this->normalize('contact-us.php', 'https://mailit.click/about-us.php');

        $this->assertSame('https://mailit.click/contact-us.php', $normalized);
    }

    public function testNormalizeUrlResolvesRelativePathWithinFolder(): void
    {
        $normalized = $this->normalize('contact-us.php', 'https://site.com/folder/page.html');

        $this->assertSame('https://site.com/folder/contact-us.php', $normalized);
    }

    public function testNormalizeUrlReplacesQueryWithoutCorruptingBasePath(): void
    {
        $normalized = $this->normalize('?lang=en', 'https://site.com/folder/page.html?foo=bar');

        $this->assertSame('https://site.com/folder/page.html?lang=en', $normalized);
    }

    public function testNormalizeUrlRejectsFragmentsAndUnsupportedSchemes(): void
    {
        $this->assertNull($this->normalize('#section', 'https://site.com/page.html'));
        $this->assertNull($this->normalize('javascript:alert(1)', 'https://site.com/page.html'));
        $this->assertNull($this->normalize('data:text/plain,hi', 'https://site.com/page.html'));
        $this->assertNull($this->normalize('file:///etc/passwd', 'https://site.com/page.html'));
        $this->assertNull($this->normalize('ftp://site.com/file.txt', 'https://site.com/page.html'));
    }

    public function testNormalizeUrlEncodesUnsafeSpacesInPathAndQuery(): void
    {
        $normalized = $this->normalize('https://example.com/files/my file.pdf?download=report 2026');

        $this->assertSame('https://example.com/files/my%20file.pdf?download=report%202026', $normalized);
    }

    public function testNormalizeUrlKeepsAlreadyEncodedPathUntouched(): void
    {
        $normalized = $this->normalize('https://example.com/files/manual%20v2.pdf');

        $this->assertSame('https://example.com/files/manual%20v2.pdf', $normalized);
    }

    public function testParseHtmlIsNullSafeForTitleDescriptionAndLanguage(): void
    {
        $result = $this->parseHtml('<html><body><a href="/about">About</a></body></html>');

        $this->assertNull($result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['language']);
    }

    private function normalize(string $url, ?string $base = null): ?string
    {
        $method = new ReflectionMethod(CrawlerService::class, 'normalizeUrl');
        $method->setAccessible(true);

        return $method->invoke($this->service, $url, $base);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHtml(string $html): array
    {
        $method = new ReflectionMethod(CrawlerService::class, 'parseHtml');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke($this->service, 'https://example.com/page.html', $html);

        return $result;
    }
}

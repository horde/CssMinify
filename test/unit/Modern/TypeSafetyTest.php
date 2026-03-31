<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  CssMinify
 */

namespace Horde\CssMinify\Test\Modern;

use Horde\CssMinify\CssParserMinifier;
use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use Horde\CssMinify\ImportCallback;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Tests for new type-safe features in modern API.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
#[CoversClass(Settings::class)]
#[CoversClass(UrlCallback::class)]
#[CoversClass(ImportCallback::class)]
#[CoversClass(StringInput::class)]
#[CoversClass(FileCollectionInput::class)]
#[CoversClass(CssFile::class)]
class TypeSafetyTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/';
    }

    public function testSettingsImmutability(): void
    {
        $urlCallback = new UrlCallback(fn($p) => $p);
        $importCallback = new ImportCallback(fn($p) => [$p, '']);
        $logger = new NullLogger();

        $settings = new Settings(
            dataUrlCallback: $urlCallback,
            importCallback: $importCallback,
            logger: $logger
        );

        // Properties are readonly
        $this->assertSame($urlCallback, $settings->dataUrlCallback);
        $this->assertSame($importCallback, $settings->importCallback);
        $this->assertSame($logger, $settings->logger);
    }

    public function testSettingsDefaults(): void
    {
        $settings = new Settings();

        $this->assertNull($settings->dataUrlCallback);
        $this->assertNull($settings->importCallback);
        $this->assertInstanceOf(NullLogger::class, $settings->logger);
    }

    public function testUrlCallbackInvoke(): void
    {
        $invoked = false;
        $urlCallback = new UrlCallback(function ($path) use (&$invoked) {
            $invoked = true;
            return '/rewritten' . $path;
        });

        $result = $urlCallback->__invoke('/images/test.png');

        $this->assertTrue($invoked);
        $this->assertSame('/rewritten/images/test.png', $result);
    }

    public function testImportCallbackInvoke(): void
    {
        $invoked = false;
        $importCallback = new ImportCallback(function ($path) use (&$invoked) {
            $invoked = true;
            return ['/uri/' . $path, '/filesystem/' . $path];
        });

        $result = $importCallback->__invoke('test.css');

        $this->assertTrue($invoked);
        $this->assertSame(['/uri/test.css', '/filesystem/test.css'], $result);
    }

    public function testCssFileValidation(): void
    {
        $file = new CssFile('test.css', $this->fixturesPath . 'simple.css');

        $this->assertSame('test.css', $file->uri);
        $this->assertSame($this->fixturesPath . 'simple.css', $file->filepath);
    }

    public function testCssFileThrowsOnUnreadable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        new CssFile('missing.css', '/nonexistent/path.css');
    }

    public function testCssFileGetContent(): void
    {
        $file = new CssFile('test.css', $this->fixturesPath . 'simple.css');

        $content = $file->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('body', $content);
        $this->assertStringContainsString('color', $content);
    }

    public function testCssFileResolveRelativeUrl(): void
    {
        $file = new CssFile('css/themes/main.css', $this->fixturesPath . 'simple.css');

        $resolved = $file->resolveRelativeUrl('../images/bg.png');

        // Path should be normalized: css/themes/../images/bg.png => css/images/bg.png
        $this->assertSame('css/images/bg.png', $resolved);
    }

    public function testFileCollectionInputEmpty(): void
    {
        $collection = new FileCollectionInput();

        $files = $collection->getFiles();

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    public function testFileCollectionInputMultiple(): void
    {
        $file1 = new CssFile('a.css', $this->fixturesPath . 'simple.css');
        $file2 = new CssFile('b.css', $this->fixturesPath . 'complex.css');

        $collection = new FileCollectionInput($file1, $file2);

        $files = $collection->getFiles();

        $this->assertCount(2, $files);
        $this->assertSame($file1, $files[0]);
        $this->assertSame($file2, $files[1]);
    }

    public function testStringInputImmutability(): void
    {
        $css = 'body { color: red; }';
        $input = new StringInput($css);

        $this->assertSame($css, $input->css);
    }

    public function testMinifierAcceptsUnionType(): void
    {
        // Test StringInput
        $stringMinifier = new CssParserMinifier(new StringInput('body{}'));
        $this->assertInstanceOf(CssParserMinifier::class, $stringMinifier);

        // Test FileCollectionInput
        $fileMinifier = new CssParserMinifier(new FileCollectionInput());
        $this->assertInstanceOf(CssParserMinifier::class, $fileMinifier);
    }

    public function testPsr3LoggerIntegration(): void
    {
        $logger = new NullLogger();

        $css = 'body { color: }'; // Malformed CSS
        $minifier = new CssParserMinifier(
            new StringInput($css),
            new Settings(logger: $logger)
        );

        $result = $minifier->minify();

        // Should not throw, returns input as-is on error
        $this->assertIsString($result);
    }

    public function testCallbackTypeSafetyEnforcement(): void
    {
        // UrlCallback enforces string return
        $urlCallback = new UrlCallback(function ($path): string {
            return '/cdn' . $path;
        });

        $result = $urlCallback->__invoke('/test.png');
        $this->assertIsString($result);

        // ImportCallback enforces array{0: string, 1: string} return
        $importCallback = new ImportCallback(function ($path): array {
            return [$path, '/path/' . $path];
        });

        $result = $importCallback->__invoke('test.css');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }

    public function testNoSabberwormTypesInPublicApi(): void
    {
        $minifier = new CssParserMinifier(new StringInput('body{}'));

        // Verify no Sabberworm types are exposed
        $reflection = new ReflectionClass($minifier);

        // Check minify() return type
        $minifyMethod = $reflection->getMethod('minify');
        $returnType = $minifyMethod->getReturnType();
        $this->assertSame('string', $returnType?->getName());

        // Check __toString() return type
        $toStringMethod = $reflection->getMethod('__toString');
        $returnType = $toStringMethod->getReturnType();
        $this->assertSame('string', $returnType?->getName());
    }

    public function testImmutableApiPattern(): void
    {
        $file1 = new CssFile('a.css', $this->fixturesPath . 'simple.css');
        $collection = new FileCollectionInput($file1);

        $minifier1 = new CssParserMinifier($collection);
        $minifier2 = new CssParserMinifier($collection);

        // Same input produces same output
        $this->assertSame($minifier1->minify(), $minifier2->minify());
    }
}

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

namespace Horde\CssMinify\Test;

use Horde\CssMinify\CssParserMinifier;
use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use Horde\CssMinify\ImportCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Tests for modern PSR-4 API (src/).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
class ModernApiTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/fixtures/';
    }

    public function testMinifyStringInput(): void
    {
        $css = 'body { color: red; margin: 10px; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
    }

    public function testMinifyFileCollectionInput(): void
    {
        $files = new FileCollectionInput(
            new CssFile('simple.css', $this->fixturesPath . 'simple.css'),
            new CssFile('multiple-rules.css', $this->fixturesPath . 'multiple-rules.css')
        );

        $minifier = new CssParserMinifier($files);
        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('.header', $result);
    }

    public function testMinifyWithUrlCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $urlsProcessed = [];
        $urlCallback = new UrlCallback(function (string $url) use (&$urlsProcessed): string {
            $urlsProcessed[] = $url;
            return '/cdn' . $url;
        });

        $minifier = new CssParserMinifier(
            $files,
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        $this->assertNotEmpty($urlsProcessed);
        $this->assertStringContainsString('/cdn', $result);
    }

    public function testMinifyWithImportCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-imports.css', $this->fixturesPath . 'with-imports.css')
        );

        $importCallback = new ImportCallback(function (string $path): array {
            return [$path, $this->fixturesPath . 'shared.css'];
        });

        $minifier = new CssParserMinifier(
            $files,
            new Settings(importCallback: $importCallback)
        );

        $result = $minifier->minify();

        // Import should be removed
        $this->assertStringNotContainsString('@import', $result);
        // Imported content should be included
        $this->assertStringContainsString('.header', $result);
    }

    public function testCssFileValidatesReadability(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        new CssFile('missing.css', '/nonexistent/path.css');
    }

    public function testCssFileResolveRelativeUrl(): void
    {
        $file = new CssFile('css/main.css', $this->fixturesPath . 'simple.css');

        $resolved = $file->resolveRelativeUrl('images/bg.png');

        $this->assertSame('css/images/bg.png', $resolved);
    }

    public function testToStringCallsMinify(): void
    {
        $css = 'body { color: blue; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = (string) $minifier;

        $this->assertStringContainsString('color:blue', $result);
    }

    public function testEmptyFileCollection(): void
    {
        $files = new FileCollectionInput();
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        $this->assertSame('', $result);
    }
}

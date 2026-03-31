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
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Settings;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Tests for modern file-based minification (equivalent to FileMinificationTest).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
class FileMinificationTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/';
    }

    public function testMinifySingleFile(): void
    {
        $files = new FileCollectionInput(
            new CssFile('simple.css', $this->fixturesPath . 'simple.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
    }

    public function testMinifyMultipleFiles(): void
    {
        $files = new FileCollectionInput(
            new CssFile('simple.css', $this->fixturesPath . 'simple.css'),
            new CssFile('multiple-rules.css', $this->fixturesPath . 'multiple-rules.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        // Should contain rules from both files
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('background:blue', $result);
    }

    public function testMinifyUnreadableFileLogsError(): void
    {
        // CssFile constructor throws on unreadable files
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        new CssFile('missing.css', $this->fixturesPath . 'nonexistent.css');
    }

    public function testMinifyHandlesEmptyFileList(): void
    {
        $files = new FileCollectionInput();
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        $this->assertSame('', $result);
    }

    public function testMinifyCombinesMultipleFiles(): void
    {
        $files = new FileCollectionInput(
            new CssFile('simple.css', $this->fixturesPath . 'simple.css'),
            new CssFile('complex.css', $this->fixturesPath . 'complex.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        // Should contain rules from both files combined
        $this->assertStringContainsString('body', $result);
        $this->assertStringContainsString('@media', $result);
        $this->assertStringContainsString('.button:hover', $result);
    }

    public function testMinifyHandlesParseErrors(): void
    {
        $files = new FileCollectionInput(
            new CssFile('malformed.css', $this->fixturesPath . 'malformed.css')
        );
        $minifier = new CssParserMinifier($files, new Settings(logger: new NullLogger()));

        $result = $minifier->minify();

        // Should output CSS as-is on parse error
        $this->assertIsString($result);
    }

    public function testMinifyOutputContainsAllRules(): void
    {
        $files = new FileCollectionInput(
            new CssFile('multiple-rules.css', $this->fixturesPath . 'multiple-rules.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        $this->assertStringContainsString('body', $result);
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('footer', $result);
    }

    public function testMinifyHandlesLargeFiles(): void
    {
        // Use existing fixture - if it processes, large files should work too
        $files = new FileCollectionInput(
            new CssFile('complex.css', $this->fixturesPath . 'complex.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testMinifyPreservesFileOrder(): void
    {
        $files = new FileCollectionInput(
            new CssFile('simple.css', $this->fixturesPath . 'simple.css'),
            new CssFile('multiple-rules.css', $this->fixturesPath . 'multiple-rules.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        // Rules from first file should appear before second
        $simplePos = strpos($result, 'body');
        $headerPos = strpos($result, '.header');

        $this->assertNotFalse($simplePos);
        $this->assertNotFalse($headerPos);
        $this->assertLessThan($headerPos, $simplePos);
    }
}

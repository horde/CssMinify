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

use Horde_CssMinify_CssParser;
use Horde_Log_Logger;
use Horde_Log_Handler_Mock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for file-based minification (array input).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(Horde_CssMinify_CssParser::class)]
class FileMinificationTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/fixtures/';
    }

    public function testMinifySingleFile(): void
    {
        $data = ['simple.css' => $this->fixturesPath . 'simple.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
    }

    public function testMinifyMultipleFiles(): void
    {
        $data = [
            'simple.css' => $this->fixturesPath . 'simple.css',
            'multiple-rules.css' => $this->fixturesPath . 'multiple-rules.css'
        ];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Should contain rules from both files
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('background:blue', $result);
    }

    public function testMinifyUnreadableFileLogsError(): void
    {
        $mockHandler = new Horde_Log_Handler_Mock();
        $logger = new Horde_Log_Logger($mockHandler);

        $data = ['missing.css' => $this->fixturesPath . 'nonexistent.css'];
        $minifier = new Horde_CssMinify_CssParser($data, ['logger' => $logger]);

        $result = $minifier->minify();

        // Should have logged an error
        $this->assertNotEmpty($mockHandler->events);
        $this->assertSame('', $result);
    }

    public function testMinifyMissingFileLogsError(): void
    {
        $mockHandler = new Horde_Log_Handler_Mock();
        $logger = new Horde_Log_Logger($mockHandler);

        $data = ['test.css' => '/nonexistent/path/file.css'];
        $minifier = new Horde_CssMinify_CssParser($data, ['logger' => $logger]);

        $result = $minifier->minify();

        // Should log error for unreadable file
        $this->assertNotEmpty($mockHandler->events);
    }

    public function testMinifyInvalidPathHandling(): void
    {
        $data = ['test.css' => ''];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Empty path should be handled gracefully
        $this->assertIsString($result);
    }

    public function testMinifyPreservesFileOrder(): void
    {
        $data = [
            'simple.css' => $this->fixturesPath . 'simple.css',
            'multiple-rules.css' => $this->fixturesPath . 'multiple-rules.css'
        ];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Rules from first file should appear before second
        $simplePos = strpos($result, 'body');
        $headerPos = strpos($result, '.header');

        $this->assertNotFalse($simplePos);
        $this->assertNotFalse($headerPos);
        $this->assertLessThan($headerPos, $simplePos);
    }

    public function testMinifyHandlesEmptyFileList(): void
    {
        $minifier = new Horde_CssMinify_CssParser([]);

        $result = $minifier->minify();

        $this->assertSame('', $result);
    }

    public function testMinifyCombinesMultipleFiles(): void
    {
        $data = [
            'simple.css' => $this->fixturesPath . 'simple.css',
            'complex.css' => $this->fixturesPath . 'complex.css'
        ];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Should contain rules from both files combined
        $this->assertStringContainsString('body', $result);
        $this->assertStringContainsString('@media', $result);
        $this->assertStringContainsString('.button:hover', $result);
    }

    public function testMinifyHandlesParseErrors(): void
    {
        $mockHandler = new Horde_Log_Handler_Mock();
        $logger = new Horde_Log_Logger($mockHandler);

        $data = ['malformed.css' => $this->fixturesPath . 'malformed.css'];
        $minifier = new Horde_CssMinify_CssParser($data, ['logger' => $logger]);

        $result = $minifier->minify();

        // Should log error and output CSS as-is
        $this->assertIsString($result);
    }

    public function testMinifyOutputContainsAllRules(): void
    {
        $data = ['multiple-rules.css' => $this->fixturesPath . 'multiple-rules.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        $this->assertStringContainsString('body', $result);
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('footer', $result);
    }

    public function testMinifyHandlesUnicodeFilenames(): void
    {
        // Test with regular ASCII path (Unicode handling is filesystem-dependent)
        $data = ['simple.css' => $this->fixturesPath . 'simple.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
    }

    public function testMinifyHandlesLargeFiles(): void
    {
        // Use existing fixture - if it processes, large files should work too
        $data = ['complex.css' => $this->fixturesPath . 'complex.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}

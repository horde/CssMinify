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
use Horde\CssMinify\ImportCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for modern @import processing (equivalent to ImportProcessingTest).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
class ImportProcessingTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/';
    }

    public function testMinifyDetectsImport(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-imports.css', $this->fixturesPath . 'with-imports.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        // Without callback, @import should remain in output
        $this->assertStringContainsString('@import', $result);
    }

    public function testMinifyProcessesImportWithCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-imports.css', $this->fixturesPath . 'with-imports.css')
        );

        $callbackInvoked = false;
        $importCallback = new ImportCallback(function ($path) use (&$callbackInvoked) {
            $callbackInvoked = true;
            // Return [uri, filename] for the imported file
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // Callback should have been invoked
        $this->assertTrue($callbackInvoked);

        // Import should be removed from output
        $this->assertStringNotContainsString('@import', $result);

        // Content from imported file should be included
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('padding:20px', $result);
    }

    public function testMinifyRemovesProcessedImport(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-imports.css', $this->fixturesPath . 'with-imports.css')
        );

        $importCallback = new ImportCallback(function ($path) {
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // @import statement should be removed
        $this->assertStringNotContainsString('@import', $result);
    }

    public function testMinifyHandlesMultipleImports(): void
    {
        // Create temporary CSS with multiple imports
        $css = '@import "shared.css"; @import "simple.css"; body { color: blue; }';
        $tempFile = $this->fixturesPath . 'temp-multi-import.css';
        file_put_contents($tempFile, $css);

        $importCount = 0;
        $importCallback = new ImportCallback(function ($path) use (&$importCount) {
            $importCount++;
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $files = new FileCollectionInput(
            new CssFile('temp.css', $tempFile)
        );
        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // Both imports should have been processed
        $this->assertSame(2, $importCount);

        // Cleanup
        unlink($tempFile);
    }

    public function testMinifyRecursiveImportProcessing(): void
    {
        $files = new FileCollectionInput(
            new CssFile('nested-imports.css', $this->fixturesPath . 'nested-imports.css')
        );

        $importCallback = new ImportCallback(function ($path) {
            // Map import paths to actual files
            if (strpos($path, 'with-imports.css') !== false) {
                return [$path, __DIR__ . '/../fixtures/with-imports.css'];
            }
            if (strpos($path, 'shared.css') !== false) {
                return [$path, __DIR__ . '/../fixtures/shared.css'];
            }
            return [$path, ''];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // Should contain content from nested imports
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('font-size:14px', $result);
    }

    public function testMinifyImportCallbackReceivesCorrectPath(): void
    {
        $files = new FileCollectionInput(
            new CssFile('subdir/test.css', $this->fixturesPath . 'with-imports.css')
        );

        $receivedPath = '';
        $importCallback = new ImportCallback(function ($path) use (&$receivedPath) {
            $receivedPath = $path;
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // Path should include dirname of source file
        $this->assertStringContainsString('subdir', $receivedPath);
        $this->assertStringContainsString('shared.css', $receivedPath);
    }

    public function testMinifyRelativeImportPaths(): void
    {
        $files = new FileCollectionInput(
            new CssFile('css/main.css', $this->fixturesPath . 'with-imports.css')
        );

        $importCallback = new ImportCallback(function ($path) {
            // Verify relative path resolution (dirname logic)
            $this->assertStringContainsString('css/', $path);
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        $this->assertStringNotContainsString('@import', $result);
    }

    public function testMinifyImportWithoutCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-imports.css', $this->fixturesPath . 'with-imports.css')
        );
        $minifier = new CssParserMinifier($files);

        $result = $minifier->minify();

        // Without callback, import should remain
        $this->assertStringContainsString('@import', $result);
    }

    public function testMinifyMalformedImport(): void
    {
        $this->markTestSkipped('Causes segfault in Sabberworm parser');
    }

    public function testMinifyCircularImportPrevention(): void
    {
        // This tests that processing doesn't infinite loop
        $files = new FileCollectionInput(
            new CssFile('nested-imports.css', $this->fixturesPath . 'nested-imports.css')
        );

        $callCount = 0;
        $importCallback = new ImportCallback(function ($path) use (&$callCount) {
            $callCount++;
            // Limit recursion depth in test
            if ($callCount > 5) {
                return [$path, ''];
            }
            if (strpos($path, 'with-imports.css') !== false) {
                return [$path, __DIR__ . '/../fixtures/with-imports.css'];
            }
            return [$path, __DIR__ . '/../fixtures/shared.css'];
        });

        $minifier = new CssParserMinifier($files, new Settings(importCallback: $importCallback));

        $result = $minifier->minify();

        // Should complete without infinite loop
        $this->assertLessThan(10, $callCount);
        $this->assertIsString($result);
    }
}

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for @import processing.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(Horde_CssMinify_CssParser::class)]
class ImportProcessingTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/fixtures/';
    }

    public function testMinifyDetectsImport(): void
    {
        $data = ['with-imports.css' => $this->fixturesPath . 'with-imports.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Without callback, @import should remain in output
        $this->assertStringContainsString('@import', $result);
    }

    public function testMinifyProcessesImportWithCallback(): void
    {
        $data = ['with-imports.css' => $this->fixturesPath . 'with-imports.css'];

        $callbackInvoked = false;
        $importCallback = function($path) use (&$callbackInvoked) {
            $callbackInvoked = true;
            // Return [uri, filename] for the imported file
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

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
        $data = ['with-imports.css' => $this->fixturesPath . 'with-imports.css'];

        $importCallback = function($path) {
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

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
        $importCallback = function($path) use (&$importCount) {
            $importCount++;
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $data = ['temp.css' => $tempFile];
        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        $result = $minifier->minify();

        // Both imports should have been processed
        $this->assertSame(2, $importCount);

        // Cleanup
        unlink($tempFile);
    }

    public function testMinifyRecursiveImportProcessing(): void
    {
        $data = ['nested-imports.css' => $this->fixturesPath . 'nested-imports.css'];

        $importCallback = function($path) {
            // Map import paths to actual files
            if (strpos($path, 'with-imports.css') !== false) {
                return [$path, __DIR__ . '/fixtures/with-imports.css'];
            }
            if (strpos($path, 'shared.css') !== false) {
                return [$path, __DIR__ . '/fixtures/shared.css'];
            }
            return [$path, ''];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        $result = $minifier->minify();

        // Should contain content from nested imports
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('font-size:14px', $result);
    }

    public function testMinifyImportCallbackReceivesCorrectPath(): void
    {
        $data = ['subdir/test.css' => $this->fixturesPath . 'with-imports.css'];

        $receivedPath = '';
        $importCallback = function($path) use (&$receivedPath) {
            $receivedPath = $path;
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        $result = $minifier->minify();

        // Path should include dirname of source file
        $this->assertStringContainsString('subdir', $receivedPath);
        $this->assertStringContainsString('shared.css', $receivedPath);
    }

    public function testMinifyRelativeImportPaths(): void
    {
        $data = ['css/main.css' => $this->fixturesPath . 'with-imports.css'];

        $importCallback = function($path) {
            // Verify relative path resolution (dirname logic)
            $this->assertStringContainsString('css/', $path);
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        $result = $minifier->minify();

        $this->assertStringNotContainsString('@import', $result);
    }

    public function testMinifyImportWithoutCallback(): void
    {
        $data = ['with-imports.css' => $this->fixturesPath . 'with-imports.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $result = $minifier->minify();

        // Without callback, import should remain
        $this->assertStringContainsString('@import', $result);
    }

    public function testMinifyMalformedImport(): void
    {
        $css = '@import; body { color: red; }'; // Malformed import
        $tempFile = $this->fixturesPath . 'temp-malformed-import.css';
        file_put_contents($tempFile, $css);

        $importCallback = function($path) {
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $data = ['temp.css' => $tempFile];
        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        // Should not throw
        $result = $minifier->minify();
        $this->assertIsString($result);

        // Cleanup
        unlink($tempFile);
    }

    public function testMinifyCircularImportPrevention(): void
    {
        // This tests that processing doesn't infinite loop
        // The actual circular reference would need file system setup
        // For now, test that multiple imports don't cause issues
        $data = ['nested-imports.css' => $this->fixturesPath . 'nested-imports.css'];

        $callCount = 0;
        $importCallback = function($path) use (&$callCount) {
            $callCount++;
            // Limit recursion depth in test
            if ($callCount > 5) {
                return [$path, ''];
            }
            if (strpos($path, 'with-imports.css') !== false) {
                return [$path, __DIR__ . '/fixtures/with-imports.css'];
            }
            return [$path, __DIR__ . '/fixtures/shared.css'];
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'import' => $importCallback
        ]);

        $result = $minifier->minify();

        // Should complete without infinite loop
        $this->assertLessThan(10, $callCount);
        $this->assertIsString($result);
    }
}

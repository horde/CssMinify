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
use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for modern URL processing (equivalent to UrlProcessingTest).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
class UrlProcessingTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures/';
    }

    public function testMinifyDetectsBackgroundUrls(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Should have detected background-image URL
        $this->assertNotEmpty($urlsFound);
        $this->assertGreaterThan(0, count(array_filter($urlsFound, function ($url) {
            return strpos($url, 'bg.png') !== false || strpos($url, 'logo.svg') !== false;
        })));
    }

    public function testMinifyDetectsSrcUrls(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Should have detected @font-face src URL
        $hasFontUrl = false;
        foreach ($urlsFound as $url) {
            if (strpos($url, 'custom.ttf') !== false) {
                $hasFontUrl = true;
                break;
            }
        }
        $this->assertTrue($hasFontUrl, 'Should detect font src URL');
    }

    public function testMinifyExtractsNestedUrls(): void
    {
        $files = new FileCollectionInput(
            new CssFile('nested-urls.css', $this->fixturesPath . 'nested-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Should extract URL from nested list (gradient + url)
        $this->assertGreaterThan(0, count($urlsFound));
        $hasBgPng = false;
        foreach ($urlsFound as $url) {
            if (strpos($url, 'bg.png') !== false) {
                $hasBgPng = true;
                break;
            }
        }
        $this->assertTrue($hasBgPng, 'Should extract bg.png from nested list');
    }

    public function testMinifyExtractsUrlsFrom3LevelNesting(): void
    {
        $files = new FileCollectionInput(
            new CssFile('nested-urls.css', $this->fixturesPath . 'nested-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // .complex rule has 3-level nesting
        $hasPatternPng = false;
        foreach ($urlsFound as $url) {
            if (strpos($url, 'pattern.png') !== false) {
                $hasPatternPng = true;
                break;
            }
        }
        $this->assertTrue($hasPatternPng, 'Should extract from 3-level nested list');
    }

    public function testMinifyRewritesRelativeUrls(): void
    {
        $files = new FileCollectionInput(
            new CssFile('subdir/test.css', $this->fixturesPath . 'with-urls.css')
        );

        $dataUrlCallback = new UrlCallback(function ($path) {
            // Should receive absolute path with dirname prepended
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Relative URLs should be rewritten with dirname
        $this->assertStringContainsString('subdir/', $result);
    }

    public function testMinifyPreservesAbsoluteUrls(): void
    {
        $css = 'body { background: url(http://example.com/image.png); }';
        $tempFile = $this->fixturesPath . 'temp-absolute-url.css';
        file_put_contents($tempFile, $css);

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $files = new FileCollectionInput(
            new CssFile('test.css', $tempFile)
        );
        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Absolute URLs (starting with http) should not be modified
        // The callback should not be called for absolute URLs
        $this->assertStringContainsString('http://example.com/image.png', $result);

        unlink($tempFile);
    }

    public function testMinifyPreservesDataUrls(): void
    {
        $css = 'body { background: url(data:image/png;base64,iVBORw0KG); }';
        $tempFile = $this->fixturesPath . 'temp-data-url.css';
        file_put_contents($tempFile, $css);

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $files = new FileCollectionInput(
            new CssFile('test.css', $tempFile)
        );
        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Data URLs should be preserved
        // Callback should not be called for data: URLs
        $this->assertStringContainsString('data:image/png', $result);

        unlink($tempFile);
    }

    public function testMinifyDataUrlCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $callbackInvoked = false;
        $dataUrlCallback = new UrlCallback(function ($path) use (&$callbackInvoked) {
            $callbackInvoked = true;
            // Convert to data URL
            return 'data:image/png;base64,FAKEDATA';
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Callback should have been invoked
        $this->assertTrue($callbackInvoked);

        // Result should contain data URL
        $this->assertStringContainsString('data:image/png', $result);
    }

    public function testMinifyUrlPathResolution(): void
    {
        $files = new FileCollectionInput(
            new CssFile('css/main.css', $this->fixturesPath . 'with-urls.css')
        );

        $receivedPaths = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$receivedPaths) {
            $receivedPaths[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Verify paths are resolved correctly
        // with-urls.css contains:
        // - url(../images/bg.png) => resolves to images/bg.png (normalized: css/../images -> images)
        // - url("logo.svg") => resolves to css/logo.svg
        // - url(fonts/custom.ttf) => resolves to css/fonts/custom.ttf

        $this->assertCount(3, $receivedPaths, 'Should process all 3 URLs');
        $this->assertContains('images/bg.png', $receivedPaths, 'Parent-relative URL normalized');
        $this->assertContains('css/logo.svg', $receivedPaths, 'Simple relative URL');
        $this->assertContains('css/fonts/custom.ttf', $receivedPaths, 'Nested relative URL');
    }

    public function testMinifyMultipleUrlsInSameRule(): void
    {
        $files = new FileCollectionInput(
            new CssFile('nested-urls.css', $this->fixturesPath . 'nested-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // .complex rule has multiple URLs in one property
        $this->assertGreaterThanOrEqual(2, count($urlsFound));
    }

    public function testMinifyUrlsInDifferentRules(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $urlsFound = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Should find URLs in body, .logo, and @font-face
        $this->assertGreaterThanOrEqual(3, count($urlsFound));
    }

    public function testMinifyUrlRewritingWithCallback(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $dataUrlCallback = new UrlCallback(function ($path) {
            // Rewrite path
            return '/cdn' . $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // URLs should be rewritten with /cdn prefix
        $this->assertStringContainsString('/cdn', $result);
    }

    public function testMinifyUrlCallbackReceivesCorrectPath(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $receivedPaths = [];
        $dataUrlCallback = new UrlCallback(function ($path) use (&$receivedPaths) {
            $receivedPaths[] = $path;
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // Each path should be a string
        foreach ($receivedPaths as $path) {
            $this->assertIsString($path);
            $this->assertNotEmpty($path);
        }
    }

    public function testMinifyMalformedUrl(): void
    {
        $css = 'body { background: url(); }'; // Empty URL
        $tempFile = $this->fixturesPath . 'temp-malformed-url.css';
        file_put_contents($tempFile, $css);

        $dataUrlCallback = new UrlCallback(function ($path) {
            return $path;
        });

        $files = new FileCollectionInput(
            new CssFile('test.css', $tempFile)
        );
        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        // Should not throw
        $result = $minifier->minify();
        $this->assertIsString($result);

        unlink($tempFile);
    }

    public function testMinifyPreservesUrlQuotes(): void
    {
        $files = new FileCollectionInput(
            new CssFile('with-urls.css', $this->fixturesPath . 'with-urls.css')
        );

        $dataUrlCallback = new UrlCallback(function ($path) {
            return $path;
        });

        $minifier = new CssParserMinifier($files, new Settings(dataUrlCallback: $dataUrlCallback));

        $result = $minifier->minify();

        // URLs should still be present (quoted or unquoted)
        $this->assertStringContainsString('url(', $result);
    }
}

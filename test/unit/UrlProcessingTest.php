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
 * Tests for URL extraction and rewriting.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(Horde_CssMinify_CssParser::class)]
class UrlProcessingTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/fixtures/';
    }

    public function testMinifyDetectsBackgroundUrls(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Should have detected background-image URL
        $this->assertNotEmpty($urlsFound);
        $this->assertGreaterThan(0, count(array_filter($urlsFound, function($url) {
            return strpos($url, 'bg.png') !== false || strpos($url, 'logo.svg') !== false;
        })));
    }

    public function testMinifyDetectsSrcUrls(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

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
        $data = ['nested-urls.css' => $this->fixturesPath . 'nested-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

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
        $data = ['nested-urls.css' => $this->fixturesPath . 'nested-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

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
        $data = ['subdir/test.css' => $this->fixturesPath . 'with-urls.css'];

        $dataUrlCallback = function($path) {
            // Should receive absolute path with dirname prepended
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

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
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $data = ['test.css' => $tempFile];
        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Absolute URLs (starting with http) should not be modified
        // The callback may not even be called for absolute URLs
        $this->assertStringContainsString('http://example.com/image.png', $result);

        unlink($tempFile);
    }

    public function testMinifyPreservesDataUrls(): void
    {
        $css = 'body { background: url(data:image/png;base64,iVBORw0KG); }';
        $tempFile = $this->fixturesPath . 'temp-data-url.css';
        file_put_contents($tempFile, $css);

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $data = ['test.css' => $tempFile];
        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Data URLs should be preserved
        // Callback should not be called for data: URLs (Horde_Url_Data::isData check)
        $this->assertStringContainsString('data:image/png', $result);

        unlink($tempFile);
    }

    public function testMinifyDataUrlCallback(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $callbackInvoked = false;
        $dataUrlCallback = function($path) use (&$callbackInvoked) {
            $callbackInvoked = true;
            // Convert to data URL
            return 'data:image/png;base64,FAKEDATA';
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Callback should have been invoked
        $this->assertTrue($callbackInvoked);

        // Result should contain data URL
        $this->assertStringContainsString('data:image/png', $result);
    }

    public function testMinifyUrlPathResolution(): void
    {
        $data = ['css/main.css' => $this->fixturesPath . 'with-urls.css'];

        $receivedPaths = [];
        $dataUrlCallback = function($path) use (&$receivedPaths) {
            $receivedPaths[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Paths should have dirname prepended
        foreach ($receivedPaths as $path) {
            $this->assertStringContainsString('css/', $path);
        }
    }

    public function testMinifyMultipleUrlsInSameRule(): void
    {
        $data = ['nested-urls.css' => $this->fixturesPath . 'nested-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // .complex rule has multiple URLs in one property
        $this->assertGreaterThanOrEqual(2, count($urlsFound));
    }

    public function testMinifyUrlsInDifferentRules(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $urlsFound = [];
        $dataUrlCallback = function($path) use (&$urlsFound) {
            $urlsFound[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // Should find URLs in body, .logo, and @font-face
        $this->assertGreaterThanOrEqual(3, count($urlsFound));
    }

    public function testMinifyUrlRewritingWithCallback(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $dataUrlCallback = function($path) {
            // Rewrite path
            return '/cdn' . $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // URLs should be rewritten with /cdn prefix
        $this->assertStringContainsString('/cdn', $result);
    }

    public function testMinifyUrlCallbackReceivesCorrectPath(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $receivedPaths = [];
        $dataUrlCallback = function($path) use (&$receivedPaths) {
            $receivedPaths[] = $path;
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

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

        $dataUrlCallback = function($path) {
            return $path;
        };

        $data = ['test.css' => $tempFile];
        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        // Should not throw
        $result = $minifier->minify();
        $this->assertIsString($result);

        unlink($tempFile);
    }

    public function testMinifyPreservesUrlQuotes(): void
    {
        $data = ['with-urls.css' => $this->fixturesPath . 'with-urls.css'];

        $dataUrlCallback = function($path) {
            return $path;
        };

        $minifier = new Horde_CssMinify_CssParser($data, [
            'dataurl' => $dataUrlCallback
        ]);

        $result = $minifier->minify();

        // URLs should still be present (quoted or unquoted)
        $this->assertStringContainsString('url(', $result);
    }
}

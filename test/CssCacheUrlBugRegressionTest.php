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
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for CSS cache URL bug reported 2026-03-31.
 *
 * Bug: When CSS caching is enabled, graphics URLs are rewritten incorrectly:
 * - Expected: /themes/horde/default/graphics/navi-arrow-normal.png
 * - Actual: /static//graphics/navi-arrow-normal.png (double slash, wrong path)
 *
 * Root cause: CssFile::resolveRelativeUrl() didn't handle absolute URLs,
 * and ltrim() was stripping meaningful characters.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 * @coversNothing
 */
class CssCacheUrlBugRegressionTest extends TestCase
{
    private string $tempDir;
    private string $cssFilePath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cssminify_test_' . uniqid();
        mkdir($this->tempDir);

        $this->cssFilePath = $this->tempDir . '/screen.css';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cssFilePath)) {
            unlink($this->cssFilePath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * Test scenario from bug report: relative URLs in CSS should resolve
     * to theme directories, not /static/.
     */
    public function testRelativeUrlPreservesThemePath(): void
    {
        // Create CSS with relative URLs to graphics
        $css = <<<CSS
            body {
                background-image: url("graphics/navi-arrow-normal.png");
            }
            .icon {
                background: url("graphics/folders/inbox.png") no-repeat;
            }
            CSS;

        file_put_contents($this->cssFilePath, $css);

        // CSS URI represents theme location
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->cssFilePath
        );

        // Mock dataurl callback that returns the URL unchanged
        // (simulates when image is too large for base64 encoding)
        $urlCallback = new UrlCallback(function (string $uri): string {
            return $uri;  // Return as-is, no base64 encoding
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // URLs should be resolved relative to /themes/horde/default/, NOT /static/
        $this->assertStringContainsString(
            '/themes/horde/default/graphics/navi-arrow-normal.png',
            $result,
            'Relative URL should resolve to theme directory'
        );

        $this->assertStringContainsString(
            '/themes/horde/default/graphics/folders/inbox.png',
            $result,
            'Nested relative URL should resolve to theme directory'
        );

        // Should NOT contain /static/ paths
        $this->assertStringNotContainsString(
            '/static/graphics/',
            $result,
            'URLs should not resolve to /static/ directory'
        );

        // Should NOT contain double slashes
        $this->assertStringNotContainsString(
            '//graphics/',
            $result,
            'URLs should not contain double slashes'
        );
    }

    /**
     * Test absolute URLs in CSS (starting with /) should NOT be modified.
     */
    public function testAbsoluteUrlsNotModified(): void
    {
        // CSS with absolute URLs
        $css = <<<CSS
            .test {
                background: url("/graphics/logo.png");
            }
            CSS;

        file_put_contents($this->cssFilePath, $css);

        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->cssFilePath
        );

        $urlCallback = new UrlCallback(function (string $uri): string {
            return $uri;
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // Absolute URL should remain unchanged
        $this->assertStringContainsString(
            '/graphics/logo.png',
            $result,
            'Absolute URL should not be modified'
        );

        // Should NOT become relative to theme dir
        $this->assertStringNotContainsString(
            '/themes/horde/default/graphics/logo.png',
            $result,
            'Absolute URL should not be resolved relative to CSS location'
        );
    }

    /**
     * Test the exact scenario from the bug report:
     * When caching is ON, URLs change from theme paths to /static/ paths.
     */
    public function testUserReportedScenario(): void
    {
        // Simulate CSS content that would come from horde/default theme
        $css = <<<CSS
            .head {
                background-image: url("graphics/navi-arrow-normal.png");
            }
            .flag {
                background: url("graphics/flags/innocent.png");
            }
            .folder {
                background: url("graphics/folders/inbox.png");
            }
            CSS;

        file_put_contents($this->cssFilePath, $css);

        // CSS URI as it would be registered in the theme system
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->cssFilePath
        );

        // No dataurl callback (simulating failure to base64 encode)
        // URLs should be left in place, properly resolved
        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings()  // No callbacks = URLs stay as-is after resolution
        );

        $result = $minifier->minify();

        // These are the WORKING URLs (cache OFF) from the bug report:
        // - /themes/horde/default/graphics/navi-arrow-normal.png
        // - /themes/horde/default/graphics/flags/innocent.png  (note: user said imp, but using horde for consistency)
        // - /themes/horde/default/graphics/folders/inbox.png

        // With the fix, cached CSS should produce the same paths
        $this->assertStringContainsString('graphics/navi-arrow-normal.png', $result);
        $this->assertStringContainsString('graphics/flags/innocent.png', $result);
        $this->assertStringContainsString('graphics/folders/inbox.png', $result);

        // These are the BROKEN URLs (cache ON) from the bug report:
        // - /static//graphics/navi-arrow-normal.png (404)
        // - /static/graphics/flags/innocent.png (404)
        // - /static//graphics/folders/inbox.png (404)

        // With the fix, these patterns should NOT appear
        $this->assertStringNotContainsString('//graphics/', $result, 'No double slashes');
        $this->assertStringNotContainsString('/static/', $result, 'No /static/ paths when source is /themes/');
    }

    /**
     * Test URLs with whitespace (from CSS parsing) are handled correctly.
     */
    public function testUrlsWithWhitespace(): void
    {
        $css = <<<CSS
            .test {
                background: url(" graphics/logo.png ");
            }
            CSS;

        file_put_contents($this->cssFilePath, $css);

        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->cssFilePath
        );

        $urlCallback = new UrlCallback(function (string $uri): string {
            return $uri;
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // Whitespace should be trimmed, URL correctly resolved
        $this->assertStringContainsString(
            '/themes/horde/default/graphics/logo.png',
            $result
        );
    }

    /**
     * Test multiple CSS files from different apps (like imp, horde).
     */
    public function testMultipleAppsPreserveThemePaths(): void
    {
        // Horde CSS
        $hordeCss = 'body { background: url("graphics/logo.png"); }';
        $hordePath = $this->tempDir . '/horde.css';
        file_put_contents($hordePath, $hordeCss);

        // Imp CSS
        $impCss = '.flag { background: url("graphics/flags/innocent.png"); }';
        $impPath = $this->tempDir . '/imp.css';
        file_put_contents($impPath, $impCss);

        $hordeFile = new CssFile('/themes/horde/default/screen.css', $hordePath);
        $impFile = new CssFile('/themes/imp/default/screen.css', $impPath);

        $urlCallback = new UrlCallback(function (string $uri): string {
            return $uri;
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($hordeFile, $impFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // Each app should preserve its theme path
        $this->assertStringContainsString('/themes/horde/default/graphics/logo.png', $result);
        $this->assertStringContainsString('/themes/imp/default/graphics/flags/innocent.png', $result);

        // Cleanup
        unlink($hordePath);
        unlink($impPath);
    }
}

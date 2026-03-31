<?php

declare(strict_types=1);

/**
 * Demonstrates the CSS cache URL bug fix for the exact scenario reported by user.
 *
 * Bug Report (2026-03-31):
 * - When CSS caching is enabled, graphics return 404
 * - URLs change from /themes/$app/default/graphics/$file.png to /static//graphics/$file.png
 * - Broke in horde/core v3.0.0beta4 => v3.0.0beta5 (commit 4309bce7)
 *
 * Root Cause:
 * - CssFile::resolveRelativeUrl() didn't handle absolute URLs (starting with /)
 * - Simple concatenation dirname($uri) . '/' . $relativeUrl caused issues
 * - No path normalization for double slashes or ../
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
 * @coversNothing
 */
class UserReportedBugDemoTest extends TestCase
{
    /**
     * This test demonstrates the EXACT scenario from the bug report.
     *
     * User reported:
     * - Cache OFF: /themes/horde/default/graphics/navi-arrow-normal.png (works)
     * - Cache ON: /static//graphics/navi-arrow-normal.png (404, double slash)
     *
     * The fix ensures that when CSS from /themes/horde/default/screen.css
     * contains url("graphics/file.png"), it resolves to the theme path,
     * not to /static/.
     */
    public function testUserReportedCachingBug(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');

        // This is what a typical Horde theme CSS file contains
        $cssContent = <<<CSS
            .head {
                background: url("graphics/head-bg.png");
            }
            .arrow {
                background: url("graphics/navi-arrow-normal.png");
            }
            .flag {
                background: url("graphics/flags/innocent.png");
            }
            .folder {
                background: url("graphics/folders/inbox.png");
            }
            CSS;

        file_put_contents($tempFile, $cssContent);

        // CSS file is registered with its theme URI
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $tempFile
        );

        // When dataurl encoding fails (file too large), the URL is returned as-is
        $urlsProcessed = [];
        $urlCallback = new UrlCallback(function (string $uri) use (&$urlsProcessed): string {
            $urlsProcessed[] = $uri;
            return $uri;  // Return unchanged (simulates no base64 encoding)
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // Verify the URLs were resolved correctly (the bug would cause wrong paths)
        echo "\n=== URLs Processed by Minifier ===\n";
        foreach ($urlsProcessed as $url) {
            echo "  $url\n";
        }

        echo "\n=== Generated CSS (minified) ===\n";
        echo substr($result, 0, 500) . "...\n\n";

        // ASSERTIONS: Verify bug is fixed

        // ✅ URLs should resolve to theme directories
        $this->assertContains('/themes/horde/default/graphics/head-bg.png', $urlsProcessed);
        $this->assertContains('/themes/horde/default/graphics/navi-arrow-normal.png', $urlsProcessed);
        $this->assertContains('/themes/horde/default/graphics/flags/innocent.png', $urlsProcessed);
        $this->assertContains('/themes/horde/default/graphics/folders/inbox.png', $urlsProcessed);

        // ❌ Should NOT contain /static/ paths (the bug)
        foreach ($urlsProcessed as $url) {
            $this->assertStringNotContainsString('/static/', $url, "URL should not contain /static/: $url");
        }

        // ❌ Should NOT contain double slashes (the bug symptom)
        foreach ($urlsProcessed as $url) {
            $this->assertStringNotContainsString('//', $url, "URL should not contain double slashes: $url");
        }

        unlink($tempFile);
    }

    /**
     * Test that absolute URLs in CSS are NOT modified.
     */
    public function testAbsoluteUrlsStayAbsolute(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');

        $cssContent = <<<CSS
            .test {
                background: url("/graphics/logo.png");
            }
            CSS;

        file_put_contents($tempFile, $cssContent);

        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $tempFile
        );

        $urlsProcessed = [];
        $urlCallback = new UrlCallback(function (string $uri) use (&$urlsProcessed): string {
            $urlsProcessed[] = $uri;
            return $uri;
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // Absolute URL should NOT be resolved relative to CSS location
        $this->assertSame(['/graphics/logo.png'], $urlsProcessed);
        $this->assertStringContainsString('/graphics/logo.png', $result);

        // Should NOT become /themes/horde/default/graphics/logo.png
        $this->assertStringNotContainsString('/themes/horde/default/graphics', $result);

        unlink($tempFile);
    }

    /**
     * Test path normalization handles ../ correctly.
     */
    public function testParentDirectoryResolution(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');

        $cssContent = <<<CSS
            .test {
                background: url("../images/bg.png");
            }
            CSS;

        file_put_contents($tempFile, $cssContent);

        // CSS in a subdirectory
        $cssFile = new CssFile(
            '/themes/horde/default/block/screen.css',
            $tempFile
        );

        $urlsProcessed = [];
        $urlCallback = new UrlCallback(function (string $uri) use (&$urlsProcessed): string {
            $urlsProcessed[] = $uri;
            return $uri;
        });

        $minifier = new CssParserMinifier(
            new FileCollectionInput($cssFile),
            new Settings(dataUrlCallback: $urlCallback)
        );

        $result = $minifier->minify();

        // ../images from /themes/horde/default/block/ should resolve to /themes/horde/default/images/
        $this->assertSame(['/themes/horde/default/images/bg.png'], $urlsProcessed);

        unlink($tempFile);
    }
}

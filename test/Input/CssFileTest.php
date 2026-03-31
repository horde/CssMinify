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

namespace Horde\CssMinify\Test\Input;

use Horde\CssMinify\Input\CssFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CssFile URL resolution.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 * @coversNothing
 */
class CssFileTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($this->tempFile, 'body { color: red; }');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testResolveRelativeUrl(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test relative URL resolution
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('graphics/logo.png')
        );
    }

    public function testResolveAbsoluteUrl(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test absolute URL - should NOT be modified
        $this->assertSame(
            '/graphics/logo.png',
            $cssFile->resolveRelativeUrl('/graphics/logo.png')
        );
    }

    public function testResolveProtocolRelativeUrl(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test protocol-relative URL - should NOT be modified
        $this->assertSame(
            '//cdn.example.com/logo.png',
            $cssFile->resolveRelativeUrl('//cdn.example.com/logo.png')
        );
    }

    public function testResolveParentDirectoryUrl(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test parent directory navigation
        $this->assertSame(
            '/themes/horde/images/logo.png',
            $cssFile->resolveRelativeUrl('../images/logo.png')
        );
    }

    public function testResolveCurrentDirectoryUrl(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test current directory reference
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('./graphics/logo.png')
        );
    }

    public function testNormalizeDoubleSlashes(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test that double slashes get normalized
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('graphics//logo.png')
        );
    }

    public function testResolveFromStaticDir(): void
    {
        $cssFile = new CssFile(
            '/static/abc123.css',
            $this->tempFile
        );

        // When CSS is in /static/, relative URLs resolve from there
        $this->assertSame(
            '/static/graphics/logo.png',
            $cssFile->resolveRelativeUrl('graphics/logo.png')
        );

        // But absolute URLs stay absolute
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('/themes/horde/default/graphics/logo.png')
        );
    }

    public function testResolveComplexPath(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/block/screen.css',
            $this->tempFile
        );

        // From block/ subdirectory, go up to default then to graphics
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('../graphics/logo.png')
        );
    }

    public function testResolveDeepNesting(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test nested directories
        $this->assertSame(
            '/themes/horde/default/graphics/tree/line.png',
            $cssFile->resolveRelativeUrl('graphics/tree/line.png')
        );
    }

    public function testUrlWithWhitespace(): void
    {
        $cssFile = new CssFile(
            '/themes/horde/default/screen.css',
            $this->tempFile
        );

        // Test that whitespace is properly trimmed
        $this->assertSame(
            '/themes/horde/default/graphics/logo.png',
            $cssFile->resolveRelativeUrl('  graphics/logo.png  ')
        );
    }

    /**
     * Regression test for bug where ltrim() removed leading slashes.
     * Bug caused absolute URLs like /graphics/file.png to become
     * graphics/file.png and be resolved as relative.
     */
    public function testAbsoluteUrlWithLeadingSlashNotStripped(): void
    {
        $cssFile = new CssFile(
            '/static/abc123.css',
            $this->tempFile
        );

        // The bug: ltrim('/graphics/file.png') === 'graphics/file.png'
        // Then dirname('/static/abc123.css') . '/' . 'graphics/file.png'
        // Results in: '/static/graphics/file.png' (WRONG!)
        //
        // Expected: '/graphics/file.png' (unchanged, it's absolute)
        $this->assertSame(
            '/graphics/file.png',
            $cssFile->resolveRelativeUrl('/graphics/file.png')
        );
    }
}

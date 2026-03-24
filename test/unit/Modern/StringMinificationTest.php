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
use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for modern string input minification (equivalent to CssParserTest).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(CssParserMinifier::class)]
class StringMinificationTest extends TestCase
{
    public function testMinifySimpleString(): void
    {
        $css = 'body { color: red; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('body', $result);
    }

    public function testMinifyCompressesWhitespace(): void
    {
        $css = "body {\n  color: red;\n  margin: 10px;\n}";
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        // Should be compressed
        $this->assertStringNotContainsString("\n", $result);
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
    }

    public function testMinifyRemovesComments(): void
    {
        $css = '/* This is a comment */ body { color: red; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringNotContainsString('comment', $result);
        $this->assertStringContainsString('color:red', $result);
    }

    public function testMinifyEmptyString(): void
    {
        $minifier = new CssParserMinifier(new StringInput(''));

        $result = $minifier->minify();

        $this->assertSame('', $result);
    }

    public function testMinifyMalformedCss(): void
    {
        $css = 'body { color: }'; // Missing value
        $minifier = new CssParserMinifier(new StringInput($css));

        // Should not throw, may log error
        $result = $minifier->minify();

        // Result may be empty or partial
        $this->assertIsString($result);
    }

    public function testMinifyPreservesValidCss(): void
    {
        $css = 'body { color: red; } .header { background: blue; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('body', $result);
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('.header', $result);
        $this->assertStringContainsString('background:blue', $result);
    }

    public function testMinifyHandlesMultipleRules(): void
    {
        $css = 'body { color: red; margin: 10px; padding: 5px; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
        $this->assertStringContainsString('padding:5px', $result);
    }

    public function testMinifyHandlesMediaQueries(): void
    {
        $css = '@media (min-width: 768px) { body { font-size: 16px; } }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('@media', $result);
        $this->assertStringContainsString('min-width', $result);
        $this->assertStringContainsString('font-size:16px', $result);
    }

    public function testMinifyHandlesComplexSelectors(): void
    {
        $css = '.button:hover { background: blue; } .nav > li { display: inline; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = $minifier->minify();

        $this->assertStringContainsString('.button:hover', $result);
        $this->assertStringContainsString('background:blue', $result);
        // Selector spacing may be preserved
        $this->assertTrue(
            strpos($result, '.nav>li') !== false || strpos($result, '.nav > li') !== false,
            'Should contain .nav selector'
        );
        $this->assertStringContainsString('display:inline', $result);
    }

    public function testToStringCallsMinify(): void
    {
        $css = 'body { color: blue; }';
        $minifier = new CssParserMinifier(new StringInput($css));

        $result = (string) $minifier;

        $this->assertStringContainsString('color:blue', $result);
    }
}

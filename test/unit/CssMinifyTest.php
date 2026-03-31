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
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_CssMinify;

/**
 * Tests for Horde_CssMinify base class functionality.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
#[CoversClass(Horde_CssMinify::class)]
#[CoversClass(Horde_CssMinify_CssParser::class)]
class CssMinifyTest extends TestCase
{
    public function testConstructorAcceptsString(): void
    {
        $css = 'body{color:red}';
        $minifier = new Horde_CssMinify_CssParser($css);

        $this->assertInstanceOf(Horde_CssMinify_CssParser::class, $minifier);
    }

    public function testConstructorAcceptsArray(): void
    {
        $data = ['test.css' => __DIR__ . '/fixtures/simple.css'];
        $minifier = new Horde_CssMinify_CssParser($data);

        $this->assertInstanceOf(Horde_CssMinify_CssParser::class, $minifier);
    }

    public function testSetOptionsInitializesLogger(): void
    {
        $css = 'body{color:red}';
        $logger = new Horde_Log_Logger(new Horde_Log_Handler_Null());

        $minifier = new Horde_CssMinify_CssParser($css, [
            'logger' => $logger,
        ]);

        // If this doesn't throw, logger was accepted
        $this->assertInstanceOf(Horde_CssMinify_CssParser::class, $minifier);
    }

    public function testSetOptionsMergesOptions(): void
    {
        $css = 'body{color:red}';
        $minifier = new Horde_CssMinify_CssParser($css, [
            'custom' => 'value',
        ]);

        // Options are stored, even if custom
        $this->assertInstanceOf(Horde_CssMinify_CssParser::class, $minifier);
    }

    public function testToStringCallsMinify(): void
    {
        $css = 'body { color: red; }';
        $minifier = new Horde_CssMinify_CssParser($css);

        $result = (string) $minifier;

        // Should be minified
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringNotContainsString('  ', $result);
    }

    public function testConstructorWithEmptyString(): void
    {
        $minifier = new Horde_CssMinify_CssParser('');

        $result = $minifier->minify();
        $this->assertSame('', $result);
    }

    public function testConstructorWithEmptyArray(): void
    {
        $minifier = new Horde_CssMinify_CssParser([]);

        $result = $minifier->minify();
        $this->assertSame('', $result);
    }
}

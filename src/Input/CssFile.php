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

namespace Horde\CssMinify\Input;

use InvalidArgumentException;

/**
 * Value object for a single CSS file.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
final class CssFile
{
    public function __construct(
        public readonly string $uri,
        public readonly string $filepath
    ) {
        if (!is_readable($filepath)) {
            throw new InvalidArgumentException(
                "File not readable: {$filepath}"
            );
        }
    }

    public function getContent(): string
    {
        return file_get_contents($this->filepath);
    }

    public function resolveRelativeUrl(string $relativeUrl): string
    {
        return dirname($this->uri) . '/' . $relativeUrl;
    }
}

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

namespace Horde\CssMinify;

use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Input\FileCollectionInput;

/**
 * Abstract base class for CSS minification.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
abstract class Minifier
{
    public function __construct(
        protected readonly StringInput|FileCollectionInput $input,
        protected readonly Settings $settings = new Settings()
    ) {}

    abstract public function minify(): string;

    public function __toString(): string
    {
        return $this->minify();
    }
}

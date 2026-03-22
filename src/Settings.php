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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Immutable configuration for CSS minification.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
final class Settings
{
    public function __construct(
        public readonly ?UrlCallback $dataUrlCallback = null,
        public readonly ?ImportCallback $importCallback = null,
        public readonly LoggerInterface $logger = new NullLogger()
    ) {}
}

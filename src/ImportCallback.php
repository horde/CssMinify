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

/**
 * Type-safe wrapper for import callback.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
final class ImportCallback
{
    /** @var callable(string): array{0: string, 1: string} */
    private $callback;

    /**
     * @param callable(string): array{0: string, 1: string} $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return array{0: string, 1: string} [uri, filename]
     */
    public function __invoke(string $path): array
    {
        return ($this->callback)($path);
    }
}

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

/**
 * Value object for a collection of CSS files.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
final class FileCollectionInput
{
    /** @var list<CssFile> */
    private array $files;

    public function __construct(CssFile ...$files)
    {
        $this->files = $files;
    }

    /** @return list<CssFile> */
    public function getFiles(): array
    {
        return $this->files;
    }
}

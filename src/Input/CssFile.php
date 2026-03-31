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
        // Trim whitespace from the URL
        $relativeUrl = trim($relativeUrl);

        // Absolute URLs (starting with /) should not be resolved
        if (str_starts_with($relativeUrl, '/')) {
            return $relativeUrl;
        }

        // Protocol-relative URLs (starting with //) should not be resolved
        if (str_starts_with($relativeUrl, '//')) {
            return $relativeUrl;
        }

        // Resolve relative URL against the CSS file's directory
        $baseDir = dirname($this->uri);
        $resolved = $baseDir . '/' . $relativeUrl;

        // Normalize path: remove double slashes and resolve . and ..
        return $this->normalizePath($resolved);
    }

    private function normalizePath(string $path): string
    {
        // Remove consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Split path and process . and .. components
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            // Skip empty parts (except leading slash creates one empty part)
            if ($part === '' && !empty($normalized)) {
                continue;
            }

            // Skip current directory references
            if ($part === '.') {
                continue;
            }

            // Handle parent directory references
            if ($part === '..') {
                // Don't pop past root
                if (!empty($normalized) && end($normalized) !== '') {
                    array_pop($normalized);
                }
                continue;
            }

            $normalized[] = $part;
        }

        return implode('/', $normalized);
    }
}

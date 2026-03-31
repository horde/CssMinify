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

use Horde\Css\Parser\Parser;
use Horde\Css\Parser\Import;
use Horde\Css\Parser\Url;
use Horde\CssMinify\Input\StringInput;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Input\CssFile;
use Psr\Log\LogLevel;
use Exception;
use InvalidArgumentException;

/**
 * CSS minification using modern Horde\Css\Parser API.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   CssMinify
 */
final class CssParserMinifier extends Minifier
{
    public function minify(): string
    {
        return match (true) {
            $this->input instanceof StringInput
                => $this->minifyString($this->input),
            $this->input instanceof FileCollectionInput
                => $this->minifyFiles($this->input),
        };
    }

    private function minifyString(StringInput $input): string
    {
        try {
            $parser = new Parser($input->css);
            return $parser->compress();
        } catch (Exception $e) {
            $this->settings->logger->log(
                LogLevel::ERROR,
                'CSS parse error: ' . $e->getMessage()
            );
            return $input->css;
        }
    }

    private function minifyFiles(FileCollectionInput $input): string
    {
        $output = '';

        foreach ($input->getFiles() as $file) {
            $output .= $this->minifyFile($file);
        }

        return $output;
    }

    private function minifyFile(CssFile $file): string
    {
        try {
            $css = $file->getContent();
            $parser = new Parser($css);

            // Process imports first
            $importOutput = '';
            $parser = $this->processImports($parser, $file, $importOutput);

            // Process URLs
            $parser = $this->processUrls($parser, $file);

            return $importOutput . $parser->compress();
        } catch (Exception $e) {
            $this->settings->logger->log(
                LogLevel::ERROR,
                "CSS parse error in {$file->filepath}: " . $e->getMessage()
            );
            return $file->getContent();
        }
    }

    private function processImports(
        Parser $parser,
        CssFile $sourceFile,
        string &$output
    ): Parser {
        $imports = $parser->getImports();

        if (empty($imports) || $this->settings->importCallback === null) {
            $output = '';
            return $parser;
        }

        // Remove imports from parser
        $parser = $parser->removeImports();

        // Recursively process imported files
        $output = '';
        foreach ($imports as $import) {
            $resolvedPath = $sourceFile->resolveRelativeUrl($import->url);
            [$uri, $filepath] = ($this->settings->importCallback)($resolvedPath);

            try {
                $importedFile = new CssFile($uri, $filepath);
                $output .= $this->minifyFile($importedFile);
            } catch (InvalidArgumentException $e) {
                $this->settings->logger->log(
                    LogLevel::ERROR,
                    "Could not read imported file {$filepath}: " . $e->getMessage()
                );
            }
        }

        return $parser;
    }

    private function processUrls(Parser $parser, CssFile $sourceFile): Parser
    {
        return $parser->modifyUrls(function (string $urlString) use ($sourceFile): string {
            // Trim whitespace only, preserve leading slashes
            $urlString = trim($urlString);

            // Skip absolute URLs and data URIs
            if (stripos($urlString, 'http') === 0 || $this->isDataUrl($urlString)) {
                return $urlString;
            }

            // Resolve relative URL to absolute path
            $resolved = $sourceFile->resolveRelativeUrl($urlString);

            // Apply dataurl callback if configured (converts to base64 or returns URI)
            if ($this->settings->dataUrlCallback !== null) {
                return ($this->settings->dataUrlCallback)($resolved);
            }

            // No callback: return the resolved absolute path
            return $resolved;
        });
    }

    private function isDataUrl(string $url): bool
    {
        return str_starts_with($url, 'data:');
    }
}

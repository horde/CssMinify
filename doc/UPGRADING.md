# Upgrading to Modern API (src/)

The modern API is very similar to legacy API but PSR-4 ready, PER-1 compliant, uses typed signatures and provides additional functionality missing from the lib/ API.
The old API is still available for upgrading scenarios but will eventually be dropped. Do not rely on the legacy API staying forever.

## Old API (lib/)

```php
$minifier = new Horde_CssMinify_CssParser($files, [
    'import' => function($path) {
        return [$uri, $filepath];
    },
    'dataurl' => function($path) {
        return $dataUrl;
    },
    'logger' => $logger
]);

$css = $minifier->minify();
```

## New API (src/)

```php
use Horde\CssMinify\CssParserMinifier;
use Horde\CssMinify\Input\{StringInput, FileCollectionInput, CssFile};
use Horde\CssMinify\{Settings, ImportCallback, UrlCallback};

// String input
$minifier = new CssParserMinifier(
    new StringInput($cssString),
    new Settings(logger: $logger)
);

// File input
$minifier = new CssParserMinifier(
    new FileCollectionInput(
        new CssFile('/css/main.css', '/path/to/main.css'),
        new CssFile('/css/theme.css', '/path/to/theme.css')
    ),
    new Settings(
        importCallback: new ImportCallback(fn($p) => [$uri, $filepath]),
        dataUrlCallback: new UrlCallback(fn($p) => $dataUrl),
        logger: $logger
    )
);

$css = $minifier->minify();
```

## Key Differences

- **Input**: Explicit `StringInput` or `FileCollectionInput` types
- **Files**: `CssFile` objects with URI and filepath data
- **Options**: `Settings` object with named properties
- **Callbacks**: Wrapped in `ImportCallback` and `UrlCallback` for type safety
- **Types**: All parameters and returns have explicit types

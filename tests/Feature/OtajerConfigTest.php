<?php

/**
 * The store's ERP ("otajer") host was a string literal at 19 sites in 14 files,
 * and its API token a literal in AllProductsCommand. Both come from
 * config/services.php now (`otajer.url`, `otajer.token`).
 */
test('the ERP host defaults to the production host and the token is not defaulted', function () {
    expect(config('services.otajer.url'))->toBe('https://stores.otajer.com')
        ->and(config('services.otajer.token'))->toBeNull();
});

test('no source file names the ERP host or carries a token in an ERP url', function () {
    $offenders = [];

    foreach (sourceFilesUnder(['app', 'routes', 'resources/views', 'database']) as $path => $source) {
        if (str_contains($source, 'stores.otajer.com')) {
            $offenders[] = "$path: names the host; use config('services.otajer.url')";
        }

        if (preg_match('#/api/rest/[^\'"\s]*[0-9A-Fa-f]{32}#', $source)) {
            $offenders[] = "$path: a token in an /api/rest/ url; use config('services.otajer.token')";
        }
    }

    expect($offenders)->toBe([]);
});

<?php

/**
 * Pint's `notPath` (pint.json) and PHPStan's `excludePaths` (phpstan.neon)
 * scope the same merged legacy code out of both tools, so the two lists must
 * name the same files. A file in only one of them is either unformatted but
 * analysed or formatted but never analysed. WMS-owned files must be in neither.
 *
 * @return array{pint: list<string>, phpstan: list<string>}
 */
function staticAnalysisExclusions(): array
{
    $pint = json_decode((string) file_get_contents(base_path('pint.json')), true)['notPath'];

    $neon = (string) file_get_contents(base_path('phpstan.neon'));
    preg_match_all('/^\s+- (\S+?)(?: \(\?\))?$/m', substr($neon, (int) strpos($neon, 'excludePaths:')), $matches);

    return ['pint' => $pint, 'phpstan' => $matches[1]];
}

test('pint and phpstan exclude the same files', function () {
    ['pint' => $pint, 'phpstan' => $phpstan] = staticAnalysisExclusions();

    expect(array_diff($pint, $phpstan))->toBe([])
        ->and(array_diff($phpstan, $pint))->toBe([]);
});

test('the WMS AppServiceProvider is analysed by both tools', function () {
    ['pint' => $pint, 'phpstan' => $phpstan] = staticAnalysisExclusions();

    expect($pint)->not->toContain('app/Providers/AppServiceProvider.php')
        ->and($phpstan)->not->toContain('app/Providers/AppServiceProvider.php');
});

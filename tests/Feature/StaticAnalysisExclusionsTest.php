<?php

/**
 * The legacy store's files are excluded from Pint (`pint.json`'s `notPath`) and
 * from PHPStan (`phpstan.neon`'s `excludePaths`) one by one, since they share
 * directories with this app's own code. The two lists describe the same set of
 * not-yet-converted files, so a file brought under one tool but left out of the
 * other — or deleted while still listed — means the halves drift apart again.
 *
 * @return list<string>
 */
function pintExcludedPaths(): array
{
    /** @var array{notPath: list<string>} $config */
    $config = json_decode((string) file_get_contents(base_path('pint.json')), true, flags: JSON_THROW_ON_ERROR);

    return $config['notPath'];
}

/**
 * @return list<string>
 */
function phpstanExcludedPaths(): array
{
    $neon = (string) file_get_contents(base_path('phpstan.neon'));

    preg_match('/^    excludePaths:\n((?:        - .+\n|\s*#.*\n)+)/m', $neon, $block);
    preg_match_all('/^        - (\S+)$/m', $block[1] ?? '', $paths);

    return $paths[1];
}

test('pint and phpstan exclude exactly the same legacy files', function () {
    $pint = pintExcludedPaths();
    $phpstan = phpstanExcludedPaths();

    expect($phpstan)->not->toBeEmpty()
        ->and(array_values(array_diff($pint, $phpstan)))->toBe([])
        ->and(array_values(array_diff($phpstan, $pint)))->toBe([]);
});

test('every excluded path still exists', function () {
    $missing = array_values(array_filter(
        array_unique([...pintExcludedPaths(), ...phpstanExcludedPaths()]),
        fn (string $path): bool => ! file_exists(base_path($path)),
    ));

    expect($missing)->toBe([]);
});

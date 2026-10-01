<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Trade licences saved by the legacy store sit in public/uploads/licenses, which
 * the web server hands to anyone who guesses a name. `store:move-trade-licenses`
 * moves them onto the private disk, where TradeLicenseStorage serves them.
 */
function legacyLicenseDirectory(string $publicPath): string
{
    File::ensureDirectoryExists("$publicPath/uploads/licenses");

    return "$publicPath/uploads/licenses";
}

test('licences are moved off the web root onto the private disk under the same names', function () {
    Storage::fake('local');

    withScratchPublicPath(function (string $publicPath) {
        $directory = legacyLicenseDirectory($publicPath);
        file_put_contents("$directory/licence.pdf", 'first');
        file_put_contents("$directory/Other Licence.png", 'second');

        $this->artisan('store:move-trade-licenses')->assertSuccessful();

        expect(glob("$directory/*"))->toBe([])
            ->and(Storage::disk('local')->get('uploads/licenses/licence.pdf'))->toBe('first')
            ->and(Storage::disk('local')->get('uploads/licenses/Other Licence.png'))->toBe('second');
    });
});

test('a dry run lists what it would move and moves nothing', function () {
    Storage::fake('local');

    withScratchPublicPath(function (string $publicPath) {
        $directory = legacyLicenseDirectory($publicPath);
        file_put_contents("$directory/licence.pdf", 'first');

        $this->artisan('store:move-trade-licenses --dry-run')->expectsOutputToContain('Would move 1')->assertSuccessful();

        expect(file_exists("$directory/licence.pdf"))->toBeTrue()
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });
});

test('a name that is already on the private disk is never overwritten, and is reported', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/licenses/licence.pdf', 'private copy');

    withScratchPublicPath(function (string $publicPath) {
        $directory = legacyLicenseDirectory($publicPath);
        file_put_contents("$directory/licence.pdf", 'web copy');
        file_put_contents("$directory/new.pdf", 'new');

        $this->artisan('store:move-trade-licenses')->expectsOutputToContain('licence.pdf already exists')->assertFailed();

        expect(file_get_contents("$directory/licence.pdf"))->toBe('web copy')
            ->and(Storage::disk('local')->get('uploads/licenses/licence.pdf'))->toBe('private copy')
            ->and(file_exists("$directory/new.pdf"))->toBeFalse()
            ->and(Storage::disk('local')->get('uploads/licenses/new.pdf'))->toBe('new');
    });
});

test('a missing licence directory is not an error', function () {
    Storage::fake('local');

    withScratchPublicPath(function () {
        $this->artisan('store:move-trade-licenses')->expectsOutputToContain('Nothing to move')->assertSuccessful();
    });
});

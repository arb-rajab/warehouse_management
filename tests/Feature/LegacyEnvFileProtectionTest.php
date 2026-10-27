<?php

use App\Http\Controllers\BusinessSettingsController;

/**
 * The store's settings screens used to rewrite the server's `.env` from the
 * request: any key, any value, no escaping, and no permission check on the POST
 * routes (only the pages were gated), behind a DEMO_MODE guard that read env()
 * and so never held once config was cached. Any admin, staff or accountant
 * could replace APP_KEY, the database credentials or WMS_TOKEN. Nothing in the
 * panel writes it now.
 *
 * Each test points the base path at a scratch directory holding its own `.env`,
 * so a regression rewrites that file and never a real one.
 */
function withScratchEnvFile(string $contents, Closure $callback): void
{
    $directory = sys_get_temp_dir().'/env-file-protection-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents("$directory/.env", $contents);
    $originalBasePath = app()->basePath();
    app()->setBasePath($directory);

    try {
        $callback("$directory/.env");
    } finally {
        app()->setBasePath($originalBasePath);
        unlink("$directory/.env");
        rmdir($directory);
    }
}

function scratchEnvContents(): string
{
    return 'APP_NAME="'.env('APP_NAME').'"'."\nDB_PASSWORD=\"secret\"\nWMS_TOKEN=\"known\"\n";
}

// The refusing overWriteEnvFile() stub went with its last callers, the
// generic settings writer's `site_name` and `timezone` branches
// (.ai/archive/store-backoffice/changes-20261013.md §20): no settings code can reach the file.
test('no settings writer is left that could rewrite the .env file', function () {
    $original = scratchEnvContents();
    actingAsStoreUser(createStoreStaff('general_settings', 'footer_setup', 'website_appearance', 'edit_website_page'));

    withScratchEnvFile($original, function (string $path) use ($original) {
        expect(method_exists(BusinessSettingsController::class, 'overWriteEnvFile'))->toBeFalse();

        $this->post(retiredSettingsWriterUri(), [
            'types' => ['site_name', 'timezone', 'APP_KEY'],
            'site_name' => 'Hacked',
            'timezone' => "x\"\nAPP_KEY=base64:attacker",
            'APP_KEY' => 'base64:attacker',
        ])->assertNotFound();

        expect(file_get_contents($path))->toBe($original);
    });
});

test('a staff user cannot rewrite the .env file through the settings screens', function () {
    $original = scratchEnvContents();
    actingAsStoreUser(createStoreUser('staff'));

    withScratchEnvFile($original, function (string $path) use ($original) {
        $this->post(route('env_key_update.update'), [
            'types' => ['APP_NAME', 'DB_PASSWORD', 'NEW_KEY'],
            'APP_NAME' => 'Hacked',
            'DB_PASSWORD' => "x\"\nWMS_TOKEN=\"attacker\"",
            'NEW_KEY' => 'appended',
        ])->assertRedirect()->assertSessionHas('flash_notification');

        expect(file_get_contents($path))->toBe($original);
    });
});

test('a customer is refused the .env settings route and nothing is written', function () {
    $original = scratchEnvContents();
    actingAsStoreUser(createStoreUser('customer'));

    withScratchEnvFile($original, function (string $path) use ($original) {
        $this->post(route('env_key_update.update'), ['types' => ['APP_NAME'], 'APP_NAME' => 'Hacked'])->assertNotFound();

        expect(file_get_contents($path))->toBe($original);
    });
});

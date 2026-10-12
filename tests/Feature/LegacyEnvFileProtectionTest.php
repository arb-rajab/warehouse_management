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

test('overWriteEnvFile neither rewrites an existing key nor appends a new one', function () {
    $original = scratchEnvContents();

    withScratchEnvFile($original, function (string $path) use ($original) {
        $controller = app(BusinessSettingsController::class);

        expect($controller->overWriteEnvFile('APP_NAME', 'Hacked'))->toBeFalse()
            ->and($controller->overWriteEnvFile('INJECTED', "x\"\nAPP_KEY=base64:attacker"))->toBeFalse()
            ->and(file_get_contents($path))->toBe($original);
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

test('the activation toggles that lived in .env report a failure and write nothing', function (string $type) {
    $original = scratchEnvContents();
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));

    withScratchEnvFile($original, function (string $path) use ($original, $type) {
        $this->post(route('business_settings.update.activation'), ['type' => $type, 'value' => 1])
            ->assertOk()
            ->assertContent('0');

        expect(file_get_contents($path))->toBe($original);
    });
})->with(['FORCE_HTTPS', 'FILESYSTEM_DRIVER']);

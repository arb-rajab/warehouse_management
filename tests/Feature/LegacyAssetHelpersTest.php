<?php

use App\Models\Upload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

/**
 * Regression test for the webshell-incident fix in
 * static_asset()/my_asset() (app/Http/Helpers.php) — see
 * .ai/rules/legacy-albaraka-holland.md section 0.2. Both used to prepend
 * 'public/' to every asset URL, which only made sense under the legacy
 * layout where the project root itself was the document root. This app
 * serves from public/ already, so that prefix pointed at a path that
 * doesn't exist here — and prepending a literal path segment onto a URL
 * generator's output is exactly the kind of thing that becomes a real
 * problem the moment any code treats it as a filesystem path instead
 * (which is how the legacy repo's addons ended up writable under
 * public/addons/ in the first place).
 */
test('static_asset() does not prepend public/ to the generated URL', function () {
    $url = static_asset('assets/img/logo.png');

    expect($url)
        ->not->toContain('/public/')
        ->toEndWith('/assets/img/logo.png');
});

test('my_asset() does not prepend public/ to the generated URL', function () {
    // FILESYSTEM_DRIVER (not this app's own FILESYSTEM_DISK) is never set,
    // so my_asset() always takes the non-S3 branch — see that function's
    // own env() call.
    $url = my_asset('assets/img/logo.png');

    expect($url)
        ->not->toContain('/public/')
        ->toEndWith('/assets/img/logo.png');
});

test('uploaded_asset() resolves a missing upload to the placeholder image, still without public/', function () {
    $url = uploaded_asset(null);

    expect($url)
        ->not->toContain('/public/')
        ->toEndWith('/assets/img/placeholder.jpg');
});

test('uploaded_asset() resolves an upload with only a file_name through my_asset(), without public/', function () {
    $upload = Upload::create(['file_name' => 'products/widget.jpg']);

    $url = uploaded_asset($upload->id);

    expect($url)
        ->not->toContain('/public/')
        ->toEndWith('/products/widget.jpg');
});

test('uploaded_asset() returns an upload\'s external_link as-is', function () {
    // external_link isn't in Upload::$fillable (see that model's own
    // docblock), and this app runs strict mode in testing, so
    // forceCreate() rather than create() here.
    $upload = Upload::forceCreate(['external_link' => 'https://cdn.example.com/widget.jpg']);

    expect(uploaded_asset($upload->id))->toBe('https://cdn.example.com/widget.jpg');
});

/**
 * Legacy theme bundles remain prebuilt assets loaded through static_asset().
 * The shared image compressor is a separate Vite entry and does not boot
 * Inertia. Theme references still need files under public/. Only literal paths are
 * checked: the ones built from a variable (flags, uploads, card images per
 * gateway) can't be resolved statically.
 */
test('every literal theme bundle the legacy views reference exists under public/', function () {
    $missing = [];

    foreach (File::allFiles(resource_path('views/shop')) as $view) {
        preg_match_all(
            '/static_asset\(\s*[\'"]((?:assets\/(?:js|css)|download)\/[^\'"]+\.[a-z0-9]+)[\'"]/i',
            $view->getContents(),
            $matches,
        );

        foreach ($matches[1] as $path) {
            if (! file_exists(public_path($path))) {
                $missing[$path] = $view->getRelativePathname();
            }
        }
    }

    expect($missing)->toBe([]);
});

test('the legacy login page loads theme bundles and the standalone compressor without the Inertia app', function () {
    $response = $this->get('/temp/users/login');

    $response->assertOk();
    $response->assertSee('/assets/css/vendors.css', false);
    $response->assertSee('/assets/js/vendors.js', false);
    $response->assertSee('/assets/js/aiz-core.js', false);
    $response->assertDontSee('data-page=', false);
    $response->assertSee(Vite::asset('resources/js/legacy-image-upload.ts'), false);
    $response->assertDontSee(Vite::asset('resources/js/app.ts'), false);
    $response->assertDontSee(Vite::asset('resources/css/app.css'), false);
    expect($response->headers->has('X-Inertia'))->toBeFalse();
});

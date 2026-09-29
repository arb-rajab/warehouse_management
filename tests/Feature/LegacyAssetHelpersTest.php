<?php

use App\Models\Upload;

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

<?php

use App\Models\Upload;
use Illuminate\Support\Facades\Route;

/**
 * The admin "delete every upload" action was a GET link anyone signed in to the
 * panel could follow (or be tricked into following), and several views printed
 * admin-editable HTML without sanitising it.
 */
function uploadOwnedBy(int $userId): Upload
{
    return Upload::factory()->stored('uploads/all/mine.png')->create(['user_id' => $userId, 'type' => 'image', 'extension' => 'png']);
}

test('the delete-all-uploads route only answers POST', function () {
    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => str_ends_with($route->uri(), 'admin/all-file'));

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST']);
});

test('a guest cannot delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);

    $this->post('/temp/admin/all-file')->assertRedirect();

    expect(Upload::count())->toBe(1);
});

test('a staff member without the permission cannot delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);
    actingAsStoreUser(createStoreUser('staff'));

    $response = $this->post('/temp/admin/all-file');

    expect($response->status())->toBeIn([302, 403])
        ->and(Upload::count())->toBe(1);
});

test('a super admin can delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);

    withScratchPublicPath(function (string $publicPath) {
        file_put_contents("$publicPath/uploads/all/mine.png", tinyPng());
        actingAsStoreUser(createStoreAdmin());

        $this->post('/temp/admin/all-file')->assertRedirect();

        expect(Upload::count())->toBe(0)
            ->and(storedUploadFiles($publicPath))->toBe([]);
    });
});

test('admin-editable html is sanitised before the views print it unescaped', function (string $view, string $expression) {
    $contents = file_get_contents(resource_path("views/$view"));

    expect($contents)->toContain('{!! clean_html('.$expression.') !!}')
        ->and($contents)->not->toContain('{!! '.$expression.' !!}');
})->with([
    'blog body' => ['shop/frontend/blog/details.blade.php', '$blog->description'],
    'custom page body' => ['shop/frontend/m_custom_page.blade.php', "\$page->getTranslation('content')"],
    'popup content' => ['shop/frontend/layouts/app.blade.php', "get_setting('website_popup_content')"],
    'footer about us' => ['shop/frontend/inc/footer.blade.php', "get_setting('about_us_description',null,App::getLocale())"],
    'footer copyright' => ['shop/frontend/inc/footer.blade.php', "get_setting('frontend_copyright_text', null, App::getLocale())"],
]);

test('the page editor prints its values escaped', function () {
    expect(file_get_contents(resource_path('views/shop/backend/website_settings/pages/edit.blade.php')))->not->toContain('{!!');
});

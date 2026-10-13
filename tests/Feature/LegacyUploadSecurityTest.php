<?php

use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\Api\V2\AizUploadController as ApiAizUploadController;
use App\Http\Controllers\Api\V2\Seller\SellerFileUploadController;
use App\Models\Page;
use App\Models\Upload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The admin "delete every upload" action was a GET link anyone signed in to the
 * panel could follow (or be tricked into following), and the page editor printed
 * its values unescaped.
 */
function uploadOwnedBy(int $userId): Upload
{
    return Upload::factory()->stored('uploads/all/mine.png')->create(['user_id' => $userId, 'type' => 'image', 'extension' => 'png']);
}

test('legacy uploader controllers cannot delete every upload', function (string $controller) {
    expect(method_exists($controller, 'all_file'))->toBeFalse();
})->with([
    'the shared Blade uploader' => [AizUploadController::class],
    'the mobile API uploader' => [ApiAizUploadController::class],
    'the seller API uploader' => [SellerFileUploadController::class],
]);

test('downloading an upload that does not exist is a not-found, not a null dereference', function (string $controller) {
    uploadOwnedBy(createStoreUser('customer')->id);

    expect(fn () => app($controller)->attachment_download(999999))->toThrow(ModelNotFoundException::class);
})->with([
    'the web uploader' => [AizUploadController::class],
    'the mobile API uploader' => [ApiAizUploadController::class],
    'the seller API uploader' => [SellerFileUploadController::class],
]);

// The Blade page editor is gone (store-backoffice-migration.md §20): its
// Inertia successor prints every value through Vue's escaped interpolation,
// never raw HTML, and receives its content cleaned.
test('the page editor prints its values escaped', function () {
    expect(view()->exists('shop.backend.website_settings.pages.edit'))->toBeFalse();
    foreach (['pages/Store/Admin/Website/Pages/Edit.vue', 'pages/Store/Admin/Website/Pages/Create.vue', 'components/StoreWebsitePageFormFields.vue'] as $file) {
        expect(file_get_contents(resource_path('js/'.$file)))->not->toContain('v-html')->not->toContain('innerHTML');
    }
    Page::forceCreate(['type' => 'custom_page', 'title' => '<script>alert(1)</script>', 'slug' => 'escaped', 'content' => '<p>Kept</p><img src="x" onerror="alert(3)">', 'meta_title' => '"><script>alert(2)</script>']);
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $html = $this->get(route('custom-pages.edit', 'escaped'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('title', '<script>alert(1)</script>')->where('content', '<p>Kept</p><img src="x">'))
        ->getContent();
    expect($html)->not->toContain('<script>alert(1)</script>')->not->toContain('<script>alert(2)</script>')->not->toContain('onerror');
});

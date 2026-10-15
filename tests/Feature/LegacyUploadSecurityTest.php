<?php

use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\Api\V2\AizUploadController as ApiAizUploadController;
use App\Http\Controllers\Api\V2\Seller\SellerFileUploadController;
use App\Models\Upload;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

test('the page editor prints its values escaped', function () {
    expect(file_get_contents(resource_path('views/shop/backend/website_settings/pages/edit.blade.php')))->not->toContain('{!!');
});

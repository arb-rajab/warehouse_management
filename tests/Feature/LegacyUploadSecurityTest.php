<?php

use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\Api\V2\AizUploadController as ApiAizUploadController;
use App\Http\Controllers\Api\V2\Seller\SellerFileUploadController;
use App\Models\Upload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * The admin "delete every upload" action was a GET link anyone signed in to the
 * panel could follow (or be tricked into following), and the page editor printed
 * its values unescaped.
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

test('only the admin controller can delete every upload', function (string $controller) {
    expect(method_exists($controller, 'all_file'))->toBeFalse();
})->with([
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

test('a guest cannot delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);

    $this->post('/temp/admin/all-file')->assertRedirect();

    expect(Upload::count())->toBe(1);
});

test('a staff member without the permission cannot delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);
    actingAsStoreUser(createStoreUser('staff'));

    $this->post('/temp/admin/all-file')->assertForbidden();

    expect(Upload::count())->toBe(1);
});

test('a staff member granted the permission can delete every upload', function () {
    uploadOwnedBy(createStoreUser('customer')->id);
    Permission::findOrCreate('delete_all_uploads', 'store');
    $staff = createStoreUser('staff');
    $staff->givePermissionTo('delete_all_uploads');

    withScratchPublicPath(function (string $publicPath) use ($staff) {
        file_put_contents("$publicPath/uploads/all/mine.png", tinyPng());
        actingAsStoreUser($staff);

        $this->post('/temp/admin/all-file')->assertRedirect();

        expect(Upload::count())->toBe(0)
            ->and(storedUploadFiles($publicPath))->toBe([]);
    });
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

test('the page editor prints its values escaped', function () {
    expect(file_get_contents(resource_path('views/shop/backend/website_settings/pages/edit.blade.php')))->not->toContain('{!!');
});

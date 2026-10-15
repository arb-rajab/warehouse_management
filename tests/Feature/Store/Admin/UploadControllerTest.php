<?php

use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function adminUpload(array $overrides = []): Upload
{
    return Upload::factory()->stored('uploads/all/photo.webp')->create([
        'file_original_name' => 'Photo', 'type' => 'image', 'file_size' => 42,
        'user_id' => createStoreUser('customer')->id, ...$overrides,
    ]);
}

test('the library and upload screens can render', function (string $path, string $permission, string $component) {
    actingAsStoreUser(createStoreStaff($permission));
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/temp/admin/uploads', 'view_uploads', 'Store/Admin/Uploads/Index'],
    ['/temp/admin/uploads/create', 'upload_files', 'Store/Admin/Uploads/Create'],
]);

test('every upload action refuses unauthorized callers without changing files', function (string $caller, string $method, string $path) {
    $file = adminUpload();
    actingAsRefusedStoreCaller($caller);
    withScratchPublicPath(function (string $publicPath) use ($caller, $method, $path, $file) {
        file_put_contents($publicPath.'/'.$file->file_name, tinyPng());
        $response = $this->call($method, str_replace('{id}', (string) $file->id, $path), ['ids' => (string) $file->id, 'id' => [$file->id]]);
        assertStoreCallerRefused($response, $caller);
        expect(Upload::count())->toBe(1)->and(is_file($publicPath.'/'.$file->file_name))->toBeTrue();
    });
})->with('refused store admin callers')->with([
    ['GET', '/temp/admin/uploads'], ['GET', '/temp/admin/uploads/create'], ['GET', '/temp/admin/uploads/files'], ['GET', '/temp/admin/uploads/previews'],
    ['POST', '/temp/admin/uploads'], ['DELETE', '/temp/admin/uploads/{id}'], ['POST', '/temp/admin/bulk-uploaded-files-delete'], ['POST', '/temp/admin/all-file'],
]);

test('WMS authentication does not grant store upload access', function (string $method, string $path) {
    $file = adminUpload();
    $this->actingAs(User::factory()->create(), 'web');
    $this->call($method, str_replace('{id}', (string) $file->id, $path))->assertRedirect(route('shop.login'));
    expect(Upload::count())->toBe(1);
})->with([['GET', '/temp/admin/uploads'], ['GET', '/temp/admin/uploads/files'], ['GET', '/temp/admin/uploads/previews'], ['POST', '/temp/admin/uploads'], ['DELETE', '/temp/admin/uploads/{id}']]);

test('the list is paginated and the upload payload is narrow', function () {
    config(['store.asset_base_url' => 'https://store.example']);
    actingAsStoreUser(createStoreStaff('view_uploads'));
    $file = adminUpload();
    foreach (range(1, 22) as $n) {
        adminUpload(['file_original_name' => 'Noise '.$n]);
    }
    $response = $this->getJson('/temp/admin/uploads/files?sort=oldest')->assertOk();
    expect($response->json('data'))->toHaveCount(20)->and($response->json('meta.total'))->toBe(23)
        ->and($response->json('data.0'))->toEqual(['id' => $file->id, 'name' => 'Photo', 'type' => 'image', 'size' => 42, 'url' => 'https://store.example/uploads/all/photo.webp']);
    $this->getJson('/temp/admin/uploads/files?page=2')->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/temp/admin/uploads/files?per_page=10')->assertOk()->assertJsonCount(10, 'data');
    $this->getJson('/temp/admin/uploads/files?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('search and type filtering precede pagination with stable sorting', function () {
    actingAsStoreUser(createStoreStaff('view_uploads'));
    $a = adminUpload(['file_original_name' => 'a_ photo', 'file_size' => 100]);
    $b = adminUpload(['file_original_name' => 'b_ photo', 'file_size' => 10]);
    adminUpload(['file_original_name' => 'axb photo']);
    adminUpload(['file_original_name' => 'c_ doc', 'type' => 'document']);
    foreach (['newest' => [$b->id, $a->id], 'oldest' => [$a->id, $b->id], 'smallest' => [$b->id, $a->id], 'largest' => [$a->id, $b->id]] as $sort => $ids) {
        expect($this->getJson('/temp/admin/uploads/files?search=_&type=image&sort='.$sort)->assertOk()->json('data.*.id'))->toBe($ids);
    }
    foreach (['search' => ['x'], 'sort' => 'bad', 'type' => 'bad', 'page' => 0] as $key => $value) {
        $this->getJson('/temp/admin/uploads/files?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }
});

test('chosen previews preserve order, omit noise and refuse unsafe URLs', function () {
    actingAsStoreUser(createStoreStaff('view_uploads'));
    $a = adminUpload();
    $b = adminUpload();
    adminUpload();
    $b->forceFill(['external_link' => 'javascript:alert(1)'])->save();
    $this->getJson('/temp/admin/uploads/previews?ids='.$b->id.','.$a->id)->assertOk()->assertJsonCount(2)->assertJsonPath('0.id', $b->id)->assertJsonPath('0.url', null)->assertJsonPath('1.id', $a->id);
    $this->getJson('/temp/admin/uploads/previews?ids=abc')->assertUnprocessable();
    $this->getJson('/temp/admin/uploads/previews?ids='.$a->id.','.$a->id)->assertUnprocessable();
    $this->getJson('/temp/admin/uploads/previews?ids=999999')->assertOk()->assertExactJson([]);
});

test('upload uses the service and signed-in owner and rejects invalid or oversized files', function () {
    $user = actingAsStoreUser(createStoreStaff('upload_files'));
    withScratchPublicPath(function (string $publicPath) use ($user) {
        $this->postJson('/temp/admin/uploads', ['file' => uploadedFile('photo.png', tinyPng())])->assertCreated()->assertJsonPath('name', 'photo')->assertJsonPath('type', 'image');
        $upload = Upload::sole();
        expect($upload->user_id)->toBe($user->id)->and($upload->extension)->toBe('webp')->and(getimagesize($publicPath.'/'.$upload->file_name)['mime'])->toBe('image/webp');
        foreach ([uploadedFile('bad.png', '<html><script>alert(1)</script></html>'), uploadedFile('bad.pdf', 'not a pdf'), UploadedFile::fake()->create('big.zip', 512 * 1024 + 1)] as $file) {
            $this->postJson('/temp/admin/uploads', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->postJson('/temp/admin/uploads', [])->assertUnprocessable()->assertJsonValidationErrors('file');
        expect(Upload::count())->toBe(1)->and(storedUploadFiles($publicPath))->toHaveCount(1);
    });
});

test('read and write permissions do not imply each other', function () {
    $file = adminUpload();
    actingAsStoreUser(createStoreStaff('view_uploads'));
    $this->postJson('/temp/admin/uploads', [])->assertForbidden();
    $this->deleteJson('/temp/admin/uploads/'.$file->id)->assertForbidden();
    actingAsStoreUser(createStoreStaff('upload_files'));
    $this->getJson('/temp/admin/uploads/files')->assertForbidden();
    $this->getJson('/temp/admin/uploads/previews?ids='.$file->id)->assertForbidden();
    expect(Upload::count())->toBe(1);
});

test('deletion removes only the chosen row and file and missing IDs 404', function () {
    actingAsStoreUser(createStoreStaff('delete_uploads'));
    $file = adminUpload();
    $noise = adminUpload(['file_name' => 'uploads/all/noise.webp']);
    withScratchPublicPath(function (string $publicPath) use ($file, $noise) {
        file_put_contents($publicPath.'/'.$file->file_name, tinyPng());
        file_put_contents($publicPath.'/'.$noise->file_name, tinyPng());
        $this->deleteJson('/temp/admin/uploads/'.$file->id)->assertOk()->assertExactJson(['deleted' => $file->id]);
        expect(Upload::whereKey($file->id)->exists())->toBeFalse()->and(Upload::whereKey($noise->id)->exists())->toBeTrue()
            ->and(is_file($publicPath.'/'.$file->file_name))->toBeFalse()->and(is_file($publicPath.'/'.$noise->file_name))->toBeTrue();
    });
    $this->deleteJson('/temp/admin/uploads/999999')->assertNotFound();
});

test('bulk deletion validates all IDs before any write', function () {
    actingAsStoreUser(createStoreStaff('delete_uploads'));
    $file = adminUpload();
    $this->postJson('/temp/admin/bulk-uploaded-files-delete', ['id' => [$file->id, 999999]])->assertUnprocessable()->assertJsonValidationErrors('id.1');
    expect(Upload::count())->toBe(1);
    $this->postJson('/temp/admin/bulk-uploaded-files-delete', ['id' => [$file->id]])->assertOk()->assertExactJson(['deleted' => [$file->id]]);
    expect(Upload::count())->toBe(0);
});

test('demo mode refuses all upload writes without changing rows', function (string $method, string $path) {
    config(['store.demo_mode' => true]);
    $file = adminUpload();
    actingAsStoreUser(createStoreAdmin());
    $this->call($method, str_replace('{id}', (string) $file->id, $path), ['id' => [$file->id]], [], ['file' => uploadedFile('photo.png', tinyPng())])->assertForbidden();
    expect(Upload::count())->toBe(1);
})->with([['POST', '/temp/admin/uploads'], ['DELETE', '/temp/admin/uploads/{id}'], ['POST', '/temp/admin/bulk-uploaded-files-delete'], ['POST', '/temp/admin/all-file']]);

test('deletion cannot follow a stored path outside the upload directory', function () {
    actingAsStoreUser(createStoreStaff('delete_uploads'));
    withScratchPublicPath(function (string $publicPath) {
        foreach (['../index.php', 'uploads/all/../../index.php', 'index.php'] as $path) {
            $file = adminUpload(['file_name' => $path]);
            $this->deleteJson('/temp/admin/uploads/'.$file->id)->assertOk();
        }
        expect(file_get_contents($publicPath.'/index.php'))->toBe('<?php // sentinel');
    });
});

test('a refused storage write never creates an upload row', function () {
    config(['filesystems.default' => 's3']);
    actingAsStoreUser(createStoreStaff('upload_files'));
    Storage::shouldReceive('disk')->with('s3')->once()->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('put')->once()->andReturn(false);
    $this->postJson('/temp/admin/uploads', ['file' => uploadedFile('notes.txt', 'Delivery notes')])->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(Upload::count())->toBe(0);
});

test('failed remote deletion keeps the upload row', function () {
    config(['filesystems.default' => 's3']);
    actingAsStoreUser(createStoreStaff('delete_uploads'));
    $file = adminUpload();
    Storage::shouldReceive('disk')->with('s3')->once()->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('delete')->with($file->file_name)->once()->andReturn(false);
    $this->deleteJson('/temp/admin/uploads/'.$file->id)->assertServerError();
    expect(Upload::whereKey($file->id)->exists())->toBeTrue();
});

test('an accountant permission does not bypass upload ownership', function () {
    $accountant = createStoreUser('accountant');
    foreach (['view_uploads', 'delete_uploads'] as $permission) {
        $accountant->givePermissionTo($permission);
    }
    actingAsStoreUser($accountant);
    $own = adminUpload(['user_id' => $accountant->id]);
    $foreign = adminUpload();
    $this->getJson('/temp/admin/uploads/files')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    $this->getJson('/temp/admin/uploads/previews?ids='.$foreign->id)->assertForbidden();
    $this->deleteJson('/temp/admin/uploads/'.$foreign->id)->assertForbidden();
    $this->postJson('/temp/admin/bulk-uploaded-files-delete', ['id' => [$own->id, $foreign->id]])->assertForbidden();
    expect(Upload::count())->toBe(2);
    $this->getJson('/temp/admin/uploads/previews?ids='.$own->id)->assertOk()->assertJsonPath('0.id', $own->id);
    $this->deleteJson('/temp/admin/uploads/'.$own->id)->assertOk();
    expect(Upload::whereKey($foreign->id)->exists())->toBeTrue();
});

test('the delete-all-uploads route only answers POST', function () {
    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => str_ends_with($route->uri(), 'admin/all-file'));

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST']);
});

test('a guest cannot delete every upload', function () {
    adminUpload(['file_name' => 'uploads/all/mine.png']);

    $this->post('/temp/admin/all-file')->assertRedirect();

    expect(Upload::count())->toBe(1);
});

test('a staff member without the permission cannot delete every upload', function () {
    adminUpload(['file_name' => 'uploads/all/mine.png']);
    actingAsStoreUser(createStoreUser('staff'));

    $this->post('/temp/admin/all-file')->assertForbidden();

    expect(Upload::count())->toBe(1);
});

test('a staff member granted the permission can delete every upload', function () {
    adminUpload(['file_name' => 'uploads/all/mine.png']);
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
    adminUpload(['file_name' => 'uploads/all/mine.png']);

    withScratchPublicPath(function (string $publicPath) {
        file_put_contents("$publicPath/uploads/all/mine.png", tinyPng());
        actingAsStoreUser(createStoreAdmin());

        $this->post('/temp/admin/all-file')->assertRedirect();

        expect(Upload::count())->toBe(0)
            ->and(storedUploadFiles($publicPath))->toBe([]);
    });
});

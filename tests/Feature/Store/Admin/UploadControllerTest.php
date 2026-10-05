<?php

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The store back office's uploaded files (/temp/admin/uploaded-files): the
 * library and upload pages moved from Blade to Inertia, and the JSON
 * endpoints behind the shared file manager dialog
 * (.ai/rules/store-backoffice-migration.md §2.4).
 *
 * No permission gates these routes, as none gated the Blade screens: every
 * back-office account may use them, so the refused callers are a guest and a
 * customer. UploadPolicy still keeps an accountant to their own files.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function libraryUpload(array $attributes = []): Upload
{
    return Upload::factory()->stored()->create([
        'user_id' => createStoreUser('staff')->id,
        'file_original_name' => 'photo',
        'extension' => 'png',
        'type' => 'image',
        'file_size' => 2048,
        ...$attributes,
    ]);
}

describe('index', function () {
    test('the library can be rendered', function () {
        actingAsStoreUser(createStoreAdmin());

        $this->get('/temp/admin/uploaded-files')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/UploadedFiles/Index')
        );
    });

    test('the library returns each file in full, with who uploaded it', function () {
        actingAsStoreUser(createStoreAdmin());
        $uploader = createStoreUser('staff');
        $uploader->forceFill(['name' => 'Sam Staff'])->save();
        $upload = libraryUpload([
            'user_id' => $uploader->id,
            'file_name' => 'uploads/all/holiday.png',
            'file_original_name' => 'holiday',
            'file_size' => 4096,
        ]);
        $upload->forceFill(['created_at' => '2026-10-01 09:30:00'])->save();

        $this->get('/temp/admin/uploaded-files')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/UploadedFiles/Index')
                ->where('filters', ['search' => null, 'sort' => 'newest', 'per_page' => 20])
                ->has('uploads.data', 1)
                ->has('uploads.data.0', fn (Assert $file) => $file
                    ->where('id', $upload->id)
                    ->where('name', 'holiday')
                    ->where('extension', 'png')
                    ->where('type', 'image')
                    ->where('size', 4096)
                    ->where('url', asset('uploads/all/holiday.png'))
                    ->where('created_at', '2026-10-01T09:30:00+00:00')
                    ->where('uploaded_by', 'Sam Staff'))
        );
    });

    test('the library links a file with an external link there, and names no uploader for a deleted account', function () {
        actingAsStoreUser(createStoreAdmin());
        libraryUpload(['user_id' => 999999, 'file_name' => null, 'external_link' => 'https://cdn.example.com/a.png']);

        $this->get('/temp/admin/uploaded-files')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('uploads.data.0.url', 'https://cdn.example.com/a.png')
                ->where('uploads.data.0.uploaded_by', null)
        );
    });

    test('the library lists every account\'s files, as the Blade library did', function () {
        actingAsStoreUser(createStoreAdmin());
        $mine = libraryUpload();
        $theirs = libraryUpload(['user_id' => createStoreUser('seller')->id]);

        expect(collect($this->get('/temp/admin/uploaded-files')->assertOk()->inertiaProps('uploads.data'))->pluck('id')->sort()->values()->all())
            ->toBe([$mine->id, $theirs->id]);
    });

    test('the library searches the original name and leaves the other files out', function () {
        actingAsStoreUser(createStoreAdmin());
        $match = libraryUpload(['file_original_name' => 'report_final']);
        libraryUpload(['file_original_name' => 'reportXfinal']);
        libraryUpload(['file_original_name' => 'holiday']);

        $this->get('/temp/admin/uploaded-files?search=t_f')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.search', 't_f')
                ->has('uploads.data', 1)
                ->where('uploads.data.0.id', $match->id)
        );
    });

    test('the library sorts newest first by default, and by age or size on request', function (string $query, array $expectedOrder) {
        actingAsStoreUser(createStoreAdmin());
        $uploads = [
            'old-small' => libraryUpload(['file_size' => 10]),
            'new-large' => libraryUpload(['file_size' => 3000]),
            'mid-mid' => libraryUpload(['file_size' => 200]),
        ];
        $uploads['old-small']->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $uploads['new-large']->forceFill(['created_at' => '2026-03-01 00:00:00'])->save();
        $uploads['mid-mid']->forceFill(['created_at' => '2026-02-01 00:00:00'])->save();

        $ids = collect($this->get('/temp/admin/uploaded-files'.$query)->assertOk()->inertiaProps('uploads.data'))->pluck('id')->all();

        expect($ids)->toBe(array_map(fn (string $key) => $uploads[$key]->id, $expectedOrder));
    })->with([
        'default' => ['', ['new-large', 'mid-mid', 'old-small']],
        'newest' => ['?sort=newest', ['new-large', 'mid-mid', 'old-small']],
        'oldest' => ['?sort=oldest', ['old-small', 'mid-mid', 'new-large']],
        'smallest' => ['?sort=smallest', ['old-small', 'mid-mid', 'new-large']],
        'largest' => ['?sort=largest', ['new-large', 'mid-mid', 'old-small']],
    ]);

    test('the library refuses an unknown sort and an over-long search', function () {
        actingAsStoreUser(createStoreAdmin());

        $this->get('/temp/admin/uploaded-files?sort=random')->assertSessionHasErrors('sort');
        $this->get('/temp/admin/uploaded-files?search='.str_repeat('a', 256))->assertSessionHasErrors('search');
    });

    test('the library pages, twenty to a page by default', function () {
        actingAsStoreUser(createStoreAdmin());
        $ids = collect(range(1, 23))->map(fn () => libraryUpload()->id)->reverse()->values();

        $firstPage = $this->get('/temp/admin/uploaded-files')->assertOk()->inertiaProps('uploads');
        $secondPage = $this->get('/temp/admin/uploaded-files?page=2')->assertOk()->inertiaProps('uploads');

        expect(collect($firstPage['data'])->pluck('id')->all())->toBe($ids->take(20)->all())
            ->and(collect($secondPage['data'])->pluck('id')->all())->toBe($ids->slice(20)->values()->all())
            ->and($firstPage['meta']['total'])->toBe(23);
    });

    test('the library honours an allowed page size and refuses any other', function () {
        actingAsStoreUser(createStoreAdmin());
        foreach (range(1, 12) as $number) {
            libraryUpload();
        }

        $this->get('/temp/admin/uploaded-files?per_page=10')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('uploads.data', 10)->where('filters.per_page', 10)
        );
        $this->get('/temp/admin/uploaded-files?per_page=7')->assertSessionHasErrors('per_page');
    });

    test('the library is open to every back-office account, with no permission needed', function (string $userType) {
        actingAsStoreUser(createStoreUser($userType));

        $this->get('/temp/admin/uploaded-files')->assertOk();
    })->with(['staff', 'accountant']);

    test('the library refuses a caller outside the back office', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/uploaded-files'), $caller);
    })->with(['guest', 'customer']);
});

describe('create', function () {
    test('the upload page can be rendered', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->get('/temp/admin/uploaded-files/create')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/UploadedFiles/Create')
        );
    });

    test('the upload page refuses a caller outside the back office', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/uploaded-files/create'), $caller);
    })->with(['guest', 'customer']);
});

describe('browse', function () {
    test('the file manager lists only the signed-in account\'s own files, in full', function () {
        $staff = actingAsStoreUser(createStoreUser('staff'));
        $mine = libraryUpload(['user_id' => $staff->id, 'file_name' => 'uploads/all/mine.png', 'file_original_name' => 'mine', 'file_size' => 512]);
        $mine->forceFill(['created_at' => '2026-10-02 10:00:00'])->save();
        libraryUpload();

        $response = $this->getJson('/temp/admin/uploaded-files/browse')->assertOk();

        expect($response->json('data'))->toBe([[
            'id' => $mine->id,
            'name' => 'mine',
            'extension' => 'png',
            'type' => 'image',
            'size' => 512,
            'url' => asset('uploads/all/mine.png'),
            'created_at' => '2026-10-02T10:00:00+00:00',
        ]])->and($response->json('meta.total'))->toBe(1);
    });

    test('the file manager searches, sorts and keeps to one kind of file', function () {
        $staff = actingAsStoreUser(createStoreUser('staff'));
        $smallImage = libraryUpload(['user_id' => $staff->id, 'file_original_name' => 'logo small', 'file_size' => 10]);
        $largeImage = libraryUpload(['user_id' => $staff->id, 'file_original_name' => 'logo large', 'file_size' => 900]);
        libraryUpload(['user_id' => $staff->id, 'file_original_name' => 'logo video', 'type' => 'video', 'extension' => 'mp4']);
        libraryUpload(['user_id' => $staff->id, 'file_original_name' => 'banner']);

        $ids = $this->getJson('/temp/admin/uploaded-files/browse?search=logo&type=image&sort=largest')->assertOk()->json('data.*.id');

        expect($ids)->toBe([$largeImage->id, $smallImage->id]);
        $this->getJson('/temp/admin/uploaded-files/browse?type=executable')->assertUnprocessable()->assertJsonValidationErrors('type');
    });

    test('the file manager pages sixty at a time', function () {
        $staff = actingAsStoreUser(createStoreUser('staff'));
        foreach (range(1, 61) as $number) {
            libraryUpload(['user_id' => $staff->id]);
        }

        $firstPage = $this->getJson('/temp/admin/uploaded-files/browse')->assertOk();
        $secondPage = $this->getJson('/temp/admin/uploaded-files/browse?page=2')->assertOk();

        expect($firstPage->json('data'))->toHaveCount(60)
            ->and($firstPage->json('meta.last_page'))->toBe(2)
            ->and($secondPage->json('data'))->toHaveCount(1);
    });

    test('the file manager refuses a caller outside the back office', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/uploaded-files/browse'), $caller);
    })->with(['guest', 'customer']);
});

describe('previews', function () {
    test('an upload field gets its files back in the order it holds them', function () {
        actingAsStoreUser(createStoreUser('staff'));
        $first = libraryUpload(['file_original_name' => 'first']);
        $second = libraryUpload(['file_original_name' => 'second']);
        libraryUpload(['file_original_name' => 'not asked for']);

        $response = $this->getJson('/temp/admin/uploaded-files/previews?'.http_build_query(['ids' => [$second->id, $first->id, 999999]]))->assertOk();

        expect($response->json('*.id'))->toBe([$second->id, $first->id])
            ->and($response->json('0'))->toBe([
                'id' => $second->id,
                'name' => 'second',
                'extension' => 'png',
                'type' => 'image',
                'size' => 2048,
                'url' => asset($second->file_name),
                'created_at' => $second->created_at->toIso8601String(),
            ]);
    });

    test('an accountant previews only their own files', function () {
        $accountant = actingAsStoreUser(createStoreUser('accountant'));
        $mine = libraryUpload(['user_id' => $accountant->id]);
        $theirs = libraryUpload();

        expect($this->getJson('/temp/admin/uploaded-files/previews?'.http_build_query(['ids' => [$mine->id, $theirs->id]]))->assertOk()->json('*.id'))
            ->toBe([$mine->id]);
    });

    test('previews need a list of ids', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->getJson('/temp/admin/uploaded-files/previews')->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->getJson('/temp/admin/uploaded-files/previews?ids[]=abc')->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    });

    test('previews refuse a caller outside the back office', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/uploaded-files/previews?ids[]=1'), $caller);
    })->with(['guest', 'customer']);
});

describe('store', function () {
    test('an upload is stored for the signed-in account and returned in full', function () {
        $staff = actingAsStoreUser(createStoreUser('staff'));

        withScratchPublicPath(function (string $publicPath) use ($staff) {
            $response = $this->postJson('/temp/admin/uploaded-files', [
                'file' => UploadedFile::fake()->createWithContent('shop front.png', tinyPng()),
            ])->assertCreated();

            $upload = Upload::sole();

            expect($upload->user_id)->toEqual($staff->id)
                ->and(storedUploadFiles($publicPath))->toHaveCount(1)
                ->and($response->json())->toBe([
                    'id' => $upload->id,
                    'name' => 'shop front',
                    'extension' => 'png',
                    'type' => 'image',
                    'size' => $upload->file_size,
                    'url' => asset($upload->file_name),
                    'created_at' => $upload->created_at->toIso8601String(),
                ]);
        });
    });

    test('a file the uploader refuses is a validation error, and nothing is stored', function () {
        actingAsStoreUser(createStoreUser('staff'));

        withScratchPublicPath(function (string $publicPath) {
            $this->postJson('/temp/admin/uploaded-files', [
                'file' => UploadedFile::fake()->createWithContent('a.png', '<html><script>alert(1)</script></html>'),
            ])->assertUnprocessable()->assertJsonValidationErrors(['file' => __('messages.upload_refused')]);

            expect(Upload::count())->toBe(0)
                ->and(storedUploadFiles($publicPath))->toBe([]);
        });
    });

    test('an upload needs a file within the size limit', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->postJson('/temp/admin/uploaded-files', [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/temp/admin/uploaded-files', ['file' => 'not a file'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/temp/admin/uploaded-files', ['file' => UploadedFile::fake()->create('big.zip', 512 * 1024 + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        expect(Upload::count())->toBe(0);
    });

    test('the demo shop takes no archives', function () {
        config(['store.demo_mode' => true]);
        actingAsStoreUser(createStoreUser('staff'));

        withScratchPublicPath(function (string $publicPath) {
            $this->postJson('/temp/admin/uploaded-files', [
                'file' => UploadedFile::fake()->createWithContent('bundle.zip', "PK\x05\x06".str_repeat("\0", 18)),
            ])->assertUnprocessable()->assertJsonValidationErrors('file');

            expect(Upload::count())->toBe(0)
                ->and(storedUploadFiles($publicPath))->toBe([]);
        });
    });

    test('an upload refuses a caller outside the back office', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        withScratchPublicPath(function (string $publicPath) use ($caller) {
            assertStoreCallerRefused($this->post('/temp/admin/uploaded-files', [
                'file' => UploadedFile::fake()->createWithContent('a.png', tinyPng()),
            ]), $caller);

            expect(Upload::count())->toBe(0)
                ->and(storedUploadFiles($publicPath))->toBe([]);
        });
    })->with(['guest', 'customer']);
});

describe('destroy', function () {
    test('a file is deleted with its stored copy, back on the same page and filters', function () {
        actingAsStoreUser(createStoreUser('staff'));
        $upload = libraryUpload(['file_name' => 'uploads/all/gone.png']);
        $kept = libraryUpload();

        withScratchPublicPath(function (string $publicPath) use ($upload) {
            file_put_contents("$publicPath/uploads/all/gone.png", tinyPng());

            $this->delete("/temp/admin/uploaded-files/{$upload->id}?page=2&sort=oldest")
                ->assertRedirect('/temp/admin/uploaded-files?page=2&sort=oldest');

            expect(storedUploadFiles($publicPath))->toBe([]);
        });

        expect(Upload::pluck('id')->all())->toBe([$kept->id]);
    });

    test('deleting a file that does not exist is a 404', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->delete('/temp/admin/uploaded-files/999999')->assertNotFound();
    });

    test('an accountant deletes their own files and no one else\'s', function () {
        $accountant = actingAsStoreUser(createStoreUser('accountant'));
        $mine = libraryUpload(['user_id' => $accountant->id]);
        $theirs = libraryUpload();

        $this->delete("/temp/admin/uploaded-files/{$theirs->id}")->assertForbidden();
        $this->delete("/temp/admin/uploaded-files/{$mine->id}")->assertRedirect('/temp/admin/uploaded-files');

        expect(Upload::pluck('id')->all())->toBe([$theirs->id]);
    });

    test('a delete refuses a caller outside the back office', function (string $caller) {
        $upload = libraryUpload();
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->delete("/temp/admin/uploaded-files/{$upload->id}"), $caller);

        $this->assertModelExists($upload);
    })->with(['guest', 'customer']);
});

describe('bulk destroy', function () {
    test('the ticked files are deleted and the rest kept, back on the same page', function () {
        actingAsStoreUser(createStoreUser('staff'));
        $first = libraryUpload();
        $second = libraryUpload();
        $kept = libraryUpload();

        $this->post('/temp/admin/bulk-uploaded-files-delete?page=3', ['ids' => [$first->id, $second->id, 999999]])
            ->assertRedirect('/temp/admin/uploaded-files?page=3');

        expect(Upload::pluck('id')->all())->toBe([$kept->id]);
    });

    test('an accountant\'s selection skips the files that are not theirs', function () {
        $accountant = actingAsStoreUser(createStoreUser('accountant'));
        $mine = libraryUpload(['user_id' => $accountant->id]);
        $theirs = libraryUpload();

        $this->post('/temp/admin/bulk-uploaded-files-delete', ['ids' => [$mine->id, $theirs->id]])
            ->assertRedirect('/temp/admin/uploaded-files');

        expect(Upload::pluck('id')->all())->toBe([$theirs->id]);
    });

    test('a bulk delete needs a list of ids, no longer than a page', function () {
        actingAsStoreUser(createStoreUser('staff'));
        $upload = libraryUpload();

        $this->post('/temp/admin/bulk-uploaded-files-delete', [])->assertSessionHasErrors('ids');
        $this->post('/temp/admin/bulk-uploaded-files-delete', ['ids' => ['abc']])->assertSessionHasErrors('ids.0');
        $this->post('/temp/admin/bulk-uploaded-files-delete', ['ids' => range(1, 101)])->assertSessionHasErrors('ids');

        $this->assertModelExists($upload);
    });

    test('a bulk delete refuses a caller outside the back office', function (string $caller) {
        $upload = libraryUpload();
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->post('/temp/admin/bulk-uploaded-files-delete', ['ids' => [$upload->id]]), $caller);

        $this->assertModelExists($upload);
    })->with(['guest', 'customer']);
});

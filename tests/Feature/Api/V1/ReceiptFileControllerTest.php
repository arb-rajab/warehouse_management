<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ReceiptFile::DISK);
    Storage::fake('public');
});

/**
 * A bill file record whose bytes exist on the (fake) private disk.
 */
function storedBillFile(Receipt $receipt, string $contents = 'bill bytes', bool $pdf = false): ReceiptFile
{
    $file = ReceiptFile::factory()->for($receipt)->when($pdf, fn ($factory) => $factory->pdf())->create();
    Storage::disk(ReceiptFile::DISK)->put($file->path, $contents);

    return $file;
}

// --- Upload ------------------------------------------------------------------

test('uploading a photo stores it re-encoded as a JPEG on the private disk and answers the file without its path', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $response = $this->postJson("/api/v1/receipts/{$receipt->id}/files", [
        'file' => UploadedFile::fake()->createWithContent('front-page.png', billPhoto(120, 80, 'png')),
    ]);

    $response->assertCreated();
    $file = ReceiptFile::query()->sole();
    $bytes = Storage::disk(ReceiptFile::DISK)->get($file->path);

    expect($response->json())->toEqual([
        'id' => $file->id,
        'original_name' => 'front-page.jpg',
        'mime_type' => 'image/jpeg',
        'size' => strlen($bytes),
        'url' => route('api.v1.receipts.files.show', ['receipt' => $receipt->id, 'file' => $file->id]),
        'created_at' => $file->created_at->toIso8601String(),
    ])
        ->and($file->receipt_id)->toBe($receipt->id)
        ->and($file->path)->toStartWith(ReceiptFile::DIRECTORY.'/')->toEndWith('.jpg')
        ->and($file->mime_type)->toBe('image/jpeg')
        ->and($file->size)->toBe(strlen($bytes))
        ->and(substr($bytes, 0, 3))->toBe("\xFF\xD8\xFF")
        ->and($response->getContent())->not->toContain('receipt-bills')->not->toContain('"path"');
});

test('a bill is never written to the public disk', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('a.jpg')])->assertCreated();

    $path = ReceiptFile::query()->sole()->path;
    Storage::disk(ReceiptFile::DISK)->assertExists($path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a PDF Ghostscript cannot compress is kept exactly as sent', function () {
    actingAsAdminMobileUser();
    Process::fake(['*' => Process::result(errorOutput: 'gs: not found', exitCode: 127)]);
    $receipt = Receipt::factory()->create();
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    $response = $this->postJson("/api/v1/receipts/{$receipt->id}/files", [
        'file' => UploadedFile::fake()->createWithContent('Delivery Note.pdf', $pdf),
    ]);

    $response->assertCreated();
    $file = ReceiptFile::query()->sole();

    expect($response->json('mime_type'))->toBe('application/pdf')
        ->and($response->json('original_name'))->toBe('Delivery Note.pdf')
        ->and($response->json('size'))->toBe(strlen($pdf))
        ->and($file->path)->toEndWith('.pdf')
        ->and(Storage::disk(ReceiptFile::DISK)->get($file->path))->toBe($pdf);
});

test('a typical phone photo is stored as a much smaller JPEG without its metadata, still at least 1600 pixels wide', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $photo = billPhoto(4032, 3024);
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x00\0PHONE-GPS-MARKER";
    $photo = substr($photo, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($photo, 2);

    $response = $this->postJson("/api/v1/receipts/{$receipt->id}/files", [
        'file' => UploadedFile::fake()->createWithContent('IMG_2041.jpg', $photo),
    ])->assertCreated();

    $file = ReceiptFile::query()->sole();
    $bytes = Storage::disk(ReceiptFile::DISK)->get($file->path);
    $info = getimagesizefromstring($bytes);
    [$width, $height, $mime] = [$info[0], $info[1], $info['mime']];

    expect(strlen($bytes))->toBeLessThan(intdiv(strlen($photo), 4))
        ->and($width)->toBeGreaterThanOrEqual(1600)->toBeLessThanOrEqual(2400)
        ->and($height)->toBe((int) round($width * 3024 / 4032))
        ->and($mime)->toBe('image/jpeg')
        ->and($bytes)->not->toContain('PHONE-GPS-MARKER')
        ->and($response->json('size'))->toBe(strlen($bytes))
        ->and($response->json('mime_type'))->toBe('image/jpeg')
        ->and($response->json('original_name'))->toBe('IMG_2041.jpg');
});

test('a PNG or WebP photo is converted to a JPEG', function (string $format) {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", [
        'file' => UploadedFile::fake()->createWithContent("scan.{$format}", billPhoto(800, 600, $format)),
    ])
        ->assertCreated()
        ->assertJsonPath('mime_type', 'image/jpeg')
        ->assertJsonPath('original_name', 'scan.jpg');

    $file = ReceiptFile::query()->sole();
    expect($file->path)->toEndWith('.jpg')
        ->and(getimagesizefromstring(Storage::disk(ReceiptFile::DISK)->get($file->path))['mime'])->toBe('image/jpeg');
})->with(['png', 'webp']);

test('a PDF is stored as Ghostscript compressed it, and its record describes the stored file', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $sent = "%PDF-1.4\n".str_repeat('% uncompressed scan bytes'."\n", 200).'%%EOF';
    $compressed = "%PDF-1.5\n% compressed\n%%EOF\n";
    Process::fake(function (PendingProcess $process) use ($compressed) {
        $output = collect($process->command)->first(fn (string $argument) => str_starts_with($argument, '-sOutputFile='));
        file_put_contents(substr($output, strlen('-sOutputFile=')), $compressed);

        return Process::result();
    });

    $response = $this->postJson("/api/v1/receipts/{$receipt->id}/files", [
        'file' => UploadedFile::fake()->createWithContent('Delivery Note.pdf', $sent),
    ])->assertCreated();

    $file = ReceiptFile::query()->sole();
    expect(Storage::disk(ReceiptFile::DISK)->get($file->path))->toBe($compressed)
        ->and($file->size)->toBe(strlen($compressed))
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->original_name)->toBe('Delivery Note.pdf')
        ->and($response->json('size'))->toBe(strlen($compressed));
    // The client's file name never reaches the command line.
    Process::assertRan(fn (PendingProcess $process) => ! str_contains(implode(' ', $process->command), 'Delivery'));
});

test('a photo larger than the bill needs is scaled down to 2400 pixels on its longest side', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->createWithContent('wide.jpg', billPhoto(3200, 300))])->assertCreated();

    [$width, $height] = getimagesizefromstring(Storage::disk(ReceiptFile::DISK)->get(ReceiptFile::query()->sole()->path));

    expect($width)->toBe(2400)
        ->and($height)->toBeLessThan(300);
});

test('each upload is stored under its own random name, whatever it was called', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('same.jpg')])->assertCreated();
    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('same.jpg')])->assertCreated();

    $paths = ReceiptFile::query()->pluck('path');

    expect($paths->unique())->toHaveCount(2)
        ->and($paths->every(fn (string $path) => ! str_contains($path, 'same')))->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toHaveCount(2);
});

test('the download name is the clients base name with the stored extension, without any path', function (string $clientName, string $expected) {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->createWithContent($clientName, billPhoto(40, 30, 'png'))])
        ->assertCreated()
        ->assertJsonPath('original_name', $expected);
})->with([
    'plain' => ['bill.png', 'bill.jpg'],
    'upper case extension' => ['SCAN.PNG', 'SCAN.jpg'],
    'unix path' => ['../../etc/passwd.png', 'passwd.jpg'],
    'windows path' => ['C:\\Users\\me\\bill scan.png', 'bill scan.jpg'],
    'nothing but an extension' => ['.png', 'bill.jpg'],
]);

test('a very long file name is shortened to 200 characters before its extension', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $name = ReceiptFile::query()->whereKey(
        $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->createWithContent(str_repeat('a', 300).'.png', billPhoto(40, 30, 'png'))])
            ->assertCreated()->json('id')
    )->value('original_name');

    expect($name)->toBe(str_repeat('a', 200).'.jpg');
});

test('uploading is refused for a missing, non-file or unsupported file, and stores nothing', function (callable $payload) {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", $payload())
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(ReceiptFile::query()->count())->toBe(0)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
})->with([
    'no file' => [fn () => []],
    'not a file' => [fn () => ['file' => 'bill.pdf']],
    'a text file' => [fn () => ['file' => UploadedFile::fake()->createWithContent('bill.txt', 'plain text')]],
    'a script renamed as a photo' => [fn () => ['file' => UploadedFile::fake()->createWithContent('bill.jpg', '<?php echo 1;')]],
    'a gif' => [fn () => ['file' => UploadedFile::fake()->image('bill.gif')]],
    'a document' => [fn () => ['file' => UploadedFile::fake()->create('bill.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]],
]);

test('uploading is refused above 10 MB and accepted at exactly 10 MB, for a PDF', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->create('big.pdf', ReceiptFile::MAX_KILOBYTES + 1, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);
    expect(ReceiptFile::query()->count())->toBe(0);

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->create('limit.pdf', ReceiptFile::MAX_KILOBYTES, 'application/pdf')])
        ->assertCreated();
    expect(ReceiptFile::query()->count())->toBe(1);
});

test('a photo that cannot be decoded is refused as a validation error on file, and stores nothing', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    // The PNG signature passes the type check; the truncated body cannot be decoded.
    $broken = substr(tinyPng(), 0, 40);

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->createWithContent('broken.png', $broken)])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(ReceiptFile::query()->count())->toBe(0)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
});

test('a receipt holds at most ten files; the eleventh is refused with receipt_file_limit_reached and its bytes are not kept', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->count(ReceiptFile::MAX_PER_RECEIPT)->create();
    $other = Receipt::factory()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('eleventh.jpg')])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('messages.receipt_file_limit_reached', ['max' => ReceiptFile::MAX_PER_RECEIPT]),
            'error_code' => 'receipt_file_limit_reached',
        ]);

    expect($receipt->files()->count())->toBe(10)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);

    // The limit is per receipt: another receipt still takes a file.
    $this->postJson("/api/v1/receipts/{$other->id}/files", ['file' => UploadedFile::fake()->image('ok.jpg')])->assertCreated();
});

test('the tenth file is still accepted', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->count(ReceiptFile::MAX_PER_RECEIPT - 1)->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('tenth.jpg')])->assertCreated();

    expect($receipt->files()->count())->toBe(10);
});

test('uploading to a finalized receipt is refused with 409 receipt_finalized and stores nothing', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->finalized()->create();

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('late.jpg')])
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.receipt_finalized'), 'error_code' => 'receipt_finalized']);

    expect(ReceiptFile::query()->count())->toBe(0)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
});

test('uploading to a receipt that does not exist is a 404', function () {
    actingAsAdminMobileUser();

    $this->postJson('/api/v1/receipts/999999/files', ['file' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
});

test('uploading is refused to a guest and to a non-admin, and stores nothing', function () {
    $receipt = Receipt::factory()->create();
    $payload = ['file' => UploadedFile::fake()->image('a.jpg')];

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", $payload)->assertUnauthorized();

    actingAsMobileUser();
    $this->postJson("/api/v1/receipts/{$receipt->id}/files", $payload)->assertForbidden();

    expect(ReceiptFile::query()->count())->toBe(0)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
});

// --- Download ----------------------------------------------------------------

test('an admin downloads a bill file inline with its type and name, never cached or sniffed', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = storedBillFile($receipt, '%PDF-1.4 the bill', pdf: true);
    $file->update(['original_name' => 'delivery-note.pdf']);

    $response = $this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-1.4 the bill')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')->toContain('delivery-note.pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('a photo is served with its image type', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = storedBillFile($receipt, 'jpeg bytes');

    $this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('the download url a receipt payload hands out is the route that serves the file', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = storedBillFile($receipt, 'the bytes');

    $url = $this->getJson("/api/v1/receipts/{$receipt->id}")->assertOk()->json('files.0.url');

    expect($this->get($url)->assertOk()->streamedContent())->toBe('the bytes');
});

test('downloading a file through another receipt is a 404', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $other = Receipt::factory()->create();
    $file = storedBillFile($other);

    $this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertNotFound();
    $this->get("/api/v1/receipts/{$other->id}/files/{$file->id}")->assertOk();
});

test('downloading a file whose bytes are gone from the disk is a 404', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();

    $this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertNotFound();
});

test('downloading a file that does not exist is a 404', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->get("/api/v1/receipts/{$receipt->id}/files/999999")->assertNotFound();
});

test('a finalized receipts files can still be downloaded', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->finalized()->create();
    $file = storedBillFile($receipt, 'kept');

    expect($this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertOk()->streamedContent())->toBe('kept');
});

// --- Delete ------------------------------------------------------------------

test('an admin removes a bill file of a draft: the record and its bytes go, the other files stay', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $gone = storedBillFile($receipt, 'to remove');
    $kept = storedBillFile($receipt, 'to keep');
    $noise = storedBillFile(Receipt::factory()->create(), 'elsewhere');

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$gone->id}")->assertNoContent();

    expect(ReceiptFile::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and(ReceiptFile::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(ReceiptFile::query()->whereKey($noise->id)->exists())->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($gone->path))->toBeFalse()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($kept->path))->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($noise->path))->toBeTrue();
});

test('removing a file of a finalized receipt is refused with 409 receipt_finalized and keeps the file', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->finalized()->create();
    $file = storedBillFile($receipt);

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$file->id}")
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.receipt_finalized'), 'error_code' => 'receipt_finalized']);

    expect(ReceiptFile::query()->whereKey($file->id)->exists())->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($file->path))->toBeTrue();
});

test('removing a file through another receipt is a 404 and keeps the file', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = storedBillFile(Receipt::factory()->create());

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertNotFound();

    expect(ReceiptFile::query()->whereKey($file->id)->exists())->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($file->path))->toBeTrue();
});

test('removing a file that does not exist is a 404', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/999999")->assertNotFound();
});

test('removing a file is refused to a guest and to a non-admin, and keeps the file', function () {
    $receipt = Receipt::factory()->create();
    $file = storedBillFile($receipt);

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertUnauthorized();

    actingAsMobileUser();
    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertForbidden();

    expect(ReceiptFile::query()->whereKey($file->id)->exists())->toBeTrue()
        ->and(Storage::disk(ReceiptFile::DISK)->exists($file->path))->toBeTrue();
});

test('removing the last file makes a receipt unfinalizable until another is added', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->create();
    $file = storedBillFile($receipt);
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => Product::factory()->create()->id]);

    $this->deleteJson("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertNoContent();
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertUnprocessable()->assertJsonPath('error_code', 'receipt_has_no_files');

    $this->postJson("/api/v1/receipts/{$receipt->id}/files", ['file' => UploadedFile::fake()->image('again.jpg')])->assertCreated();
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    expect(Pallet::query()->count())->toBe(1);
});

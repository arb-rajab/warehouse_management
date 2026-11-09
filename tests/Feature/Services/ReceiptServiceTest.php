<?php

use App\Enums\ReceiptStatus;
use App\Exceptions\InvalidReceiptStateException;
use App\Exceptions\ReceiptFinalizedException;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReceiptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ReceiptFile::DISK);
});

/**
 * A receipt model that still says draft although the row has been finalized
 * since it was loaded, as a request that bound it before a concurrent
 * finalize would hold.
 */
function staleDraftOfFinalizedReceipt(): Receipt
{
    $receipt = Receipt::factory()->create();
    $stale = Receipt::query()->findOrFail($receipt->id);
    $receipt->update(['status' => ReceiptStatus::Finalized, 'finalized_by' => User::factory()->create()->id, 'finalized_at' => now()]);

    expect($stale->isFinalized())->toBeFalse();

    return $stale;
}

test('create records a draft with its note and the pallets in the order given', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $receipt = app(ReceiptService::class)->create($user->id, $warehouse->id, 'Dock 2', [
        ['product_id' => $product->id, 'boxes' => 9, 'expiration_date' => '2027-01-02'],
        ['product_id' => $product->id, 'boxes' => 4, 'expiration_date' => null],
    ]);

    expect($receipt->status)->toBe(ReceiptStatus::Draft)
        ->and($receipt->warehouse_id)->toBe($warehouse->id)
        ->and($receipt->created_by)->toBe($user->id)
        ->and($receipt->note)->toBe('Dock 2')
        ->and($receipt->pallets()->orderBy('position')->get()->map(fn (ReceiptPallet $entry) => [$entry->position, $entry->boxes, $entry->expiration_date?->toDateString()])->all())
        ->toBe([[0, 9, '2027-01-02'], [1, 4, null]]);
});

test('create with no pallets and no note records an empty draft', function () {
    $receipt = app(ReceiptService::class)->create(User::factory()->create()->id, Warehouse::firstOrCreateDefault()->id, null, []);

    expect($receipt->note)->toBeNull()
        ->and($receipt->pallets()->count())->toBe(0)
        ->and(Pallet::query()->count())->toBe(0);
});

test('replacePallets keeps the note unless the changes carry one, and null clears it', function () {
    $receipt = Receipt::factory()->create(['note' => 'kept']);
    $service = app(ReceiptService::class);

    expect($service->replacePallets($receipt, [], [])->note)->toBe('kept')
        ->and($service->replacePallets($receipt, [], ['note' => 'new'])->note)->toBe('new')
        ->and($service->replacePallets($receipt, [], ['note' => null])->note)->toBeNull()
        ->and($receipt->fresh()->note)->toBeNull();
});

test('replacePallets renumbers positions from zero and drops the previous entries', function () {
    $receipt = Receipt::factory()->create();
    $product = Product::factory()->create();
    ReceiptPallet::factory()->for($receipt)->count(4)->create(['position' => 7]);
    $before = ReceiptPallet::query()->pluck('id');

    app(ReceiptService::class)->replacePallets($receipt, [
        ['product_id' => $product->id, 'boxes' => 1, 'expiration_date' => null],
        ['product_id' => $product->id, 'boxes' => 2, 'expiration_date' => null],
    ], []);

    expect($receipt->pallets()->orderBy('position')->pluck('position')->all())->toBe([0, 1])
        ->and($receipt->pallets()->whereIn('id', $before)->count())->toBe(0);
});

test('every change re-reads the receipt under the lock, so a stale draft model cannot edit a finalized receipt', function () {
    $stale = staleDraftOfFinalizedReceipt();
    $service = app(ReceiptService::class);

    expect(fn () => $service->replacePallets($stale, [], ['note' => 'late']))->toThrow(ReceiptFinalizedException::class)
        ->and(fn () => $service->finalize($stale, User::factory()->create()->id))->toThrow(ReceiptFinalizedException::class)
        ->and(fn () => $service->deleteFile($stale, ReceiptFile::factory()->for($stale)->create()))->toThrow(ReceiptFinalizedException::class);

    expect($stale->fresh()->note)->not->toBe('late');
});

test('addFile on a stale draft of a finalized receipt is refused and leaves no bytes behind', function () {
    $stale = staleDraftOfFinalizedReceipt();

    expect(fn () => app(ReceiptService::class)->addFile($stale, UploadedFile::fake()->image('late.jpg')))
        ->toThrow(ReceiptFinalizedException::class);

    expect(ReceiptFile::query()->count())->toBe(0)
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
});

test('addFile on a finalized receipt is refused before anything is written', function () {
    $receipt = Receipt::factory()->finalized()->create();

    expect(fn () => app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->image('late.jpg')))
        ->toThrow(ReceiptFinalizedException::class);

    expect(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([]);
});

test('addFile beyond the limit throws receipt_file_limit_reached and removes the bytes it wrote', function () {
    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->count(ReceiptFile::MAX_PER_RECEIPT)->create();

    try {
        app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->image('extra.jpg'));
        $this->fail('Expected the limit to refuse the file.');
    } catch (InvalidReceiptStateException $exception) {
        expect($exception->errorCode)->toBe('receipt_file_limit_reached')
            ->and($exception->getMessage())->toBe(__('messages.receipt_file_limit_reached', ['max' => ReceiptFile::MAX_PER_RECEIPT]));
    }

    expect(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([])
        ->and($receipt->files()->count())->toBe(ReceiptFile::MAX_PER_RECEIPT);
});

test('addFile removes the bytes it wrote when recording the file fails for another reason', function () {
    $receipt = Receipt::factory()->create();
    ReceiptFile::creating(fn () => throw new RuntimeException('Simulated failure while recording the file.'));

    expect(fn () => app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->image('a.jpg')))
        ->toThrow(RuntimeException::class, 'Simulated failure');

    expect(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([])
        ->and(ReceiptFile::query()->count())->toBe(0);
});

test('addFile stores the re-encoded JPEG without metadata or appended bytes even when it is larger than the photo sent', function (string $name, Closure $photo) {
    $receipt = Receipt::factory()->create();
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x00\0PHONE-GPS-MARKER";
    $sent = $photo();
    $sent = str_starts_with($sent, "\xFF\xD8")
        ? substr($sent, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($sent, 2)
        : $sent.'APPENDED-PAYLOAD-MARKER';

    $file = app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->createWithContent($name, $sent));
    $stored = Storage::disk(ReceiptFile::DISK)->get($file->path);

    expect(strlen($stored))->toBeGreaterThan(strlen($sent))
        ->and($stored)->not->toContain('PHONE-GPS-MARKER')->not->toContain('APPENDED-PAYLOAD-MARKER')
        ->and(getimagesizefromstring($stored)['mime'])->toBe('image/jpeg')
        ->and($file->path)->toEndWith('.jpg')
        ->and($file->mime_type)->toBe('image/jpeg')
        ->and($file->size)->toBe(strlen($stored))
        ->and($file->original_name)->toBe('scan.jpg');
})->with([
    'an already heavily compressed JPEG with EXIF' => ['scan.jpeg', fn () => billPhoto(400, 300, quality: 20)],
    'a flat PNG with appended bytes' => ['scan.png', function () {
        $image = imagecreatetruecolor(1200, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }],
]);

test('addFile stores the re-encoded JPEG when it is smaller than the photo sent', function () {
    $receipt = Receipt::factory()->create();
    $sent = billPhoto(800, 600, 'png');

    $file = app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->createWithContent('scan.png', $sent));
    $stored = Storage::disk(ReceiptFile::DISK)->get($file->path);

    expect(strlen($stored))->toBeLessThan(strlen($sent))
        ->and(substr($stored, 0, 3))->toBe("\xFF\xD8\xFF")
        ->and($file->mime_type)->toBe('image/jpeg')
        ->and($file->size)->toBe(strlen($stored))
        ->and($file->original_name)->toBe('scan.jpg');
});

test('addFile stores a PDF as sent when compressing it fails, and never fails the upload for it', function () {
    $receipt = Receipt::factory()->create();
    Process::fake(['*' => Process::result(errorOutput: 'Unrecoverable error', exitCode: 1)]);
    $sent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";

    $file = app(ReceiptService::class)->addFile($receipt, UploadedFile::fake()->createWithContent('bill.pdf', $sent));

    expect(Storage::disk(ReceiptFile::DISK)->get($file->path))->toBe($sent)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size)->toBe(strlen($sent))
        ->and($file->original_name)->toBe('bill.pdf');
    Process::assertRan(fn ($process) => $process->command[0] === 'gs');
});

test('deleteFile removes the record and then its bytes, and only that file', function () {
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();
    $sibling = ReceiptFile::factory()->for($receipt)->create();
    Storage::disk(ReceiptFile::DISK)->put($file->path, 'one');
    Storage::disk(ReceiptFile::DISK)->put($sibling->path, 'two');

    app(ReceiptService::class)->deleteFile($receipt, $file);

    expect(ReceiptFile::query()->pluck('id')->all())->toBe([$sibling->id])
        ->and(Storage::disk(ReceiptFile::DISK)->allFiles())->toBe([$sibling->path]);
});

test('deleteFile on a finalized receipt throws and keeps the bytes', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();
    Storage::disk(ReceiptFile::DISK)->put($file->path, 'kept');

    expect(fn () => app(ReceiptService::class)->deleteFile($receipt, $file))->toThrow(ReceiptFinalizedException::class);

    expect(Storage::disk(ReceiptFile::DISK)->exists($file->path))->toBeTrue()
        ->and(ReceiptFile::query()->count())->toBe(1);
});

test('finalize checks the bill file before the pallets, each with its own error code', function () {
    $service = app(ReceiptService::class);
    $user = User::factory()->create();
    $bare = Receipt::factory()->create();
    $withFileOnly = Receipt::factory()->create();
    ReceiptFile::factory()->for($withFileOnly)->create();

    $codeOf = function (Receipt $receipt) use ($service, $user): string {
        try {
            $service->finalize($receipt, $user->id);
        } catch (InvalidReceiptStateException $exception) {
            return $exception->errorCode;
        }

        return 'finalized';
    };

    expect($codeOf($bare))->toBe('receipt_has_no_files')
        ->and($codeOf($withFileOnly))->toBe('receipt_has_no_pallets');
});

test('finalize creates the pallets in typed order and links each entry, whatever order the entry rows were inserted in', function () {
    $user = User::factory()->create();
    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->create();
    $first = Product::factory()->boxesCount(10)->create();
    $second = Product::factory()->boxesCount(10)->create();
    // The entry typed second is inserted first.
    $typedSecond = ReceiptPallet::factory()->for($receipt)->create(['product_id' => $second->id, 'boxes' => 2, 'expiration_date' => null, 'position' => 1]);
    $typedFirst = ReceiptPallet::factory()->for($receipt)->create(['product_id' => $first->id, 'boxes' => 1, 'expiration_date' => '2027-05-05', 'position' => 0]);

    app(ReceiptService::class)->finalize($receipt, $user->id);

    $pallets = Pallet::query()->orderBy('id')->get();
    expect($pallets->pluck('product_id')->all())->toBe([$first->id, $second->id])
        ->and($typedFirst->fresh()->pallet_id)->toBe($pallets[0]->id)
        ->and($typedSecond->fresh()->pallet_id)->toBe($pallets[1]->id)
        ->and($pallets[0]->expiration_date->toDateString())->toBe('2027-05-05')
        ->and($pallets[1]->expiration_date)->toBeNull()
        ->and($pallets->pluck('cell_id')->filter()->all())->toBe([])
        ->and($receipt->fresh()->finalized_by)->toBe($user->id);
});

test('the exceptions carry the error code their JSON answer names', function () {
    $finalized = new ReceiptFinalizedException;
    $invalid = new InvalidReceiptStateException('receipt_has_no_pallets', 'Add a pallet.');

    expect($finalized->errorCode)->toBe('receipt_finalized')
        ->and($finalized->getMessage())->toBe(__('messages.receipt_finalized'))
        ->and($finalized->render(request())->getStatusCode())->toBe(409)
        ->and($finalized->render(request())->getData(true))->toBe(['message' => __('messages.receipt_finalized'), 'error_code' => 'receipt_finalized'])
        ->and($invalid->render(request())->getStatusCode())->toBe(422)
        ->and($invalid->render(request())->getData(true))->toBe(['message' => 'Add a pallet.', 'error_code' => 'receipt_has_no_pallets']);
});

test('every receiving error code has a message in both languages', function (string $key) {
    foreach (['en', 'ar'] as $locale) {
        expect(trans("messages.{$key}", ['max' => 10], $locale))->not->toBe("messages.{$key}");
    }
})->with(['receipt_finalized', 'receipt_has_no_files', 'receipt_has_no_pallets', 'receipt_file_limit_reached', 'pallet_not_placed', 'pallet_already_placed', 'pallet_warehouse_mismatch']);

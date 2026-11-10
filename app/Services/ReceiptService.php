<?php

namespace App\Services;

use App\Enums\ReceiptStatus;
use App\Exceptions\InvalidReceiptStateException;
use App\Exceptions\ReceiptFinalizedException;
use App\Models\Pallet;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Truck receiving, for Api\V1\ReceiptController and ReceiptFileController,
 * in the controller/service split of PalletActionService and
 * CellVerificationService. The bill is stored only as photos/PDFs; the admin
 * records the pallets as written on it (one entry per pallet) and checks them
 * against the bill by eye, so nothing is compared here.
 *
 * Every change to a receipt runs in a transaction holding a row lock on the
 * receipt (lockDraft()), which also refuses a finalized receipt with 409
 * `receipt_finalized`: once finalized a receipt is immutable, and the pallets
 * it created are corrected through the normal pallet actions. Who may receive
 * at all (`role:admin`) is the route's concern, not re-checked here.
 *
 * @phpstan-type WrittenPallet array{product_id: int, boxes: int, expiration_date: string|null}
 */
class ReceiptService
{
    /** The longest side a bill photo keeps when it is re-encoded: enough to read the bill. */
    private const int PHOTO_MAX_DIMENSION = 2400;

    /** The longest side a re-encoded bill photo is never shrunk below, whatever its size. */
    private const int PHOTO_MIN_DIMENSION = 1600;

    /** The JPEG quality bill photos are re-encoded at. */
    private const int PHOTO_QUALITY = 80;

    /** The type each stored extension is recorded and served as. */
    private const array STORED_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'pdf' => 'application/pdf',
    ];

    public function __construct(
        private readonly ImageOptimizer $images,
        private readonly PdfOptimizer $pdfs,
    ) {}

    /**
     * Create a draft receipt for one warehouse with an optional note and the
     * pallets as written on the bill so far (possibly none yet).
     *
     * @param  list<WrittenPallet>  $pallets
     */
    public function create(int $userId, int $warehouseId, ?string $note, array $pallets): Receipt
    {
        return DB::transaction(function () use ($userId, $warehouseId, $note, $pallets) {
            $receipt = Receipt::create([
                'warehouse_id' => $warehouseId,
                'note' => $note,
                'status' => ReceiptStatus::Draft,
                'created_by' => $userId,
            ]);

            $this->writePallets($receipt, $pallets);

            return $receipt;
        });
    }

    /**
     * Replace the whole pallet list of a draft receipt with the one sent (in
     * that order), and its note when `$changes` carries one (null clears it;
     * absent keeps it).
     *
     * @param  list<WrittenPallet>  $pallets
     * @param  array{note?: string|null}  $changes
     *
     * @throws ReceiptFinalizedException
     */
    public function replacePallets(Receipt $receipt, array $pallets, array $changes): Receipt
    {
        return DB::transaction(function () use ($receipt, $pallets, $changes) {
            $locked = $this->lockDraft($receipt->id);

            $locked->pallets()->delete();
            $this->writePallets($locked, $pallets);

            if (array_key_exists('note', $changes)) {
                $locked->update(['note' => $changes['note']]);
            }

            return $locked;
        });
    }

    /**
     * Store a photo or PDF of the bill on the private disk and record it,
     * compressed (see photo() and PdfOptimizer); the record's type, size and
     * download name describe the bytes actually stored. The file is written
     * before the receipt is locked and removed again when the record cannot
     * be created, so a refused upload leaves nothing behind.
     *
     * @throws ReceiptFinalizedException
     * @throws InvalidReceiptStateException `receipt_file_limit_reached` beyond ReceiptFile::MAX_PER_RECEIPT
     * @throws ValidationException when a photo cannot be decoded
     */
    public function addFile(Receipt $receipt, UploadedFile $upload): ReceiptFile
    {
        if ($receipt->isFinalized()) {
            throw new ReceiptFinalizedException;
        }

        $original = (string) file_get_contents($upload->getRealPath());

        [$contents, $extension] = $upload->getMimeType() === 'application/pdf'
            ? [$this->pdfs->optimise($original), 'pdf']
            : $this->photo($original);

        $path = ReceiptFile::DIRECTORY.'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk(ReceiptFile::DISK)->put($path, $contents)) {
            throw new RuntimeException('Unable to store the receipt bill file.');
        }

        try {
            return DB::transaction(function () use ($receipt, $upload, $path, $contents, $extension) {
                $locked = $this->lockDraft($receipt->id);

                if ($locked->files()->count() >= ReceiptFile::MAX_PER_RECEIPT) {
                    throw new InvalidReceiptStateException(
                        'receipt_file_limit_reached',
                        __('messages.receipt_file_limit_reached', ['max' => ReceiptFile::MAX_PER_RECEIPT]),
                    );
                }

                return $locked->files()->create([
                    'path' => $path,
                    'original_name' => $this->downloadName($upload->getClientOriginalName(), $extension),
                    'mime_type' => self::STORED_MIME_TYPES[$extension],
                    'size' => strlen($contents),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk(ReceiptFile::DISK)->delete($path);

            throw $exception;
        }
    }

    /**
     * A bill photo re-encoded to JPEG (quality 80, longest side at most 2400
     * px and never shrunk below 1600, orientation applied, metadata and
     * appended bytes dropped). Always the re-encode, even in the rare case it
     * is larger than the upload: an untrusted photo is never stored as sent.
     *
     * @return array{0: string, 1: string} the bytes to store and their extension
     *
     * @throws ValidationException when the photo cannot be decoded
     */
    private function photo(string $original): array
    {
        try {
            [$encoded] = $this->images->optimise($original, 'jpg', self::PHOTO_MAX_DIMENSION, self::PHOTO_QUALITY, self::PHOTO_MIN_DIMENSION);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return [$encoded, 'jpg'];
    }

    /**
     * Remove one bill file of a draft receipt, the record under the receipt
     * lock and the stored bytes once that has committed.
     *
     * @throws ReceiptFinalizedException
     */
    public function deleteFile(Receipt $receipt, ReceiptFile $file): void
    {
        DB::transaction(function () use ($receipt, $file) {
            $this->lockDraft($receipt->id);

            $receipt->files()->whereKey($file->id)->delete();
        });

        Storage::disk(ReceiptFile::DISK)->delete($file->path);
    }

    /**
     * Finalize a draft receipt: with the receipt locked, refuse it while it
     * has no bill file (`receipt_has_no_files`) or no pallet entry
     * (`receipt_has_no_pallets`); otherwise create one unplaced pallet per
     * entry (its product, boxes and expiry, no cell, owned by the receipt's
     * warehouse through `receipt_id`), link each entry to its pallet and
     * stamp the receipt finalized.
     *
     * @throws ReceiptFinalizedException
     * @throws InvalidReceiptStateException
     */
    public function finalize(Receipt $receipt, int $userId): Receipt
    {
        return DB::transaction(function () use ($receipt, $userId) {
            $locked = $this->lockDraft($receipt->id);

            if (! $locked->files()->exists()) {
                throw new InvalidReceiptStateException('receipt_has_no_files', __('messages.receipt_has_no_files'));
            }

            $entries = $locked->pallets()
                ->select(['id', 'receipt_id', 'product_id', 'boxes', 'expiration_date', 'pallet_id'])
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            if ($entries->isEmpty()) {
                throw new InvalidReceiptStateException('receipt_has_no_pallets', __('messages.receipt_has_no_pallets'));
            }

            foreach ($entries as $entry) {
                $pallet = Pallet::create([
                    'product_id' => $entry->product_id,
                    'cell_id' => null,
                    'receipt_id' => $locked->id,
                    'expiration_date' => $entry->expiration_date?->toDateString(),
                    'remaining_boxes' => $entry->boxes,
                ]);

                $entry->update(['pallet_id' => $pallet->id]);
            }

            $locked->update([
                'status' => ReceiptStatus::Finalized,
                'finalized_by' => $userId,
                'finalized_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Lock the receipt row for the surrounding transaction and refuse a
     * finalized one, re-read under the lock rather than trusted as bound.
     *
     * @throws ReceiptFinalizedException
     */
    private function lockDraft(int $receiptId): Receipt
    {
        $receipt = Receipt::query()->lockForUpdate()->findOrFail($receiptId);

        if ($receipt->isFinalized()) {
            throw new ReceiptFinalizedException;
        }

        return $receipt;
    }

    /**
     * Record the pallets in the order given, `position` 0 upwards.
     *
     * @param  list<WrittenPallet>  $pallets
     */
    private function writePallets(Receipt $receipt, array $pallets): void
    {
        $receipt->pallets()->createMany(array_map(fn (array $pallet, int $position): array => [
            'product_id' => $pallet['product_id'],
            'boxes' => $pallet['boxes'],
            'expiration_date' => $pallet['expiration_date'],
            'position' => $position,
        ], $pallets, array_keys($pallets)));
    }

    /**
     * The name a bill file downloads under: the client's base name without
     * path or control characters, with the extension of what was stored (a
     * re-encoded photo is a JPEG whatever it was sent as).
     */
    private function downloadName(string $clientName, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $clientName), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $base));

        return Str::limit($base !== '' ? $base : 'bill', 200, '').'.'.$extension;
    }
}

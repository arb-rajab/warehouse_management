<?php

namespace App\Services;

use App\Enums\ReceiptLineStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\InvalidReceiptStateException;
use App\Exceptions\ReceiptFinalizedException;
use App\Models\Pallet;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Truck receiving against a bill, for Api\V1\ReceiptController and its file
 * and line siblings, in the controller/service split of PalletActionService
 * and CellVerificationService.
 *
 * Every change to a receipt runs in a transaction holding a row lock on the
 * receipt (lockDraft()), which also refuses a finalized receipt with 409
 * `receipt_finalized`: once finalized a receipt is immutable, and the pallets
 * it created are corrected through the normal pallet actions. Who may receive
 * at all (`role:admin`) is the route's concern, not re-checked here.
 *
 * @phpstan-type ActualPallet array{boxes: int, expiration_date: string|null}
 */
class ReceiptService
{
    /** The longest side a bill photo keeps when it is re-encoded: enough to read the bill. */
    private const int PHOTO_MAX_DIMENSION = 2400;

    public function __construct(private readonly ImageOptimizer $images) {}

    /**
     * Create a draft receipt for one warehouse with the bill's header and its
     * lines, every line pending until it is checked.
     *
     * @param  list<array{product_id: int, expected_pallets_count: int, expected_boxes: int, expected_expiration_date: string|null}>  $lines
     */
    public function create(int $userId, int $warehouseId, string $billName, ?string $billInfo, array $lines): Receipt
    {
        return DB::transaction(function () use ($userId, $warehouseId, $billName, $billInfo, $lines) {
            $receipt = Receipt::create([
                'warehouse_id' => $warehouseId,
                'bill_name' => $billName,
                'bill_info' => $billInfo,
                'status' => ReceiptStatus::Draft,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $receipt->lines()->create([
                    'product_id' => $line['product_id'],
                    'is_extra' => false,
                    'expected_pallets_count' => $line['expected_pallets_count'],
                    'expected_boxes' => $line['expected_boxes'],
                    'expected_expiration_date' => $this->normalizeDate($line['expected_expiration_date']),
                    'status' => ReceiptLineStatus::Pending,
                ]);
            }

            return $receipt;
        });
    }

    /**
     * Store a photo or PDF of the bill on the private disk and record it.
     * Photos are re-encoded to JPEG (dropping metadata and anything appended
     * to them), as product images are; a PDF is kept as sent. The file is
     * written before the receipt is locked and removed again when the record
     * cannot be created, so a refused upload leaves nothing behind.
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

        $contents = (string) file_get_contents($upload->getRealPath());
        $isPdf = $upload->getMimeType() === 'application/pdf';

        if (! $isPdf) {
            try {
                [$contents] = $this->images->optimise($contents, 'jpg', self::PHOTO_MAX_DIMENSION);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['file' => $exception->getMessage()]);
            }
        }

        $extension = $isPdf ? 'pdf' : 'jpg';
        $path = ReceiptFile::DIRECTORY.'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk(ReceiptFile::DISK)->put($path, $contents)) {
            throw new RuntimeException('Unable to store the receipt bill file.');
        }

        try {
            return DB::transaction(function () use ($receipt, $upload, $path, $contents, $isPdf, $extension) {
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
                    'mime_type' => $isPdf ? 'application/pdf' : 'image/jpeg',
                    'size' => strlen($contents),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk(ReceiptFile::DISK)->delete($path);

            throw $exception;
        }
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
     * Record the pallets that actually arrived for a line, replacing what was
     * recorded before (an empty list: nothing of it arrived), with an optional
     * note, and set the line's status from checkStatus(). An extra line keeps
     * the `extra` status: nothing is expected of it to compare against.
     *
     * @param  list<ActualPallet>  $pallets
     *
     * @throws ReceiptFinalizedException
     */
    public function checkLine(Receipt $receipt, ReceiptLine $line, array $pallets, ?string $note): ReceiptLine
    {
        return DB::transaction(function () use ($receipt, $line, $pallets, $note) {
            $this->lockDraft($receipt->id);

            $lockedLine = $receipt->lines()->findOrFail($line->id);

            $this->replaceActualPallets($lockedLine, $pallets);

            $lockedLine->update([
                'status' => $lockedLine->is_extra
                    ? ReceiptLineStatus::Extra
                    : self::checkStatus(
                        $lockedLine->expected_pallets_count,
                        $lockedLine->expected_boxes,
                        $lockedLine->expected_expiration_date?->toDateString(),
                        $pallets,
                    ),
                'note' => $note,
            ]);

            return $lockedLine;
        });
    }

    /**
     * Add a line for pallets that arrived without being on the bill (another
     * product, or more pallets of a billed one): `is_extra`, status `extra`,
     * nothing expected.
     *
     * @param  list<ActualPallet>  $pallets
     *
     * @throws ReceiptFinalizedException
     */
    public function addExtraLine(Receipt $receipt, int $productId, array $pallets, ?string $note): ReceiptLine
    {
        return DB::transaction(function () use ($receipt, $productId, $pallets, $note) {
            $locked = $this->lockDraft($receipt->id);

            $line = $locked->lines()->create([
                'product_id' => $productId,
                'is_extra' => true,
                'expected_pallets_count' => null,
                'expected_boxes' => null,
                'expected_expiration_date' => null,
                'status' => ReceiptLineStatus::Extra,
                'note' => $note,
            ]);

            $this->replaceActualPallets($line, $pallets);

            return $line;
        });
    }

    /**
     * Delete an extra line of a draft receipt with its pallets. A line of the
     * bill itself stays: the bill says it, so it is checked (an empty check
     * records that nothing arrived) rather than removed.
     *
     * @throws ReceiptFinalizedException
     * @throws InvalidReceiptStateException `receipt_line_not_extra`
     */
    public function deleteExtraLine(Receipt $receipt, ReceiptLine $line): void
    {
        DB::transaction(function () use ($receipt, $line) {
            $this->lockDraft($receipt->id);

            $lockedLine = $receipt->lines()->findOrFail($line->id);

            if (! $lockedLine->is_extra) {
                throw new InvalidReceiptStateException('receipt_line_not_extra', __('messages.receipt_line_not_extra'));
            }

            $lockedLine->delete();
        });
    }

    /**
     * Finalize a draft receipt: with the receipt locked, refuse it while it
     * has no bill file (`receipt_has_no_files`), a line is still pending
     * (`receipt_has_pending_lines`) or no pallet arrived at all
     * (`receipt_has_no_pallets`); otherwise create one unplaced pallet per
     * actual pallet (the line's product, its boxes and expiry, no cell, owned
     * by the receipt's warehouse through its line), link each entry to its
     * pallet and stamp the receipt finalized. Discrepancies are allowed: a
     * mismatched line finalizes with what actually arrived.
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

            if ($locked->lines()->where('status', ReceiptLineStatus::Pending)->exists()) {
                throw new InvalidReceiptStateException('receipt_has_pending_lines', __('messages.receipt_has_pending_lines'));
            }

            $lines = $locked->lines()
                ->select(['id', 'receipt_id', 'product_id'])
                ->with(['actualPallets' => fn ($query) => $query
                    ->select(['id', 'receipt_line_id', 'boxes', 'expiration_date', 'pallet_id'])
                    ->orderBy('id')])
                ->orderBy('id')
                ->get();

            if ($lines->every(fn (ReceiptLine $line): bool => $line->actualPallets->isEmpty())) {
                throw new InvalidReceiptStateException('receipt_has_no_pallets', __('messages.receipt_has_no_pallets'));
            }

            foreach ($lines as $line) {
                foreach ($line->actualPallets as $actual) {
                    $pallet = Pallet::create([
                        'product_id' => $line->product_id,
                        'cell_id' => null,
                        'receipt_line_id' => $line->id,
                        'expiration_date' => $actual->expiration_date?->toDateString(),
                        'remaining_boxes' => $actual->boxes,
                    ]);

                    $actual->update(['pallet_id' => $pallet->id]);
                }
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
     * Whether a checked bill line matches its bill: as many pallets as
     * expected, each with the expected boxes and the expected expiry. A null
     * expected value is not compared (a bill without an expiry matches any).
     * Anything else, including nothing arriving, is a mismatch. Pure, so it
     * can be unit tested without a database.
     *
     * @param  list<ActualPallet>  $pallets  expiry dates as `Y-m-d`
     */
    public static function checkStatus(?int $expectedPalletsCount, ?int $expectedBoxes, ?string $expectedExpirationDate, array $pallets): ReceiptLineStatus
    {
        if ($expectedPalletsCount !== null && count($pallets) !== $expectedPalletsCount) {
            return ReceiptLineStatus::Mismatched;
        }

        foreach ($pallets as $pallet) {
            if ($expectedBoxes !== null && $pallet['boxes'] !== $expectedBoxes) {
                return ReceiptLineStatus::Mismatched;
            }

            if ($expectedExpirationDate !== null && $pallet['expiration_date'] !== $expectedExpirationDate) {
                return ReceiptLineStatus::Mismatched;
            }
        }

        return ReceiptLineStatus::Matched;
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
     * @param  list<ActualPallet>  $pallets
     */
    private function replaceActualPallets(ReceiptLine $line, array $pallets): void
    {
        $line->actualPallets()->delete();

        $line->actualPallets()->createMany(array_map(fn (array $pallet): array => [
            'boxes' => $pallet['boxes'],
            'expiration_date' => $pallet['expiration_date'],
        ], $pallets));
    }

    /**
     * A date-only value, so SQLite stores no time component (see
     * PalletActionService::normalizeExpirationDate()).
     */
    private function normalizeDate(?string $date): ?string
    {
        return $date !== null ? Carbon::parse($date)->toDateString() : null;
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

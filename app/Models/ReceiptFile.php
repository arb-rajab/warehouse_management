<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A photo or PDF of a receipt's bill. `path` is relative to the filesystem
 * disk the file was written to (the way `products.warehouse_image_path` is);
 * the row never holds a URL, and no payload exposes the path: clients read
 * the authenticated download URL instead.
 *
 * @property int $id
 * @property int $receipt_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Receipt $receipt
 */
#[Fillable(['receipt_id', 'path', 'original_name', 'mime_type', 'size'])]
class ReceiptFile extends Model
{
    /** @use HasFactory<ReceiptFileFactory> */
    use HasFactory;

    /**
     * The private disk bills are written to (`storage/app/private`): never
     * `public`, so a bill is only readable through the authenticated download
     * endpoint (`api.v1.receipts.files.show`).
     */
    public const string DISK = 'local';

    /** The directory on DISK that holds every receipt's bill files. */
    public const string DIRECTORY = 'receipt-bills';

    /** How many bill files one receipt may hold. */
    public const int MAX_PER_RECEIPT = 10;

    /** The largest upload accepted, in kilobytes (the `max` validation rule's unit). */
    public const int MAX_KILOBYTES = 10240;

    /**
     * The accepted upload extensions (the `mimes` rule checks the content).
     * Photos are re-encoded to JPEG before they are stored; a PDF is kept as sent.
     *
     * @var list<string>
     */
    public const array MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * The file's bytes as an inline response (its type and original name),
     * never cached and never sniffed. The one place a bill is read, shared by
     * the mobile API and the admin web download; the storage path stays here.
     * Aborts with 404 when the bytes are gone from the disk.
     */
    public function inlineResponse(): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        abort_unless($disk->exists($this->path), 404);

        return $disk->response($this->path, $this->original_name, [
            'Content-Type' => $this->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

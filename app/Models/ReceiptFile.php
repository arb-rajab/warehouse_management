<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or PDF of a receipt's bill. `path` is relative to the filesystem
 * disk the file was written to (the way `products.warehouse_image_path` is);
 * the row never holds a URL.
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
}

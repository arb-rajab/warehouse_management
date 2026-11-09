<?php

namespace App\Models;

use App\Enums\ReceiptLineStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of pallets of one product on a receipt: a line of the bill, or (when
 * `is_extra`) a group that arrived without being on it. `expected_*` hold what
 * the bill says (`expected_boxes` is boxes per pallet) and are null on an
 * extra line. The pallets actually received are the line's `actualPallets`
 * while the receipt is a draft, and its real `pallets` once finalized; their
 * product is always this line's product.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $product_id
 * @property bool $is_extra
 * @property int|null $expected_pallets_count
 * @property int|null $expected_boxes
 * @property CarbonImmutable|null $expected_expiration_date
 * @property ReceiptLineStatus $status
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Receipt $receipt
 * @property-read Product $product
 */
#[Fillable([
    'receipt_id',
    'product_id',
    'is_extra',
    'expected_pallets_count',
    'expected_boxes',
    'expected_expiration_date',
    'status',
    'note',
])]
class ReceiptLine extends Model
{
    /** @use HasFactory<ReceiptLineFactory> */
    use HasFactory;

    /**
     * A line created without a status is pending, as in the column default.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReceiptLineStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_extra' => 'boolean',
            'expected_expiration_date' => 'date',
            'status' => ReceiptLineStatus::class,
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The pallets the admin recorded as received for this line (draft entries,
     * linked to the real pallet once the receipt is finalized).
     *
     * @return HasMany<ReceiptLinePallet, $this>
     */
    public function actualPallets(): HasMany
    {
        return $this->hasMany(ReceiptLinePallet::class);
    }

    /**
     * The real pallets created for this line when the receipt was finalized,
     * placed or not.
     *
     * @return HasMany<Pallet, $this>
     */
    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }
}

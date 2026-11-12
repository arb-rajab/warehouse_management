<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptPalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pallet of a receipt as written on the bill by the merchant (and
 * physically checked by the admin): its product, boxes and optional expiry,
 * at `position` in the order the admin typed them. `pallet_id` is set when
 * the receipt is finalized and the real Pallet row is created, and goes null
 * again if that pallet is later deleted. See .ai/rules/receiving.md.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $product_id
 * @property int $boxes
 * @property CarbonImmutable|null $expiration_date
 * @property string|null $note copied onto the pallet finalizing creates
 * @property int|null $pallet_id
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Receipt $receipt
 * @property-read Product $product
 * @property-read Pallet|null $pallet
 */
#[Fillable(['receipt_id', 'product_id', 'boxes', 'expiration_date', 'note', 'pallet_id', 'position'])]
class ReceiptPallet extends Model
{
    /** @use HasFactory<ReceiptPalletFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
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
     * The pallet finalizing created for this entry, placed or not.
     *
     * @return BelongsTo<Pallet, $this>
     */
    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }
}

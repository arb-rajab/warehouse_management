<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptLinePalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pallet the admin actually received for a receipt line, recorded while
 * the receipt is a draft. Its product is the line's product. `pallet_id` is
 * set when the receipt is finalized and the real Pallet row is created, and
 * goes null again if that pallet is later deleted.
 *
 * @property int $id
 * @property int $receipt_line_id
 * @property int $boxes
 * @property CarbonImmutable|null $expiration_date
 * @property int|null $pallet_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ReceiptLine $receiptLine
 * @property-read Pallet|null $pallet
 */
#[Fillable(['receipt_line_id', 'boxes', 'expiration_date', 'pallet_id'])]
class ReceiptLinePallet extends Model
{
    /** @use HasFactory<ReceiptLinePalletFactory> */
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
     * @return BelongsTo<ReceiptLine, $this>
     */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }

    /**
     * @return BelongsTo<Pallet, $this>
     */
    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }
}

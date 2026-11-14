<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Shared validation + accessor for a receipt's pallets as written on the bill
 * (`pallets[]`, one entry per pallet with its `product_id`, `boxes`,
 * optional `expiration_date` and optional `note`), for creating a receipt and replacing its
 * pallet list. The product is checked by the rule pallet store uses. Expiry
 * dates may be in the past: the receipt records what arrived.
 */
trait ValidatesReceivedPallets
{
    use ValidatesPalletContents;

    /** How many pallets one receipt may record. */
    private const int MAX_RECEIVED_PALLETS = 500;

    /** The longest note one pallet entry (and so the pallet it creates) may carry. */
    private const int MAX_PALLET_NOTE_LENGTH = 255;

    /**
     * @param  bool  $required  Whether `pallets` must be sent (it may still be empty); otherwise it may be omitted.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function receivedPalletsRules(bool $required): array
    {
        return [
            /** The pallets as written on the bill, in order: one entry per pallet. */
            'pallets' => [$required ? 'present' : 'sometimes', 'array', 'max:'.self::MAX_RECEIVED_PALLETS],
            'pallets.*' => ['array'],
            /** The pallet's product (ids from `GET /api/v1/products`). */
            'pallets.*.product_id' => $this->palletProductRules(),
            /** Boxes on this pallet. */
            'pallets.*.boxes' => ['required', 'integer', 'min:1', 'max:100000'],
            /** This pallet's expiry (`Y-m-d`); omit when it has none. */
            'pallets.*.expiration_date' => ['nullable', 'date'],
            /** A note for this pallet (for example the real product name written on it), trimmed; empty or omitted is none. Copied onto the pallet when the receipt is finalized. */
            'pallets.*.note' => ['nullable', 'string', 'max:'.self::MAX_PALLET_NOTE_LENGTH],
        ];
    }

    /**
     * The validated pallets in the order sent, expiry dates normalized to
     * `Y-m-d` and notes trimmed (an empty one is none); an empty list when
     * none were sent.
     *
     * @return list<array{product_id: int, boxes: int, expiration_date: string|null, note: string|null}>
     */
    public function receivedPallets(): array
    {
        return array_values(array_map(fn (array $pallet): array => [
            'product_id' => (int) $pallet['product_id'],
            'boxes' => (int) $pallet['boxes'],
            'expiration_date' => isset($pallet['expiration_date']) ? Carbon::parse($pallet['expiration_date'])->toDateString() : null,
            'note' => isset($pallet['note']) && trim($pallet['note']) !== '' ? trim($pallet['note']) : null,
        ], $this->array('pallets')));
    }
}

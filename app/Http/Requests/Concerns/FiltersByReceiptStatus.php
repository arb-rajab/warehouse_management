<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ReceiptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation + accessor for the receipts listings (mobile API and
 * admin web) that filter by `status`. Omitting it lists drafts and finalized
 * receipts alike.
 */
trait FiltersByReceiptStatus
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function receiptStatusFilterRules(): array
    {
        return [
            /** Only drafts or only finalized receipts; omit for both. */
            'status' => ['nullable', Rule::enum(ReceiptStatus::class)],
        ];
    }

    /**
     * The validated status filter, or null when the caller sent none.
     */
    public function status(): ?ReceiptStatus
    {
        return $this->enum('status', ReceiptStatus::class);
    }
}

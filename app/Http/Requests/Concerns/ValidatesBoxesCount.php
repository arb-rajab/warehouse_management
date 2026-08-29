<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that remove a number of boxes from a pallet.
 */
trait ValidatesBoxesCount
{
    /**
     * The boxes_count field's rules, for requests that merge it with other rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function boxesCountRules(): array
    {
        return [
            'boxes_count' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * The confirm_empty field's rules, for requests that merge it with other rules.
     * When boxes_count exceeds what remains on the pallet, the caller re-sends the
     * same request with this set to empty the pallet instead of failing with a
     * 409 — the mobile app is expected to prompt for this confirmation itself
     * (it already knows remaining_boxes), not rely on the 409 to learn the count.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function confirmEmptyRules(): array
    {
        return [
            'confirm_empty' => ['nullable', 'boolean'],
        ];
    }
}

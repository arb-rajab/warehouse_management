<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesPalletSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/pallets/bulk/note`: one shared note for the selected pallets
 * (`null` clears it).
 */
class BulkPalletNoteRequest extends FormRequest
{
    use ValidatesPalletSelection;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->palletSelectionRules(),
            /** The note every selected pallet gets, trimmed; `null` (or empty) clears it. */
            'note' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The trimmed note, null to clear it.
     */
    public function note(): ?string
    {
        $note = trim($this->string('note')->value());

        return $note === '' ? null : $note;
    }
}

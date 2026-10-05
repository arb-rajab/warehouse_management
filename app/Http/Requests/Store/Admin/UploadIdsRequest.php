<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A list of upload ids: the files ticked for the library's "Delete
 * selection", or the files an upload field holds and previews.
 */
class UploadIdsRequest extends FormRequest
{
    /** At most one library page's worth (PerPageOptions' largest size). */
    public const MAX_IDS = 100;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(intval(...), array_values($this->validated('ids')));
    }
}

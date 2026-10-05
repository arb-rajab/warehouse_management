<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use App\Services\MultipartUploadService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The uploaded-files library's and the file manager dialog's filters: a
 * search on the original name, one of the four sorts the Blade library
 * offered, and a file kind (the dialog of an image-only field asks for
 * images).
 */
class FilterUploadsRequest extends FormRequest
{
    use FiltersPerPage;

    /** The sorts the library offers, newest first by default. */
    public const SORTS = ['newest', 'oldest', 'smallest', 'largest'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'type' => ['nullable', Rule::in(array_values(array_unique(MultipartUploadService::KINDS)))],
        ];
    }
}

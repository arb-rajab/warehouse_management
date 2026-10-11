<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One file sent by the file manager's upload tab. What the file may be is
 * MultipartUploadService's decision, made from its content; this only checks
 * that a file within the service's size limit arrived.
 */
class StoreUploadRequest extends FormRequest
{
    /** MultipartUploadService's limit, 512 MB, in kilobytes. */
    public const MAX_KILOBYTES = 512 * 1024;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}

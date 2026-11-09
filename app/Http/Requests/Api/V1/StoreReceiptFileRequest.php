<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ReceiptFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/receipts/{receipt}/files` (multipart): one photo or PDF of
 * the bill.
 */
class StoreReceiptFileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** A photo (jpg, jpeg, png, webp) or PDF of the bill, at most 10 MB. */
            'file' => ['required', 'file', 'mimes:'.implode(',', ReceiptFile::MIMES), 'max:'.ReceiptFile::MAX_KILOBYTES],
        ];
    }
}

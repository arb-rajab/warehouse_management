<?php

namespace App\Http\Requests\Store\Admin;

use App\Services\MultipartUploadService;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:'.(MultipartUploadService::MAX_BYTES / 1024)]];
    }
}

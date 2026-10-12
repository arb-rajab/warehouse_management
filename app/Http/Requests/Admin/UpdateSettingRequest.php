<?php

namespace App\Http\Requests\Admin;

use App\Models\Row;
use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qr_code_width' => ['required', 'integer', 'min:'.Setting::MIN_QR_CODE_SIZE, 'max:'.Setting::MAX_QR_CODE_SIZE],
            'qr_code_height' => ['required', 'integer', 'min:'.Setting::MIN_QR_CODE_SIZE, 'max:'.Setting::MAX_QR_CODE_SIZE],
            'allowed_open_levels' => ['required', 'array', 'min:1'],
            'allowed_open_levels.*' => ['integer', 'min:1', 'max:'.Row::highestFlatsCount()],
        ];
    }
}

<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGoogleMapRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'google_map' => ['required_without_all:google_map_longtitude,google_map_latitude', 'boolean'],
            'google_map_longtitude' => ['required_with:google_map_latitude', 'nullable', 'numeric', 'between:-180,180'],
            'google_map_latitude' => ['required_with:google_map_longtitude', 'nullable', 'numeric', 'between:-90,90'],
        ];
    }
}

<?php

namespace App\Http\Requests\Store\Admin;

use App\Support\GoogleMapsKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Required only while the map is switched on and neither a saved key
            // nor MAP_API_KEY supplies one; blank otherwise keeps the saved key.
            'google_map_api_key' => [
                Rule::requiredIf(fn (): bool => $this->boolean('google_map') && GoogleMapsKey::get() === null),
                'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/',
            ],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->except(['_token'])), ['google_map', 'google_map_longtitude', 'google_map_latitude', 'google_map_api_key']) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
        }];
    }
}

<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'integer'], 'status' => ['required', Rule::enum(TripStatus::class)]];
    }
}

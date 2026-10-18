<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\CheckpointStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckpointStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CheckpointStatus::class)]];
    }
}

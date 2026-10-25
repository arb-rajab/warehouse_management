<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\CheckpointStatus;
use Illuminate\Validation\Rule;

class UpdateCheckpointStatusRequest extends TripWriteRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'status' => ['required', Rule::enum(CheckpointStatus::class)]];
    }
}

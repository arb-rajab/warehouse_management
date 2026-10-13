<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    use ValidatesStoreTrip;

    protected function prepareForValidation(): void
    {
        Trip::query()->findOrFail($this->input('id'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'integer'], ...$this->tripRules(), ...$this->checkpointRules()];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateCheckpointReferences($validator, $this->integer('id'))];
    }
}

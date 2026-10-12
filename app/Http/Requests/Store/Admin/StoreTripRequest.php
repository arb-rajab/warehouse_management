<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    use ValidatesStoreTrip;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->tripRules(), ...$this->checkpointRules()];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateDeparture($validator);
            $this->validateCheckpointReferences($validator);
        }];
    }
}

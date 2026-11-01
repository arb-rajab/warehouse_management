<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesAddress;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The address the app saves (POST /user/shipping/create and /update), held to
 * the rules of the storefront's own address form: a street address, a phone
 * and, while the store has an active country, a country; a state or city only
 * when it names an active one of that country and state. The app sends 0 for
 * "none", which the database holds as no state or city, so 0 is read as unset
 * rather than refused.
 */
class SaveShippingAddressRequest extends FormRequest
{
    use ValidatesAddress;

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['state_id', 'city_id'])
            ->filter(fn (string $field): bool => $this->has($field) && (string) $this->input($field) === '0')
            ->mapWithKeys(fn (string $field): array => [$field => null])
            ->all());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->addressRules(),
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The API's own error shape: the first error as `message`, which the app shows.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'result' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}

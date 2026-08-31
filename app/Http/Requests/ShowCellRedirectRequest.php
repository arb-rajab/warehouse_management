<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowCellRedirectRequest extends FormRequest
{
    use ResolvesSlotFromCoordinates;

    /**
     * Merge the route's path segments into the request's input so
     * ResolvesSlotFromCoordinates (built for form input) can see them.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->route()->parameters());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->coordinateRules();
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->resolveSlot($validator));
    }

    /**
     * A scanned label with a tampered or stale coordinate has no page to
     * redirect back to, so a failed lookup is a plain 404 rather than the
     * default redirect-back-with-errors.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new NotFoundHttpException;
    }
}

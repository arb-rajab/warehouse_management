<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesLoginCredentials;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use ValidatesLoginCredentials;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->loginCredentialRules();
    }
}

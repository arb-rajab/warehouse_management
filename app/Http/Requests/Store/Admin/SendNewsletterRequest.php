<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNewsletterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
            'user_emails' => ['required_without:subscriber_emails', 'array', 'max:1000'],
            'user_emails.*' => ['required', 'email:rfc', 'distinct', Rule::exists('users', 'email')],
            'subscriber_emails' => ['required_without:user_emails', 'array', 'max:1000'],
            'subscriber_emails.*' => ['required', 'email:rfc', 'distinct', Rule::exists('subscribers', 'email')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => clean_html($this->input('content'))]);
        }
    }
}

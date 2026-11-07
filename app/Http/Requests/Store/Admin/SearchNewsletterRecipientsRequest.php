<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSearchOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchNewsletterRecipientsRequest extends FormRequest
{
    use ValidatesSearchOptions;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...$this->searchOptionsRules(), 'kind' => ['required', Rule::in(['users', 'subscribers'])]];
    }
}

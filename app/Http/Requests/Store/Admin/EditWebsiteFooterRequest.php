<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreRole;
use Illuminate\Foundation\Http\FormRequest;

class EditWebsiteFooterRequest extends FormRequest
{
    use ValidatesStoreRole;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->languageRules('nullable');
    }
}

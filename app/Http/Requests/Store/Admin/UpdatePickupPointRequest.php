<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use App\Http\Requests\Concerns\ValidatesStorePickupPoint;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePickupPointRequest extends FormRequest
{
    use ValidatesStoreLanguage;
    use ValidatesStorePickupPoint;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->pickupPointRules(), ...$this->languageRules('required')];
    }
}

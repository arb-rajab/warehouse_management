<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreSeller;
use Illuminate\Foundation\Http\FormRequest;

class StoreSellerRequest extends FormRequest
{
    use ValidatesStoreSeller;

    /**
     * Get the validation rules that should apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sellerRules(null, true);
    }
}

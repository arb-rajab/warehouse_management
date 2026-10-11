<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreOffer;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    use ValidatesStoreOffer;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->offerRules();
    }
}

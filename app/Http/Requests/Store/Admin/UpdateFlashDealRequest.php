<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreFlashDeal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFlashDealRequest extends FormRequest
{
    use ValidatesStoreFlashDeal;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->flashDealRules(true);
    }
}

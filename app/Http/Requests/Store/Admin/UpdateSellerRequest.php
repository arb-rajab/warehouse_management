<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreSeller;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerRequest extends FormRequest
{
    use ValidatesStoreSeller;

    /**
     * Get the validation rules that should apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Shop $seller */
        $seller = $this->route('seller');

        return $this->sellerRules($seller->user_id, false);
    }
}

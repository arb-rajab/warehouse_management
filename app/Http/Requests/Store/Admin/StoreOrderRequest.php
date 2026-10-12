<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    use ValidatesStoreOrder;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->orderRules(), 'rep_id' => [$this->routeIs('feat-order.store') ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')->where('is_rep', 1)], 'submission_key' => ['required', 'uuid']];
    }
}

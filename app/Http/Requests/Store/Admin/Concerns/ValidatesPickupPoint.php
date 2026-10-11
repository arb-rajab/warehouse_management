<?php

namespace App\Http\Requests\Store\Admin\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesPickupPoint
{
    protected function preparePickupPointForValidation(): void
    {
        $this->merge(['pick_up_status' => $this->boolean('pick_up_status')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pickupPointRules(bool $editing): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:65535'],
            'phone' => ['required', 'string', 'max:15'],
            'pick_up_status' => ['required', 'boolean'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'lang' => $editing
                ? ['required', 'string', Rule::exists('languages', 'code')]
                : ['prohibited'],
        ];
    }
}

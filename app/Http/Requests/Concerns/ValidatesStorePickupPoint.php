<?php

namespace App\Http\Requests\Concerns;

use App\Models\StoreUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStorePickupPoint
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function pickupPointRules(): array
    {
        return ['name' => ['required', 'string', 'max:50'], 'address' => ['required', 'string', 'max:65535'], 'phone' => ['required', 'string', 'max:15'], 'pick_up_status' => ['required', 'boolean'], 'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where(fn ($query) => $query->whereIn('user_id', StoreUser::query()->select('id')))]];
    }
}

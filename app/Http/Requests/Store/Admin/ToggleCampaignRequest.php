<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleCampaignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['value' => ['required', 'boolean']];
    }
}

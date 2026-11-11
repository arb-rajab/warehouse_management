<?php

namespace App\Http\Requests\Store\Admin;

use App\Support\StoreDeliverySettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The delivery lead days and timezone on the Order configuration page
 * (StoreDeliverySettings). Both are optional: an empty field falls back to the
 * server's `.env` value. The timezone must be exactly one of PHP's identifiers
 * (DateTimeZone::listIdentifiers). The browser checks a copy of these rules
 * (resources/js/lib/deliverySettingsRules.json, kept equal by
 * DeliverySettingsClientValidationParityTest).
 */
class UpdateDeliverySettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'delivery_lead_days' => ['nullable', 'integer', 'min:'.StoreDeliverySettings::MIN_LEAD_DAYS, 'max:'.StoreDeliverySettings::MAX_LEAD_DAYS],
            'delivery_timezone' => ['nullable', 'string', Rule::in(StoreDeliverySettings::timezones())],
        ];
    }
}

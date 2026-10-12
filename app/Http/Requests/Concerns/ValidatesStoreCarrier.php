<?php

namespace App\Http\Requests\Concerns;

use App\Models\Carrier;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesStoreCarrier
{
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function carrierRules(?Carrier $carrier = null): array
    {
        $paid = Rule::excludeIf($this->boolean('shipping_type'));

        return [
            'carrier_name' => ['required', 'string', 'max:255'],
            'transit_time' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'integer', $this->retainableImageUploadRule('logo', $carrier?->logo)],
            'shipping_type' => ['required', 'boolean'],
            'billing_type' => [$paid, 'required', Rule::in(['weight_based', 'price_based'])],
            'delimiter1' => [$paid, 'required', 'array', 'list', 'min:1'],
            'delimiter2' => [$paid, 'required', 'array', 'list', 'min:1'],
            'delimiter1.*' => ['required', 'numeric', 'regex:/^-?\d{1,23}(?:\.\d{1,2})?$/D'],
            'delimiter2.*' => ['required', 'numeric', 'regex:/^-?\d{1,23}(?:\.\d{1,2})?$/D', 'gt:delimiter1.*'],
            'zones' => [$paid, 'required', 'array', 'list', 'min:1'],
            'zones.*' => ['required', 'integer', 'distinct', Rule::exists('zones', 'id')],
            'carrier_price' => [$paid, 'required', 'array', 'min:1'],
            'carrier_price.*' => ['required', 'array', 'list', 'min:1'],
            'carrier_price.*.*' => ['required', 'numeric', 'regex:/^-?\d{1,6}(?:\.\d{1,2})?$/D'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('shipping_type') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $starts = $this->input('delimiter1');
            $ends = $this->input('delimiter2');
            $zones = $this->input('zones');
            $prices = $this->input('carrier_price');
            if (count($starts) !== count($ends)) {
                $validator->errors()->add('delimiter2', __('validation.same', ['attribute' => 'delimiter2', 'other' => 'delimiter1']));
            }
            foreach ($starts as $index => $start) {
                if ($index > 0 && isset($ends[$index - 1]) && ! BigDecimal::of((string) $start)->isEqualTo((string) $ends[$index - 1])) {
                    $validator->errors()->add("delimiter1.$index", __('validation.same', ['attribute' => 'delimiter1', 'other' => 'delimiter2']));
                }
            }
            $selected = array_map('strval', $zones);
            $priced = array_map('strval', array_keys($prices));
            sort($selected);
            sort($priced);
            if ($selected !== $priced) {
                $validator->errors()->add('carrier_price', __('validation.same', ['attribute' => 'carrier_price', 'other' => 'zones']));
            }
            foreach ($prices as $zone => $row) {
                if (count($row) !== count($starts)) {
                    $validator->errors()->add("carrier_price.$zone", __('validation.same', ['attribute' => 'carrier_price', 'other' => 'delimiter1']));
                }
            }
        }];
    }
}

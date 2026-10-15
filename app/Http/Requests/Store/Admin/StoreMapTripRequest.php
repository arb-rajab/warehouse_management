<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMapTripRequest extends FormRequest
{
    use ValidatesStoreTrip;

    protected function prepareForValidation(): void
    {
        if (! $this->filled('trip_id')) {
            $this->merge(['due_date' => $this->input('due_date', now()->toDateString())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->tripRules(),
            'due_date' => ['required_without:trip_id', 'nullable', 'date'],
            'trip_id' => ['nullable', 'integer', Rule::exists('trips', 'id')],
            'markers' => ['required', 'array', 'min:1'],
            'markers.*' => ['required', 'array'],
            'markers.*.order_id' => ['required', 'integer', 'distinct', Rule::exists('orders', 'id')->where('delivery_status', 'on_delivery')],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! $this->filled('trip_id')) {
                $this->validateDeparture($validator);
            } else {
                $trip = Trip::query()->find($this->integer('trip_id'));
                if ($trip->truck_id !== $this->integer('truck_id') || $trip->status === 'closed') {
                    $validator->errors()->add('trip_id', __('validation.exists', ['attribute' => 'trip']));
                }
            }
            foreach ($this->input('markers', []) as $index => $marker) {
                if (Checkpoint::query()->where('relationable_type', Order::class)->where('relationable_id', $marker['order_id'])->whereIn('trip_id', Trip::query()->select('id')->where('status', '<>', 'closed'))->exists()) {
                    $validator->errors()->add("markers.$index.order_id", __('validation.unique', ['attribute' => 'order']));
                }
            }
        }];
    }
}

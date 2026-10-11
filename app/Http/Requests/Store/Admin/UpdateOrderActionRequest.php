<?php

namespace App\Http\Requests\Store\Admin;

use App\Services\OrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderActionRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'orders.details' => ['order_id' => ['required', 'integer']],
            'orders.update_delivery_status' => ['order_id' => ['required', 'integer'], 'status' => ['required', Rule::in(OrderService::DELIVERY_STATUSES)]],
            'orders.update_payment_status' => ['order_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['paid', 'unpaid'])]],
            'orders.update_tracking_code' => ['order_id' => ['required', 'integer'], 'tracking_code' => ['nullable', 'string', 'max:255']],
            'orders.update_manager_notes' => ['order_id' => ['required_without:id', 'integer'], 'id' => ['required_without:order_id', 'integer'], 'notes' => ['nullable', 'string', 'max:2000']],
            'order.item.edit_price' => ['id' => ['required', 'integer'], 'admin_price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']],
            default => [],
        };
    }
}

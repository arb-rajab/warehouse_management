<?php

namespace App\Http\Resources\Store;

use App\Notifications\OrderNotification;
use App\Notifications\UnorderedCartNotification;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $id
 * @property-read string $type
 * @property-read array<string, mixed> $data
 * @property-read CarbonInterface|null $created_at
 */
class AdminNotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->data;
        $order = $this->type === OrderNotification::class;
        $cart = $this->type === UnorderedCartNotification::class;

        return [
            'id' => $this->id,
            'kind' => $order ? 'order' : ($cart ? 'cart' : 'other'),
            'order_code' => $order ? ($data['order_code'] ?? null) : null,
            'status' => $order ? ($data['status'] ?? null) : null,
            'customer_name' => $cart ? ($data['user_name'] ?? null) : null,
            'message' => $cart ? ($data['message'] ?? null) : null,
            'url' => $order && isset($data['order_id']) ? route('all_orders.show', encrypt($data['order_id']))
                : ($cart && isset($data['user_id']) ? route('customers.whatsapp_contact', $data['user_id']) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

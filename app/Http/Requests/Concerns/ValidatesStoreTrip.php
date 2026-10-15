<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CheckpointType;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesStoreTrip
{
    /** @return array<string, mixed> */
    protected function tripRules(): array
    {
        return [
            'driver_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('user_type', 'driver')],
            'truck_id' => ['required', 'integer', Rule::exists('trucks', 'id')],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The trip form's checkpoint rows. Each row's reference must exist in
     * the table its type names (an order for a delivery, a store user
     * otherwise), so a missing one is a field error rather than a 404.
     *
     * @return array<string, mixed>
     */
    protected function checkpointRules(): array
    {
        $rules = [
            'checkpoints_array' => ['required', 'array', 'min:1'],
            'checkpoints_array.*' => ['required', 'array:id,checkpoint_type,checkpoint_related_id,checkpoint_notes'],
            'checkpoints_array.*.id' => ['nullable', 'integer', 'min:0'],
            'checkpoints_array.*.checkpoint_type' => ['required', Rule::enum(CheckpointType::class)],
            'checkpoints_array.*.checkpoint_related_id' => ['required', 'integer'],
            'checkpoints_array.*.checkpoint_notes' => ['nullable', 'string', 'max:1000'],
        ];
        $rows = $this->input('checkpoints_array');
        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            $table = is_array($row) && ($row['checkpoint_type'] ?? null) === CheckpointType::Delivery->value ? 'orders' : 'users';
            $rules["checkpoints_array.$index.checkpoint_related_id"] = [Rule::exists($table, 'id')];
        }

        return $rules;
    }

    /**
     * A stored row (non-zero id) must belong to the trip being edited, appear
     * once and keep its type and reference; the create form has no stored
     * rows, so any id there is refused.
     */
    protected function validateCheckpointReferences(Validator $validator, ?int $tripId = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $seen = [];
        foreach ($this->input('checkpoints_array', []) as $index => $checkpoint) {
            $type = $checkpoint['checkpoint_type'] === CheckpointType::Delivery->value ? Order::class : StoreUser::class;
            $id = (int) ($checkpoint['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $existing = $tripId === null ? null : Checkpoint::query()->where('trip_id', $tripId)->find($id);
            if ($existing === null || isset($seen[$id])) {
                $validator->errors()->add("checkpoints_array.$index.id", __('validation.exists', ['attribute' => 'checkpoint']));
            } elseif ($existing->relationable_type !== $type || $existing->relationable_id !== (int) $checkpoint['checkpoint_related_id'] || $existing->type !== $checkpoint['checkpoint_type']) {
                $validator->errors()->add("checkpoints_array.$index.checkpoint_related_id", __('validation.exists', ['attribute' => 'checkpoint']));
            }
            $seen[$id] = true;
        }
    }

    protected function validateDeparture(Validator $validator): void
    {
        if (! StoreUser::query()->whereKey(9)->exists()) {
            $validator->errors()->add('departure', __('messages.store_trip_departure_unavailable'));
        }
    }
}

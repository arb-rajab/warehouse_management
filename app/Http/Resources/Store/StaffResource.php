<?php

namespace App\Http\Resources\Store;

use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store staff member: their account's contact details, their role and
 * whether they hold Super Admin, which the staff screens leave alone.
 *
 * Needs `user` loaded with its `roles`, and `role`.
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read StoreUser|null $user
 * @property-read Role|null $role
 * @property-read Staff $resource
 */
class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,
            'role' => $this->role === null ? null : new RoleResource($this->role),
            'is_super_admin' => $this->resource->isSuperAdmin(),
        ];
    }
}

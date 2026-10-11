<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read StoreUser $resource */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'phone' => $user->phone, 'company_name' => $user->company_name,
            'is_rep' => (bool) $user->is_rep, 'archived' => (bool) $user->is_delete,
            'banned' => (bool) $user->banned, 'verified' => (bool) $user->admin_verified,
            'important' => (bool) $user->is_important, 'activity_status' => $user->activity_status,
            'rep_id' => $user->rep_id, 'rep_serial' => $user->rep_serial,
            'package' => $this->whenLoaded('representativePackage', fn () => $user->representativePackage === null ? null : ['id' => $user->representativePackage->id, 'name' => $user->representativePackage->name, 'percentage' => (string) $user->representativePackage->percentage]),
        ];
    }
}

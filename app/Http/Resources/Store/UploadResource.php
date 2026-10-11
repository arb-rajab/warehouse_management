<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * An uploaded file as the back office's file manager shows it.
 *
 * `url` is where the back office serves the file: its `external_link` when
 * it has one, otherwise `file_name` under this app's own public path (what
 * the Blade library did through `my_asset()`). `uploaded_by` is only present
 * when `user` is loaded, which the library page does and the file manager
 * dialog does not.
 *
 * @property-read int $id
 * @property-read string|null $file_original_name
 * @property-read string|null $file_name
 * @property-read string|null $external_link
 * @property-read string|null $extension
 * @property-read string|null $type
 * @property-read int|null $file_size
 * @property-read Carbon|null $created_at
 * @property-read StoreUser|null $user
 * @property-read Upload $resource
 */
class UploadResource extends JsonResource
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
            'name' => $this->file_original_name,
            'extension' => $this->extension,
            'type' => $this->type,
            'size' => $this->file_size,
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
        ];
    }

    private function url(): ?string
    {
        if (filled($this->external_link)) {
            return $this->external_link;
        }

        return blank($this->file_name) ? null : my_asset($this->file_name);
    }
}

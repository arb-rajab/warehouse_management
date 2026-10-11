<?php

namespace App\Http\Requests\Concerns;

use App\Models\Truck;
use App\Models\Upload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

trait ValidatesStoreTruck
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function truckRules(?Truck $truck): array
    {
        $retainedPhotoIds = $truck === null ? [] : explode(',', $truck->photos ?? '');

        return [
            'name' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'max_pallets_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'license_plate' => ['nullable', 'string', 'max:50', Rule::unique(Truck::class, 'license_plate')->ignore($truck)],
            'photos' => ['nullable', 'array', 'max:128'],
            'photos.*' => ['required', 'integer', 'distinct', Rule::exists(Upload::class, 'id')->where('type', 'image')->when(! $this->user('store')?->can('viewAny', Upload::class), fn (Exists $rule) => $rule->where(fn (Builder $query) => $query->where('user_id', $this->user('store')?->id)->orWhereIn('id', $retainedPhotoIds)))],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $photos = $this->input('photos', []);
            if (is_array($photos) && ! $validator->errors()->hasAny(['photos', 'photos.*']) && strlen(implode(',', $photos)) > 255) {
                $validator->errors()->add('photos', __('validation.max.string', ['attribute' => 'photos', 'max' => 255]));
            }
        }];
    }
}

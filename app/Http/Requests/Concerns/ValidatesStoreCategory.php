<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesStoreCategory
{
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function categoryRules(?Category $category = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'parent_id' => ['bail', 'required', 'integer', 'min:0', function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                $id = (int) $value;
                $visited = $category ? [$category->id] : [];
                while ($id !== 0) {
                    if (in_array($id, $visited, true)) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));

                        return;
                    }
                    $visited[] = $id;
                    $parent = Category::query()->without('category_translations')->select(['id', 'parent_id'])->find($id);
                    if (! $parent) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));

                        return;
                    }
                    $id = (int) $parent->parent_id;
                }
            }],
            'order_level' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],
            'digital' => ['required', 'boolean'],
            'slug' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'commision_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'filtering_attributes' => ['nullable', 'array'],
            'filtering_attributes.*' => ['integer', 'distinct', 'exists:attributes,id'],
        ];
        foreach (['banner', 'icon', 'cover_image'] as $field) {
            $value = $this->input($field);
            $retained = (is_int($value) || is_string($value)) && $category?->$field !== null && (string) $value === (string) $category->$field;
            $rules[$field] = ['nullable', 'integer', $retained ? Rule::exists('uploads', 'id')->where('type', 'image') : $this->imageUploadRule()];
        }

        return $rules;
    }
}

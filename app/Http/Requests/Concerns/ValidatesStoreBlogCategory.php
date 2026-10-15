<?php

namespace App\Http\Requests\Concerns;

trait ValidatesStoreBlogCategory
{
    /** @return array<string, array<string>> */
    protected function blogCategoryRules(): array
    {
        return ['category_name' => ['required', 'string', 'max:255']];
    }
}

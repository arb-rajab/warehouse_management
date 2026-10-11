<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Language;
use Illuminate\Database\Eloquent\Collection;

trait ProvidesStoreReferenceOptions
{
    /** @return Collection<int, Language> */
    private function languageOptions(): Collection
    {
        return Language::query()->select(['code', 'name'])->orderBy('id')->get();
    }
}

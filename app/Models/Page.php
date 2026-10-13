<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    public function getTranslation(string $field = '', string|false|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $page_translation = $this->hasMany(PageTranslation::class)->where('lang', $lang)->first();

        return $page_translation != null ? $page_translation->$field : $this->$field;
    }

    /** @return HasMany<PageTranslation, $this> */
    public function page_translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    /**
     * Where the storefront shows the page, as the back office's page list
     * gives it: the home page at the storefront root, any other page at
     * `custom-pages.show_custom_page` by slug (none without a slug).
     */
    public function publicUrl(): ?string
    {
        if ($this->type === 'home_page') {
            return route('home');
        }

        return $this->slug === null || $this->slug === '' ? null : route('custom-pages.show_custom_page', $this->slug);
    }
}

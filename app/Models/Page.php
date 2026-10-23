<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    /**
     * The storefront's own route for each policy page type. Such a page is
     * also served at its slug by the custom-page catch-all, so its policy
     * route is the one to index and to list in the sitemap.
     */
    public const array POLICY_ROUTES = [
        'seller_policy_page' => 'sellerpolicy',
        'return_policy_page' => 'returnpolicy',
        'support_policy_page' => 'supportpolicy',
        'terms_conditions_page' => 'terms',
        'privacy_policy_page' => 'privacypolicy',
    ];

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

    /** The name of the policy route this page is the content of, if it is a policy page. */
    public function policyRouteName(): ?string
    {
        return self::POLICY_ROUTES[$this->type] ?? null;
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

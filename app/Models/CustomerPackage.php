<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

/** @property-read Collection<int, CustomerPackageTranslation> $customer_package_translations */
class CustomerPackage extends Model
{
    public function getTranslation(string $field = '', string|false $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $translation = $this->relationLoaded('customer_package_translations')
            ? $this->customer_package_translations->firstWhere('lang', $lang)
            : $this->customer_package_translations()->where('lang', $lang)->first();

        return $translation !== null ? $translation->getAttribute($field) : $this->getAttribute($field);
    }

    /** @return HasMany<CustomerPackageTranslation, $this> */
    public function customer_package_translations(): HasMany
    {
        return $this->hasMany(CustomerPackageTranslation::class);
    }

    /** @return HasMany<CustomerPackagePayment, $this> */
    public function customer_package_payments(): HasMany
    {
        return $this->hasMany(CustomerPackagePayment::class);
    }
}

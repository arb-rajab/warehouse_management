<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Brand;
use App\Models\Upload;

trait ProvidesStoreBrandLogos
{
    /**
     * Set each brand's `logo_url` from one batched upload query, with the
     * same placeholder and URL resolution as `uploaded_asset()`.
     *
     * @param  iterable<Brand>  $brands
     */
    private function attachBrandLogoUrls(iterable $brands): void
    {
        $brands = collect($brands);
        $uploads = Upload::query()->select(['id', 'file_name', 'external_link'])->whereIn('id', $brands->pluck('logo')->filter())->get()->keyBy('id');
        foreach ($brands as $brand) {
            $logo = $uploads->get($brand->getAttribute('logo'));
            $brand->setAttribute('logo_url', $brand->getAttribute('logo') === null ? null : ($logo === null ? static_asset('assets/img/placeholder.jpg') : ($logo->external_link === null ? my_asset($logo->file_name) : ($logo->file_name ?? $logo->external_link))));
        }
    }
}

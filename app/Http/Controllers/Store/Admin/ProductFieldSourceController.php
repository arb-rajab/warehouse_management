<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Requests\Store\Admin\UpdateProductFieldSourcesRequest;
use App\Services\ProductFieldSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setup > Product data sources: for each product field the Otajer sync
 * writes, whether the sync ("Otajer synced") or the admin panel ("Admin
 * panel set") owns it. See .ai/rules/product-field-sources.md.
 */
class ProductFieldSourceController extends Controller implements HasMiddleware
{
    use FlashesSuccess;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:product_field_sources')];
    }

    public function edit(ProductFieldSourceService $fieldSources): Response
    {
        $sources = $fieldSources->sources();

        return Inertia::render('Store/Admin/Setup/ProductFieldSources/Index', [
            'fields' => array_map(fn (string $field): array => [
                'key' => $field,
                'source' => $sources[$field]->value,
                'default' => $fieldSources->defaultFor($field)->value,
            ], $fieldSources->fields()),
        ]);
    }

    public function update(UpdateProductFieldSourcesRequest $request, ProductFieldSourceService $fieldSources): RedirectResponse
    {
        $fieldSources->update($request->sources());
        $this->flashSuccess('product_data_sources_have_been_updated_successfully');

        return redirect()->route('product_field_sources.index');
    }
}

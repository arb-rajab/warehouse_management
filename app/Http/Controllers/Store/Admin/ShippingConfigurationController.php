<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\ReadsHighlight;
use App\Http\Requests\Store\Admin\UpdateShippingConfigurationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ShippingConfigurationController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use ReadsHighlight;
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:shipping_configuration')];
    }

    /**
     * What the page may mark through `?highlight=` (.ai/rules/deep-link-highlight.md):
     * its three settings, and the city and carrier notes the area wise and
     * carrier wise types are priced from.
     */
    public const array HIGHLIGHTS = ['shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin', 'cities', 'carriers'];

    /**
     * Each value exactly as saved: a setting never saved stays null, so the
     * page shows no type chosen or an empty cost, as the setup checklist
     * counts it, rather than a default that looks set.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Store/Admin/Setup/ShippingConfiguration/Index', [
            'settings' => [
                'shipping_type' => get_setting('shipping_type'),
                'flat_rate_shipping_cost' => get_setting('flat_rate_shipping_cost'),
                'shipping_cost_admin' => get_setting('shipping_cost_admin'),
            ],
            'highlight' => $this->highlight($request, self::HIGHLIGHTS),
        ]);
    }

    public function update(UpdateShippingConfigurationRequest $request): RedirectResponse
    {
        $key = $request->validated('type');
        $this->updateSettings([$key => $request->validated($key)]);
        $this->flashSuccess('shipping_configuration_has_been_updated_successfully');

        return redirect()->route('shipping_configuration.index');
    }
}

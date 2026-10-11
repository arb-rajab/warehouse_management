<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\SearchOrderProductsRequest;
use App\Http\Requests\Store\Admin\UpdateMinimumOrderConfigurationRequest;
use App\Http\Requests\Store\Admin\UpdateRepresentativeConfigurationRequest;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class OrderConfigurationController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:order_configuration')];
    }

    public function index(): Response
    {
        $matId = (string) get_setting('default_specific_product', '');

        return Inertia::render('Store/Admin/Setup/OrderConfiguration/Index', [
            'settings' => [
                'minimum_order_amount_check' => (bool) get_setting('minimum_order_amount_check', 0),
                'minimum_order_amount' => get_setting('minimum_order_amount', '0'),
                'default_specific_product' => $matId,
                'default_representative_check' => (bool) get_setting('default_representative_check', 0),
                'default_representative_id' => (int) get_setting('default_representative_id', 0),
            ],
            'selectedProduct' => Product::query()
                ->select(['id', 'name', 'mat_id'])
                ->where('mat_id', $matId)
                ->first(),
            'representatives' => StoreUser::query()
                ->select(['id', 'name'])
                ->where('is_rep', true)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function updateMinimum(UpdateMinimumOrderConfigurationRequest $request): RedirectResponse
    {
        $this->saveSettings([
            'minimum_order_amount_check' => $request->boolean('minimum_order_amount_check') ? '1' : '0',
            'minimum_order_amount' => (string) $request->validated('minimum_order_amount'),
            'default_specific_product' => $request->validated('default_specific_product'),
        ]);

        return redirect()->route('order_configuration.index');
    }

    public function updateRepresentative(UpdateRepresentativeConfigurationRequest $request): RedirectResponse
    {
        $this->saveSettings([
            'default_representative_check' => $request->boolean('default_representative_check') ? '1' : '0',
            'default_representative_id' => (string) $request->integer('default_representative_id'),
        ]);

        return redirect()->route('order_configuration.index');
    }

    public function search(SearchOrderProductsRequest $request): JsonResponse
    {
        return response()->json(
            Product::query()
                ->select(['id', 'name', 'mat_id'])
                ->where('mat_id', 'like', '%'.$request->validated('query').'%')
                ->orderBy('mat_id')
                ->limit(20)
                ->get()
        );
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function saveSettings(array $settings): void
    {
        foreach ($settings as $type => $value) {
            $setting = BusinessSetting::query()->where('type', $type)->first() ?? new BusinessSetting;
            $setting->type = $type;
            $setting->value = $value;
            $setting->save();
        }

        Cache::forget('business_settings');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\SearchOrderProductsRequest;
use App\Http\Requests\Store\Admin\UpdateDefaultRepresentativeRequest;
use App\Http\Requests\Store\Admin\UpdateOrderConfigurationRequest;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class OrderConfigurationController extends Controller implements HasMiddleware
{
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:order_configuration')];
    }

    public function index(): Response
    {
        $matId = get_setting('default_specific_product', '');

        return Inertia::render('Store/Admin/Setup/OrderConfiguration/Index', [
            'settings' => ['minimum_order_amount_check' => (bool) get_setting('minimum_order_amount_check', false), 'minimum_order_amount' => get_setting('minimum_order_amount', 0), 'default_specific_product' => $matId, 'default_representative_check' => (bool) get_setting('default_representative_check', false), 'default_representative_id' => get_setting('default_representative_id')],
            'selectedProduct' => Product::query()->select(['id', 'name', 'mat_id'])->where('mat_id', $matId)->first(),
            'representatives' => StoreUser::query()->select(['id', 'name'])->where('is_rep', 1)->orderByDesc('id')->get(),
        ]);
    }

    public function update(UpdateOrderConfigurationRequest $request): RedirectResponse
    {
        $this->updateSettings($request->validated());

        return redirect()->route('order_configuration.index');
    }

    public function updateRepresentative(UpdateDefaultRepresentativeRequest $request): RedirectResponse
    {
        $this->updateSettings($request->validated());

        return redirect()->route('order_configuration.index');
    }

    public function search(SearchOrderProductsRequest $request): JsonResponse
    {
        return response()->json(Product::query()->select(['id', 'name', 'mat_id'])->where('mat_id', 'like', '%'.$request->validated('query').'%')->orderBy('id')->limit(20)->get());
    }
}

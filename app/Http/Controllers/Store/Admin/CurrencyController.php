<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterCurrenciesRequest;
use App\Http\Requests\Store\Admin\StoreCurrencyRequest;
use App\Http\Requests\Store\Admin\UpdateCurrencyRequest;
use App\Http\Requests\Store\Admin\UpdateCurrencySettingsRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Resources\Store\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller implements HasMiddleware
{
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:currency_setup')];
    }

    public function index(FilterCurrenciesRequest $request): Response
    {
        return $this->page($request);
    }

    public function create(FilterCurrenciesRequest $request): Response
    {
        return $this->page($request, 'create');
    }

    public function edit(FilterCurrenciesRequest $request, Currency $currency): Response
    {
        return $this->page($request, 'edit', $currency);
    }

    private function page(FilterCurrenciesRequest $request, ?string $dialog = null, ?Currency $currency = null): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Setup/Currencies/Index', [
            'currencies' => $this->paginated(CurrencyResource::collection(Currency::query()->select(['id', 'name', 'symbol', 'code', 'exchange_rate', 'status'])->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('search').'%'))->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->validated('search')],
            'activeCurrencies' => Currency::query()->select(['id', 'name'])->where('status', 1)->orderBy('name')->get(),
            'settings' => ['system_default_currency' => (int) get_setting('system_default_currency', 0), 'symbol_format' => (int) get_setting('symbol_format', 1), 'decimal_separator' => (int) get_setting('decimal_separator', 1), 'no_of_decimals' => (int) get_setting('no_of_decimals', 2)],
            'dialog' => $dialog, 'selectedCurrency' => $currency === null ? null : new CurrencyResource($currency),
        ]);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $currency = new Currency;
        $currency->forceFill([...$request->validated(), 'status' => 0])->save();

        return redirect()->route('currency.index');
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->forceFill($request->validated())->save();
        Cache::forget('system_default_currency');

        return redirect()->route('currency.index');
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, Currency $currency): RedirectResponse
    {
        abort_if(! $request->boolean('status') && (int) get_setting('system_default_currency') === $currency->id, 403);
        $currency->status = $request->boolean('status') ? 1 : 0;
        $currency->save();

        return $this->redirectPreservingQuery('currency.index', $request);
    }

    public function configure(UpdateCurrencySettingsRequest $request): RedirectResponse
    {
        $this->updateSettings($request->validated());
        Cache::forget('system_default_currency');

        return redirect()->route('currency.index');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterCurrenciesRequest;
use App\Http\Requests\Store\Admin\StoreCurrencyRequest;
use App\Http\Requests\Store\Admin\UpdateCurrencyFormatsRequest;
use App\Http\Requests\Store\Admin\UpdateCurrencyRequest;
use App\Http\Requests\Store\Admin\UpdateDefaultCurrencyRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Resources\Store\CurrencyResource;
use App\Models\BusinessSetting;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:currency_setup')];
    }

    public function index(FilterCurrenciesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Setup/Currencies/Index', [
            'currencies' => $this->paginated(CurrencyResource::collection(
                Currency::query()
                    ->select(['id', 'name', 'symbol', 'code', 'exchange_rate', 'status'])
                    ->when($search, fn ($query, string $value) => $query->where('name', 'like', "%{$value}%"))
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'activeCurrencies' => CurrencyResource::collection(
                Currency::query()
                    ->select(['id', 'name', 'symbol', 'code', 'exchange_rate', 'status'])
                    ->where('status', 1)
                    ->orderBy('name')
                    ->get()
            ),
            'settings' => [
                'system_default_currency' => (int) get_setting('system_default_currency'),
                'symbol_format' => (int) get_setting('symbol_format', 1),
                'decimal_separator' => (int) get_setting('decimal_separator', 1),
                'no_of_decimals' => (int) get_setting('no_of_decimals', 2),
            ],
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $currency = new Currency;
        $this->fill($currency, $request);
        $currency->status = 0;
        $currency->save();

        return redirect()->route('currency.index');
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->fill($currency, $request);
        $currency->save();

        return redirect()->route('currency.index');
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, Currency $currency): RedirectResponse
    {
        if (! $request->boolean('status') && (int) get_setting('system_default_currency') === $currency->id) {
            throw ValidationException::withMessages([
                'status' => __('validation.prohibited', ['attribute' => 'status']),
            ]);
        }

        $currency->status = $request->boolean('status') ? 1 : 0;
        $currency->save();

        return $this->redirectPreservingQuery('currency.index', $request);
    }

    public function updateDefault(UpdateDefaultCurrencyRequest $request): RedirectResponse
    {
        $this->saveSetting('system_default_currency', (string) $request->integer('system_default_currency'));

        return redirect()->route('currency.index');
    }

    public function updateFormats(UpdateCurrencyFormatsRequest $request): RedirectResponse
    {
        foreach (['symbol_format', 'decimal_separator', 'no_of_decimals'] as $key) {
            $this->saveSetting($key, (string) $request->integer($key), forgetCache: false);
        }

        Cache::forget('business_settings');

        return redirect()->route('currency.index');
    }

    private function fill(Currency $currency, StoreCurrencyRequest|UpdateCurrencyRequest $request): void
    {
        $currency->name = $request->validated('name');
        $currency->symbol = $request->validated('symbol');
        $currency->code = $request->validated('code');
        $currency->exchange_rate = $request->validated('exchange_rate');
    }

    private function saveSetting(string $type, string $value, bool $forgetCache = true): void
    {
        $setting = BusinessSetting::query()->where('type', $type)->first() ?? new BusinessSetting;
        $setting->type = $type;
        $setting->value = $value;
        $setting->save();

        if ($forgetCache) {
            Cache::forget('business_settings');
        }
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
use App\Http\Requests\Store\Admin\UpdateDefaultRepresentativeRequest;
use App\Http\Requests\Store\Admin\UpdateDeliverySettingsRequest;
use App\Http\Requests\Store\Admin\UpdateOrderConfigurationRequest;
use App\Models\Product;
use App\Models\StoreUser;
use App\Support\SearchOption;
use App\Support\StoreDeliverySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class OrderConfigurationController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use SearchesOptions;
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:order_configuration')];
    }

    public function index(): Response
    {
        $defaultProductId = get_setting('default_specific_product', '');

        return Inertia::render('Store/Admin/Setup/OrderConfiguration/Index', [
            'settings' => ['minimum_order_amount_check' => (bool) get_setting('minimum_order_amount_check', false), 'minimum_order_amount' => get_setting('minimum_order_amount', 0), 'default_specific_product' => $defaultProductId, 'default_representative_check' => (bool) get_setting('default_representative_check', false), 'default_representative_id' => get_setting('default_representative_id')],
            'selectedProduct' => $this->selectedProduct($defaultProductId),
            'selectedRepresentative' => $this->selectedRepresentative(get_setting('default_representative_id')),
            'delivery' => $this->delivery(),
        ]);
    }

    /**
     * The delivery date settings the page shows: what is saved (null when
     * nothing is), what is in use and where it comes from, and the `.env` values.
     *
     * @return array<string, mixed>
     */
    private function delivery(): array
    {
        return [
            'saved' => [
                'delivery_lead_days' => StoreDeliverySettings::stored('delivery_lead_days'),
                'delivery_timezone' => StoreDeliverySettings::stored('delivery_timezone'),
            ],
            'effective' => [
                'delivery_lead_days' => StoreDeliverySettings::effective('delivery_lead_days'),
                'delivery_timezone' => StoreDeliverySettings::effective('delivery_timezone'),
            ],
            'environment' => [
                'delivery_lead_days' => StoreDeliverySettings::fromEnvironment('delivery_lead_days'),
                'delivery_timezone' => StoreDeliverySettings::fromEnvironment('delivery_timezone'),
            ],
            'timezones' => StoreDeliverySettings::timezones(),
            'bounds' => ['min' => StoreDeliverySettings::MIN_LEAD_DAYS, 'max' => StoreDeliverySettings::MAX_LEAD_DAYS],
        ];
    }

    /** The typeahead behind the default representative select (StoreSearchSelect). */
    public function representatives(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $reps = StoreUser::query()->select(['id', 'name', 'email', 'phone', 'AccSysID', 'member_serial', 'rep_discount_percentage'])->where('is_rep', 1);
        if ($term !== '') {
            $this->whereWordsMatch($reps, ['name', 'email', 'phone', 'AccSysID', 'member_serial'], $term);
        }
        $this->orderByRelevance($reps, 'name', $term);

        return $this->optionsPage($reps, fn (StoreUser $user): array => SearchOption::representative($user));
    }

    /** @return array<string, mixed>|null */
    private function selectedProduct(mixed $id): ?array
    {
        $product = Product::query()->select(['id', 'name', 'thumbnail_img'])
            ->with(['product_translations:id,product_id,lang,name', 'thumbnailUpload:id,file_name,external_link'])->where('id', $id)->first();

        return $product === null ? null : SearchOption::configurationProduct($product);
    }

    /** @return array<string, mixed>|null */
    private function selectedRepresentative(mixed $id): ?array
    {
        $rep = StoreUser::query()->select(['id', 'name', 'email', 'phone', 'AccSysID', 'member_serial', 'rep_discount_percentage'])->where('is_rep', 1)->where('id', $id)->first();

        return $rep === null ? null : SearchOption::representative($rep);
    }

    public function update(UpdateOrderConfigurationRequest $request): RedirectResponse
    {
        $this->updateSettings($request->validated());
        $this->flashSuccess('order_configuration_has_been_updated_successfully');

        return redirect()->route('order_configuration.index');
    }

    /** Save the delivery lead days and timezone (StoreDeliverySettings); empty uses `.env`. */
    public function updateDelivery(UpdateDeliverySettingsRequest $request): RedirectResponse
    {
        StoreDeliverySettings::save($request->validated());
        $this->flashSuccess('delivery_settings_have_been_updated_successfully');

        return redirect()->route('order_configuration.index');
    }

    public function updateRepresentative(UpdateDefaultRepresentativeRequest $request): RedirectResponse
    {
        $this->updateSettings($request->validated());
        $this->flashSuccess('default_representative_has_been_updated_successfully');

        return redirect()->route('order_configuration.index');
    }
}
